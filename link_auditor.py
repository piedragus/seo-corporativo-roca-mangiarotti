# -*- coding: utf-8 -*-
"""
Link Auditor — rocaingenieria.com.ar
Dos modos:

  python link_auditor.py analyze   -> lee el sitio, genera links_propuestos.csv
  python link_auditor.py apply     -> aplica los links del CSV (solo filas con aprobado=SI)

Flujo:
  1. De dia:   python link_auditor.py analyze
  2. Abris links_propuestos.csv en Excel, cambias aprobado=NO en los que no queres
  3. De noche: python link_auditor.py apply
"""

import sys
import csv
import json
import re
import time
import logging
import base64
import requests
from woocommerce import API

# CONFIG
WC_URL    = "https://rocaingenieria.com.ar"
WC_KEY    = "ck_XXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXX"
WC_SECRET = "cs_XXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXX"
WP_USER   = "tu_usuario_admin"
WP_PASS   = "xxxx xxxx xxxx xxxx xxxx"
OLLAMA_URL   = "http://localhost:11434/api/generate"
OLLAMA_MODEL = "mistral"
CSV_FILE  = "links_propuestos.csv"
DELAY     = 3
DRY_RUN   = False

# LOGGING
logging.basicConfig(
    level=logging.INFO,
    format="%(asctime)s [%(levelname)s] %(message)s",
    handlers=[
        logging.FileHandler("link_auditor.log", encoding="utf-8"),
        logging.StreamHandler()
    ]
)
log = logging.getLogger(__name__)

# CLIENTES
wcapi = API(url=WC_URL, consumer_key=WC_KEY, consumer_secret=WC_SECRET,
            version="wc/v3", timeout=30)

def wp_auth():
    token = base64.b64encode(f"{WP_USER}:{WP_PASS}".encode()).decode()
    return {"Authorization": f"Basic {token}", "Content-Type": "application/json"}

def wp_get(endpoint, params=None):
    return requests.get(f"{WC_URL}/wp-json/wp/v2/{endpoint}",
                        headers=wp_auth(), params=params, timeout=30)

def wp_post(endpoint, payload):
    return requests.post(f"{WC_URL}/wp-json/wp/v2/{endpoint}",
                         headers=wp_auth(), json=payload, timeout=30)

# LECTURA
def obtener_productos_publicados():
    prods, page = [], 1
    while True:
        r = wcapi.get("products", params={"per_page": 100, "page": page, "status": "publish"})
        batch = r.json()
        if not batch:
            break
        prods.extend(batch)
        page += 1
    return prods

def obtener_categorias():
    cats, page = [], 1
    while True:
        r = wcapi.get("products/categories", params={"per_page": 100, "page": page})
        batch = r.json()
        if not batch:
            break
        cats.extend(batch)
        page += 1
    return [c for c in cats if c.get("slug") != "uncategorized"]

def obtener_paginas_catalogo():
    r = wp_get("pages", params={"slug": "catalogo", "per_page": 1})
    if r.status_code != 200 or not r.json():
        return []
    parent_id = r.json()[0]["id"]
    pages, page = [], 1
    while True:
        r = wp_get("pages", params={"parent": parent_id, "per_page": 100,
                                     "page": page, "status": "publish"})
        batch = r.json()
        if not batch:
            break
        pages.extend(batch)
        page += 1
    return pages

# HELPERS HTML
def extraer_links_del_html(html):
    """Devuelve set de URLs linkeadas en el contenido. Maneja None."""
    if not html:
        return set()
    return set(re.findall(r'href=["\']([^"\']+)["\']', html))

def inyectar_link_en_html(html, anchor_text, url_destino):
    """
    Reemplaza la primera ocurrencia de anchor_text que no este dentro de un <a>.
    Devuelve (html_nuevo, True) si lo hizo, (html, False) si no encontro el texto.
    Maneja None, cadena vacia y anchors con caracteres especiales de regex.
    """
    if not html or not anchor_text:
        return (html or ""), False

    try:
        m = re.search(re.escape(anchor_text), html)
    except re.error:
        return html, False

    if not m:
        return html, False

    start, end = m.start(), m.end()

    # Verificar que no esta dentro de un <a> abierto
    antes = html[:start]
    abren   = len(re.findall(r'<a\b[^>]*>', antes))
    cierran = len(re.findall(r'</a>', antes))
    if abren > cierran:
        return html, False

    nuevo = html[:start] + f'<a href="{url_destino}">{anchor_text}</a>' + html[end:]
    return nuevo, True

# MAPA DEL SITIO
def construir_mapa_sitio(todos_productos, categorias, paginas_catalogo):
    mapa = []
    for p in todos_productos:
        html = (p.get("description") or "") + (p.get("short_description") or "")
        mapa.append({
            "tipo":           "producto",
            "id":             p["id"],
            "nombre":         p["name"],
            "slug":           p.get("slug", ""),
            "url":            f"{WC_URL}/producto/{p.get('slug', '')}/",
            "categorias":     [c["name"] for c in p.get("categories") or []],
            "links_actuales": extraer_links_del_html(html),
            "html_desc":      p.get("description") or "",
        })
    for c in categorias:
        mapa.append({
            "tipo":           "categoria",
            "id":             c["id"],
            "nombre":         c["name"],
            "slug":           c.get("slug", ""),
            "url":            f"{WC_URL}/product-category/{c.get('slug', '')}/",
            "categorias":     [],
            "links_actuales": extraer_links_del_html(c.get("description") or ""),
            "html_desc":      c.get("description") or "",
        })
    for pg in paginas_catalogo:
        html = (pg.get("content") or {}).get("rendered") or ""
        slug_pg = pg.get("slug", "")
        slug_clean = slug_pg.replace("catalogo-", "", 1)
        mapa.append({
            "tipo":           "catalogo",
            "id":             pg["id"],
            "nombre":         (pg.get("title") or {}).get("rendered", ""),
            "slug":           slug_pg,
            "url":            f"{WC_URL}/catalogo/{slug_clean}/",
            "categorias":     [],
            "links_actuales": extraer_links_del_html(html),
            "html_desc":      html,
        })
    return mapa

# OLLAMA
def generar_con_ollama(prompt, intentos=3):
    """Llama a Ollama y parsea la respuesta JSON. Robusta ante fallos y mal JSON."""
    for intento in range(1, intentos + 1):
        texto = ""
        try:
            resp = requests.post(
                OLLAMA_URL,
                json={"model": OLLAMA_MODEL, "prompt": prompt, "stream": False},
                timeout=300
            )
            resp.raise_for_status()
            texto = resp.json().get("response", "").strip()

            if "```" in texto:
                for parte in texto.split("```"):
                    parte = parte.strip()
                    if parte.startswith("json"):
                        parte = parte[4:].strip()
                    try:
                        return json.loads(parte)
                    except Exception:
                        continue

            return json.loads(texto)

        except json.JSONDecodeError as e:
            log.warning(f"  JSON invalido (intento {intento}): {e}")
            if intento == intentos:
                log.error("  No se pudo parsear despues de todos los intentos.")
                return None
        except Exception as e:
            log.error(f"  Error Ollama (intento {intento}): {e}")
            if intento == intentos:
                return None
        time.sleep(2)

# PROMPTS
def prompt_analizar_links(item, mapa_sitio):
    destinos = "\n".join(
        f"  - [{m['tipo']}] {m['nombre']}: {m['url']}"
        for m in mapa_sitio if m["url"] != item["url"]
    )
    links_ya = "\n".join(f"  - {l}" for l in item["links_actuales"]) or "  (ninguno)"
    return f"""Sos un experto SEO B2B para Roca Ingenieria (rocaingenieria.com.ar),
fabricante argentino de equipos industriales.

Analiza este contenido y propone links internos que FALTAN o estan DESACTUALIZADOS.

PAGINA A ANALIZAR:
- Tipo: {item['tipo']}
- Nombre: {item['nombre']}
- URL: {item['url']}
- Categorias: {', '.join(item['categorias']) or 'N/A'}

LINKS QUE YA TIENE:
{links_ya}

CONTENIDO HTML ACTUAL (primeros 1500 chars):
{item['html_desc'][:1500]}

DESTINOS DISPONIBLES EN EL SITIO:
{destinos}

CRITERIOS:
1. Destino semanticamente relevante (mismo equipo, misma industria, complementario)
2. No esta ya linkeado
3. El anchor text debe ser una frase que ya existe en el HTML
4. Maximo 4 links nuevos por pagina

Si no hay links utiles, devuelve lista vacia.

Devuelve EXACTAMENTE este JSON (sin texto extra, sin markdown, sin backticks):
{{
  "links_propuestos": [
    {{
      "anchor_text": "texto exacto que ya existe en el HTML",
      "url_destino": "URL completa del destino",
      "nombre_destino": "nombre legible del destino",
      "razon": "por que este link agrega valor SEO"
    }}
  ]
}}
"""

# MODO ANALYZE
def modo_analyze():
    log.info("=" * 60)
    log.info("MODO ANALYZE")
    log.info("=" * 60)

    productos        = obtener_productos_publicados()
    categorias       = obtener_categorias()
    paginas_catalogo = obtener_paginas_catalogo()

    log.info(f"Sitio: {len(productos)} productos, {len(categorias)} categorias, "
             f"{len(paginas_catalogo)} paginas de catalogo.")

    mapa       = construir_mapa_sitio(productos, categorias, paginas_catalogo)
    propuestas = []

    for item in mapa:
        log.info(f"\n-> Analizando: [{item['tipo']}] {item['nombre']}")
        prompt    = prompt_analizar_links(item, mapa)
        resultado = generar_con_ollama(prompt)

        if resultado is None:
            log.warning("  Sin respuesta de Ollama, saltando.")
            time.sleep(DELAY)
            continue

        links = resultado.get("links_propuestos", [])
        if not links:
            log.info("  Sin links nuevos.")
        else:
            log.info(f"  {len(links)} links propuestos.")
            for lk in links:
                propuestas.append({
                    "aprobado":       "SI",
                    "tipo_origen":    item["tipo"],
                    "id_origen":      item["id"],
                    "nombre_origen":  item["nombre"],
                    "url_origen":     item["url"],
                    "anchor_text":    lk.get("anchor_text", ""),
                    "url_destino":    lk.get("url_destino", ""),
                    "nombre_destino": lk.get("nombre_destino", ""),
                    "razon":          lk.get("razon", ""),
                })
                log.info(f'    + "{lk.get("anchor_text")}" -> {lk.get("nombre_destino")}')

        time.sleep(DELAY)

    if not propuestas:
        log.info("\nNo se encontraron oportunidades de linking.")
        return

    campos = ["aprobado", "tipo_origen", "id_origen", "nombre_origen",
              "url_origen", "anchor_text", "url_destino", "nombre_destino", "razon"]
    with open(CSV_FILE, "w", newline="", encoding="utf-8") as f:
        writer = csv.DictWriter(f, fieldnames=campos)
        writer.writeheader()
        writer.writerows(propuestas)

    log.info(f"\n{len(propuestas)} links propuestos guardados en {CSV_FILE}")
    log.info("Proximos pasos:")
    log.info("  1. Abri links_propuestos.csv en Excel")
    log.info("  2. Cambia 'aprobado' a NO en los que no queres")
    log.info(f"  3. De noche: python link_auditor.py apply")

# APLICAR LINKS
def aplicar_link_producto(item, anchor_text, url_destino):
    html_nuevo, ok = inyectar_link_en_html(item["html_desc"], anchor_text, url_destino)
    if not ok:
        log.warning(f"  Anchor text no encontrado: '{anchor_text}'")
        return False
    if DRY_RUN:
        log.info(f"  [DRY RUN] producto {item['id']}")
        return True
    r = wcapi.put(f"products/{item['id']}", {"description": html_nuevo})
    if r.status_code in (200, 201):
        item["html_desc"] = html_nuevo
        log.info("  Link inyectado en producto.")
        return True
    log.error(f"  Error WC: {r.status_code}")
    return False

def aplicar_link_categoria(item, anchor_text, url_destino):
    html_nuevo, ok = inyectar_link_en_html(item["html_desc"], anchor_text, url_destino)
    if not ok:
        log.warning(f"  Anchor text no encontrado: '{anchor_text}'")
        return False
    if DRY_RUN:
        log.info(f"  [DRY RUN] categoria {item['id']}")
        return True
    r = wcapi.put(f"products/categories/{item['id']}", {"description": html_nuevo})
    if r.status_code in (200, 201):
        item["html_desc"] = html_nuevo
        log.info("  Link inyectado en categoria.")
        return True
    log.error(f"  Error WC: {r.status_code}")
    return False

def aplicar_link_pagina(item, anchor_text, url_destino):
    html_nuevo, ok = inyectar_link_en_html(item["html_desc"], anchor_text, url_destino)
    if not ok:
        log.warning(f"  Anchor text no encontrado: '{anchor_text}'")
        return False
    if DRY_RUN:
        log.info(f"  [DRY RUN] pagina {item['id']}")
        return True
    r = wp_post(f"pages/{item['id']}", {"content": html_nuevo})
    if r.status_code in (200, 201):
        item["html_desc"] = html_nuevo
        log.info("  Link inyectado en pagina.")
        return True
    log.error(f"  Error WP: {r.status_code}")
    return False

# MODO APPLY
def modo_apply():
    log.info("=" * 60)
    log.info(f"MODO APPLY | DRY_RUN: {DRY_RUN}")
    log.info("=" * 60)

    try:
        with open(CSV_FILE, newline="", encoding="utf-8") as f:
            rows = list(csv.DictReader(f))
    except FileNotFoundError:
        log.error(f"No se encontro {CSV_FILE}. Corre primero: python link_auditor.py analyze")
        return

    aprobados = [r for r in rows if r.get("aprobado", "").strip().upper() == "SI"]
    log.info(f"{len(aprobados)} links aprobados de {len(rows)} totales.")

    if not aprobados:
        log.info("Nada para aplicar.")
        return

    log.info("Cargando contenido actual del sitio...")
    mapa    = construir_mapa_sitio(
        obtener_productos_publicados(),
        obtener_categorias(),
        obtener_paginas_catalogo()
    )
    indice  = {m["id"]: m for m in mapa}
    ok_count, err_count = 0, 0

    for row in aprobados:
        try:
            tipo      = row.get("tipo_origen", "")
            item_id   = int(row.get("id_origen", "0"))
            anchor    = row.get("anchor_text", "").strip()
            url_dest  = row.get("url_destino", "").strip()
            nombre_o  = row.get("nombre_origen", "?")
            nombre_d  = row.get("nombre_destino", "?")
        except (ValueError, KeyError) as e:
            log.warning(f"  Fila CSV malformada: {e} — saltando.")
            err_count += 1
            continue

        log.info(f'\n-> [{tipo}] {nombre_o} | "{anchor}" -> {nombre_d}')

        item = indice.get(item_id)
        if not item:
            log.warning(f"  Item id={item_id} no encontrado en el sitio.")
            err_count += 1
            continue

        if tipo == "producto":
            ok = aplicar_link_producto(item, anchor, url_dest)
        elif tipo == "categoria":
            ok = aplicar_link_categoria(item, anchor, url_dest)
        elif tipo == "catalogo":
            ok = aplicar_link_pagina(item, anchor, url_dest)
        else:
            log.warning(f"  Tipo desconocido: {tipo} — saltando.")
            ok = False

        if ok:
            ok_count += 1
        else:
            err_count += 1

        time.sleep(DELAY)

    log.info(f"\nProceso completo: {ok_count} links aplicados, {err_count} errores.")

# MAIN
if __name__ == "__main__":
    modo = sys.argv[1].lower() if len(sys.argv) > 1 else "analyze"
    if modo == "analyze":
        modo_analyze()
    elif modo == "apply":
        modo_apply()
    else:
        print("Uso: python link_auditor.py [analyze|apply]")
