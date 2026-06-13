"""
WooCommerce Content Generator â rocaingenieria.com.ar
- Solo procesa productos en estado DRAFT
- Genera pÃ¡gina de catÃ¡logo en /catalogo/nombre-producto/ con contenido GEO
- Links internos en descripciones
- Sube descripciÃ³n + slug + Yoast SEO meta
- Crea/actualiza pÃ¡gina madre /catalogo/ como Ã­ndice

Uso:
    python woo_content_generator.py

Requisitos:
    pip install requests woocommerce
    Ollama corriendo: ollama serve
    Plugin WP: wp-rest-yoast-meta instalado y activo
"""

import requests
import json
import time
import logging
import base64
from woocommerce import API

# âââ CONFIGURACIÃN ââââââââââââââââââââââââââââââââââââââââââââââââââââââââââââ

WC_URL       = "https://rocaingenieria.com.ar"
WC_KEY       = "ck_XXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXX"  # <-- completar
WC_SECRET    = "cs_XXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXX"  # <-- completar

WP_USER      = "tu_usuario_admin"           # <-- completar
WP_PASS      = "xxxx xxxx xxxx xxxx xxxx"   # <-- Application Password

OLLAMA_URL   = "http://localhost:11434/api/generate"
OLLAMA_MODEL = "mistral"  # o "llama3.1:8b"

DELAY_BETWEEN_ITEMS = 5
DRY_RUN = False

# âââ LOGGING ââââââââââââââââââââââââââââââââââââââââââââââââââââââââââââââââââ

logging.basicConfig(
    level=logging.INFO,
    format="%(asctime)s [%(levelname)s] %(message)s",
    handlers=[
        logging.FileHandler("woo_generator.log", encoding="utf-8"),
        logging.StreamHandler()
    ]
)
log = logging.getLogger(__name__)

# âââ CLIENTES âââââââââââââââââââââââââââââââââââââââââââââââââââââââââââââââââ

wcapi = API(
    url=WC_URL,
    consumer_key=WC_KEY,
    consumer_secret=WC_SECRET,
    version="wc/v3",
    timeout=30
)

def wp_auth():
    token = base64.b64encode(f"{WP_USER}:{WP_PASS}".encode()).decode()
    return {"Authorization": f"Basic {token}", "Content-Type": "application/json"}

def wp_get(endpoint, params=None):
    url = f"{WC_URL}/wp-json/wp/v2/{endpoint}"
    return requests.get(url, headers=wp_auth(), params=params, timeout=30)

def wp_post(endpoint, payload):
    url = f"{WC_URL}/wp-json/wp/v2/{endpoint}"
    return requests.post(url, headers=wp_auth(), json=payload, timeout=30)

# âââ WOOCOMMERCE â LECTURA ââââââââââââââââââââââââââââââââââââââââââââââââââââ

def obtener_categorias():
    log.info("Obteniendo categorÃ­as...")
    cats, page = [], 1
    while True:
        r = wcapi.get("products/categories", params={"per_page": 100, "page": page})
        batch = r.json()
        if not batch:
            break
        cats.extend(batch)
        page += 1
    cats = [c for c in cats if c.get("slug") != "uncategorized"]
    log.info(f"  {len(cats)} categorÃ­as.")
    return cats

def obtener_productos_draft():
    log.info("Obteniendo productos en DRAFT...")
    prods, page = [], 1
    while True:
        r = wcapi.get("products", params={"per_page": 100, "page": page, "status": "draft"})
        batch = r.json()
        if not batch:
            break
        prods.extend(batch)
        page += 1
    log.info(f"  {len(prods)} productos en draft.")
    return prods

def obtener_todos_productos():
    prods, page = [], 1
    while True:
        r = wcapi.get("products", params={"per_page": 100, "page": page, "status": "any"})
        batch = r.json()
        if not batch:
            break
        prods.extend(batch)
        page += 1
    return prods

# âââ CATÃLOGO DE LINKS ââââââââââââââââââââââââââââââââââââââââââââââââââââââââ

def construir_catalogo(todos_productos, categorias):
    lineas = ["CATÃLOGO COMPLETO DEL SITIO (usar para links internos):\n"]
    lineas.append("## CategorÃ­as:")
    for c in categorias:
        lineas.append(f"  - {c['name']}: {WC_URL}/product-category/{c['slug']}/")
    lineas.append("\n## Productos publicados:")
    for p in todos_productos:
        if p.get("status") == "publish":
            cats = ", ".join(c["name"] for c in p.get("categories", []))
            lineas.append(f"  - {p['name']} [{cats}]: {WC_URL}/producto/{p['slug']}/")
            lineas.append(f"    Ficha tÃ©cnica: {WC_URL}/catalogo/{p['slug']}/")
    return "\n".join(lineas)

# âââ PÃGINA MADRE /catalogo/ ââââââââââââââââââââââââââââââââââââââââââââââââââ

def obtener_o_crear_pagina_catalogo():
    """
    Busca la pÃ¡gina madre /catalogo/. Si no existe, la crea.
    Devuelve el ID de la pÃ¡gina.
    """
    r = wp_get("pages", params={"slug": "catalogo", "per_page": 1})
    if r.status_code == 200 and r.json():
        page_id = r.json()[0]["id"]
        log.info(f"â PÃ¡gina madre /catalogo/ existe (id={page_id}).")
        return page_id

    log.info("  Creando pÃ¡gina madre /catalogo/...")
    if DRY_RUN:
        log.info("  [DRY RUN] Se crearÃ­a /catalogo/")
        return None

    payload = {
        "title":   "CatÃ¡logos TÃ©cnicos | Roca IngenierÃ­a",
        "slug":    "catalogo",
        "status":  "publish",
        "content": """<p>DescargÃ¡ las fichas tÃ©cnicas de los equipos de Roca IngenierÃ­a.
Cada catÃ¡logo incluye especificaciones tÃ©cnicas, aplicaciones industriales y datos de rendimiento.</p>
<p><em>Esta pÃ¡gina se actualiza automÃ¡ticamente con cada nuevo equipo incorporado al catÃ¡logo.</em></p>""",
        "meta": {
            "_yoast_wpseo_title":    "CatÃ¡logos TÃ©cnicos de Equipos Industriales | Roca IngenierÃ­a",
            "_yoast_wpseo_metadesc": "DescargÃ¡ fichas tÃ©cnicas de granalladoras, aspiradores y sistemas industriales. Especificaciones detalladas para ingenierÃ­a y compras.",
            "_yoast_wpseo_focuskw":  "catÃ¡logos tÃ©cnicos equipos industriales",
        }
    }
    r2 = wp_post("pages", payload)
    if r2.status_code in (200, 201):
        page_id = r2.json()["id"]
        log.info(f"  â PÃ¡gina /catalogo/ creada (id={page_id}).")
        return page_id
    else:
        log.error(f"  â No se pudo crear /catalogo/: {r2.status_code} {r2.text[:200]}")
        return None

# âââ PROMPTS ââââââââââââââââââââââââââââââââââââââââââââââââââââââââââââââââââ

SITE_CONTEXT = """
Sos un experto Senior en SEO B2B, marketing industrial y Copywriter TÃ©cnico especializado en LLMs.
RepresentÃ¡s a la empresa 'Roca IngenierÃ­a' (www.rocaingenieria.com.ar), fabricante argentino de
equipos de granallado, aspiraciÃ³n industrial y automatizaciÃ³n.

Reglas:
- Lenguaje tÃ©cnico-profesional, mercado B2B de Argentina.
- Sin adjetivos vacÃ­os. Datos tangibles: normativas (Sa 2.5), marcas (Siemens, SKF), eficiencia.
- Nunca mencionar competidores.
- Optimizado para Google (SEO) y motores de IA (GEO/AIO).
- Formato Problema/SoluciÃ³n con viÃ±etas claras.
"""

def prompt_producto(nombre, categoria, catalogo, descripcion_existente=""):
    contexto_extra = f'\nDescripciÃ³n actual (mejorar):\n"{descripcion_existente}"' if descripcion_existente else ""
    return f"""{SITE_CONTEXT}

{catalogo}

---
GenerÃ¡ el contenido SEO completo para el PRODUCTO WooCommerce: "{nombre}"
CategorÃ­a: {categoria}{contexto_extra}

INSTRUCCIONES DE LINKING INTERNO:
- DescripciÃ³n larga: incluÃ­ 2-4 links con <a href="URL">texto</a>
- Linkear obligatoriamente a la categorÃ­a propia
- Linkear a la ficha tÃ©cnica del producto: {WC_URL}/catalogo/[slug-del-producto]/
- Linkear a 1 producto complementario del catÃ¡logo
- Solo usar URLs del catÃ¡logo provisto, no inventar URLs

Estructura:
- Metadatos: keyword principal, slug, SEO Title (mÃ¡x 60 chars con 'Roca IngenierÃ­a'), meta descripciÃ³n (140-156 chars con CTA).
- DescripciÃ³n corta: 60-80 palabras texto plano. Problema que resuelve + ventaja tÃ©cnica.
- DescripciÃ³n larga (~300 palabras) HTML: H1 tÃ©cnico, H2 beneficios operativos, H3 especificaciones. Keyword 2 veces. Links internos incluidos.
- ImÃ¡genes: metadatos para 3 imÃ¡genes sugeridas.

DevolvÃ© EXACTAMENTE este JSON (sin texto extra, sin markdown, sin backticks):
{{
  "focus_keyword": "...",
  "slug": "slug-corto",
  "seo_title": "... | Roca IngenierÃ­a",
  "meta_descripcion": "140-156 chars con CTA",
  "descripcion_corta": "60-80 palabras texto plano",
  "descripcion_larga": "HTML ~300 palabras con H1 H2 H3 ul li links internos",
  "imagenes": [
    {{"archivo": "nombre.jpg", "alt": "...", "titulo": "...", "leyenda": "..."}},
    {{"archivo": "nombre-2.jpg", "alt": "...", "titulo": "...", "leyenda": "..."}},
    {{"archivo": "nombre-3.jpg", "alt": "...", "titulo": "...", "leyenda": "..."}}
  ]
}}
"""

def prompt_pagina_catalogo(nombre, slug_producto, categoria, catalogo, url_producto):
    return f"""{SITE_CONTEXT}

{catalogo}

---
GenerÃ¡ el contenido de la PÃGINA DE CATÃLOGO (ficha tÃ©cnica informativa) para: "{nombre}"
CategorÃ­a: {categoria}
URL del producto en tienda: {url_producto}

Esta pÃ¡gina NO es de venta â es una ficha tÃ©cnica informativa orientada a:
1. Ingenieros y responsables de compras que necesitan especificaciones detalladas
2. Motores de IA (GEO/AIO): el contenido debe ser tan completo y estructurado que un LLM
   pueda responder preguntas tÃ©cnicas sobre el equipo citando esta pÃ¡gina.

INSTRUCCIONES GEO/AIO:
- Usar formato pregunta-respuesta implÃ­cito: cada secciÃ³n debe responder una pregunta tÃ©cnica concreta
- Incluir datos especÃ­ficos: rangos de presiÃ³n, caudal, potencia, materiales, normativas
- Usar <dl>/<dt>/<dd> para especificaciones tÃ©cnicas (ideal para LLMs)
- Incluir una secciÃ³n "Preguntas frecuentes" con 3-4 FAQs tÃ©cnicas en formato <details>/<summary>
- Schema-friendly: usar estructura clara con H2/H3 semÃ¡nticos

INSTRUCCIONES DE LINKING:
- Linkear al producto en tienda: {url_producto}
- Linkear a la categorÃ­a propia
- Linkear a 1-2 equipos complementarios del catÃ¡logo

Estructura requerida:
- Intro tÃ©cnica (~80 palabras): quÃ© es, para quÃ© industrias, quÃ© problema resuelve
- Especificaciones tÃ©cnicas (tabla <dl>): potencia, dimensiones, capacidad, materiales, normativas
- Aplicaciones industriales: lista de industrias y casos de uso concretos
- Ventajas operativas: formato Problema/SoluciÃ³n con datos tangibles
- FAQs tÃ©cnicas: 3-4 preguntas con respuestas detalladas
- CTA final: botÃ³n de descarga PDF + link al producto

DevolvÃ© EXACTAMENTE este JSON (sin texto extra, sin markdown, sin backticks):
{{
  "focus_keyword": "ficha tÃ©cnica [nombre] industrial",
  "slug": "catalogo-{slug_producto}",
  "seo_title": "Ficha TÃ©cnica [nombre] | Roca IngenierÃ­a",
  "meta_descripcion": "140-156 chars: ficha tÃ©cnica + CTA descarga",
  "titulo_pagina": "TÃ­tulo H1 de la pÃ¡gina",
  "contenido_html": "HTML completo: intro, specs dl/dt/dd, aplicaciones, ventajas, FAQs details/summary, CTA con botÃ³n descarga y link a tienda"
}}
"""

# âââ OLLAMA âââââââââââââââââââââââââââââââââââââââââââââââââââââââââââââââââââ

def generar_con_ollama(prompt, intentos=3):
    for intento in range(1, intentos + 1):
        texto = ""
        try:
            log.info(f"  Ollama (intento {intento}/{intentos})...")
            resp = requests.post(
                OLLAMA_URL,
                json={"model": OLLAMA_MODEL, "prompt": prompt, "stream": False},
                timeout=300
            )
            resp.raise_for_status()
            texto = resp.json().get("response", "").strip()

            if "```" in texto:
                partes = texto.split("```")
                for parte in partes:
                    parte = parte.strip()
                    if parte.startswith("json"):
                        parte = parte[4:].strip()
                    try:
                        return json.loads(parte)
                    except Exception:
                        continue

            return json.loads(texto)

        except json.JSONDecodeError as e:
            log.warning(f"  JSON invÃ¡lido (intento {intento}): {e}")
            log.debug(f"  Respuesta cruda: {texto[:400]}")
            if intento == intentos:
                log.error("  No se pudo parsear despuÃ©s de todos los intentos.")
                return None
        except Exception as e:
            log.error(f"  Error Ollama (intento {intento}): {e}")
            if intento == intentos:
                return None
        time.sleep(3)

# âââ ESCRITURA â PRODUCTO âââââââââââââââââââââââââââââââââââââââââââââââââââââ

def actualizar_producto(prod_id, contenido):
    if DRY_RUN:
        log.info(f"  [DRY RUN] producto {prod_id} | kw: {contenido.get('focus_keyword')}")
        return True

    payload = {
        "description":       contenido.get("descripcion_larga", ""),
        "short_description": contenido.get("descripcion_corta", ""),
        "slug":              contenido.get("slug", ""),
    }
    r = wcapi.put(f"products/{prod_id}", payload)
    if r.status_code not in (200, 201):
        log.error(f"  â WC error prod {prod_id}: {r.status_code} {r.text[:200]}")
        return False
    log.info(f"  â DescripciÃ³n y slug actualizados.")

    # Yoast
    r2 = wp_post(f"product/{prod_id}", {
        "meta": {
            "_yoast_wpseo_title":    contenido.get("seo_title", ""),
            "_yoast_wpseo_metadesc": contenido.get("meta_descripcion", ""),
            "_yoast_wpseo_focuskw":  contenido.get("focus_keyword", ""),
        }
    })
    if r2.status_code in (200, 201):
        log.info(f"  â Yoast meta actualizados.")
    else:
        log.warning(f"  â  Yoast fallÃ³: {r2.status_code}")

    if contenido.get("imagenes"):
        for img in contenido["imagenes"]:
            log.info(f"    ð· {img.get('archivo')} | alt: {img.get('alt')}")

    return True

# âââ ESCRITURA â PÃGINA DE CATÃLOGO ââââââââââââââââââââââââââââââââââââââââââ

def crear_o_actualizar_pagina_catalogo(nombre, slug_producto, contenido_cat, parent_id, url_pdf_placeholder):
    """
    Crea o actualiza la pÃ¡gina WordPress en /catalogo/slug-producto/
    Si el PDF ya existe en Media Library, reemplazar url_pdf_placeholder con la URL real.
    """
    slug_pagina = f"catalogo-{slug_producto}"
    url_producto = f"{WC_URL}/producto/{slug_producto}/"

    # BotÃ³n de descarga PDF + link a tienda
    cta_html = f"""
<div class="roca-cta-catalogo" style="margin:2em 0;padding:1.5em;background:#f5f5f5;border-left:4px solid #c00;">
  <p><strong>Â¿NecesitÃ¡s la ficha tÃ©cnica completa?</strong></p>
  <a href="{url_pdf_placeholder}" class="button" download
     style="display:inline-block;background:#c00;color:#fff;padding:.7em 1.5em;text-decoration:none;margin-right:1em;">
    â¬ Descargar catÃ¡logo PDF
  </a>
  <a href="{url_producto}"
     style="display:inline-block;color:#c00;padding:.7em 0;">
    Ver precio y disponibilidad â
  </a>
</div>"""

    # Inyectar CTA al final del contenido generado
    contenido_final = contenido_cat.get("contenido_html", "") + cta_html

    if DRY_RUN:
        log.info(f"  [DRY RUN] PÃ¡gina /catalogo/{slug_producto}/ | tÃ­tulo: {contenido_cat.get('titulo_pagina')}")
        return True

    # Buscar si ya existe
    r = wp_get("pages", params={"slug": slug_pagina, "per_page": 1})
    existe = r.status_code == 200 and r.json()

    payload = {
        "title":   contenido_cat.get("titulo_pagina", f"CatÃ¡logo {nombre}"),
        "slug":    slug_pagina,
        "status":  "draft",   # vos la revisÃ¡s y publicÃ¡s
        "content": contenido_final,
        "parent":  parent_id or 0,
        "meta": {
            "_yoast_wpseo_title":    contenido_cat.get("seo_title", ""),
            "_yoast_wpseo_metadesc": contenido_cat.get("meta_descripcion", ""),
            "_yoast_wpseo_focuskw":  contenido_cat.get("focus_keyword", ""),
        }
    }

    if existe:
        page_id = r.json()[0]["id"]
        r2 = wp_post(f"pages/{page_id}", payload)
        accion = "actualizada"
    else:
        r2 = wp_post("pages", payload)
        accion = "creada"

    if r2.status_code in (200, 201):
        page_id = r2.json()["id"]
        log.info(f"  â PÃ¡gina /catalogo/{slug_producto}/ {accion} (id={page_id}, status=draft).")
        log.info(f"    URL: {WC_URL}/catalogo/{slug_producto}/")
        log.info(f"    PDF placeholder: {url_pdf_placeholder}")
        log.info(f"    â ReemplazÃ¡ el placeholder cuando subas el PDF.")
        return True
    else:
        log.error(f"  â Error creando pÃ¡gina catÃ¡logo: {r2.status_code} {r2.text[:300]}")
        return False

# âââ PIPELINE âââââââââââââââââââââââââââââââââââââââââââââââââââââââââââââââââ

def main():
    log.info("=" * 60)
    log.info("WooCommerce Content Generator â rocaingenieria.com.ar")
    log.info(f"Modelo: {OLLAMA_MODEL} | DRY_RUN: {DRY_RUN}")
    log.info("=" * 60)

    try:
        requests.get("http://localhost:11434/", timeout=5)
        log.info("â Ollama corriendo.")
    except Exception:
        log.error("â Ollama no responde. CorrÃ© 'ollama serve' primero.")
        return

    try:
        r = wcapi.get("system_status")
        if r.status_code != 200:
            raise Exception(f"Status {r.status_code}")
        log.info("â WooCommerce API conectada.")
    except Exception as e:
        log.error(f"â WooCommerce no responde: {e}")
        return

    # Cargar datos
    categorias      = obtener_categorias()
    drafts          = obtener_productos_draft()
    todos_productos = obtener_todos_productos()

    if not drafts:
        log.info("No hay productos en draft. Nada que procesar.")
        return

    catalogo  = construir_catalogo(todos_productos, categorias)
    cat_nombre = {c["id"]: c["name"] for c in categorias}

    # Asegurar que existe la pÃ¡gina madre /catalogo/
    parent_id = obtener_o_crear_pagina_catalogo()

    log.info(f"\n=== PROCESANDO {len(drafts)} PRODUCTOS EN DRAFT ===\n")

    for prod in drafts:
        nombre   = prod["name"]
        prod_id  = prod["id"]
        slug     = prod.get("slug") or nombre.lower().replace(" ", "-")
        desc_act = prod.get("short_description", "")
        cats     = [cat_nombre.get(c["id"], c["name"]) for c in prod.get("categories", [])]
        cat_str  = ", ".join(cats) if cats else "Sin categorÃ­a"
        url_prod = f"{WC_URL}/producto/{slug}/"

        log.info(f"\nâ {nombre} (id={prod_id} | cat={cat_str})")

        # 1. Contenido del producto WooCommerce
        log.info("  Generando contenido de producto...")
        c_prod = generar_con_ollama(prompt_producto(nombre, cat_str, catalogo, desc_act))
        if c_prod:
            actualizar_producto(prod_id, c_prod)
            slug = c_prod.get("slug", slug)  # usar slug generado si es mejor
        else:
            log.warning(f"  Saltando contenido de producto '{nombre}'.")

        time.sleep(DELAY_BETWEEN_ITEMS)

        # 2. PÃ¡gina de catÃ¡logo
        log.info("  Generando pÃ¡gina de catÃ¡logo...")
        c_cat = generar_con_ollama(
            prompt_pagina_catalogo(nombre, slug, cat_str, catalogo, url_prod)
        )
        if c_cat:
            # Placeholder PDF â reemplazar cuando subas el archivo
            url_pdf = f"{WC_URL}/wp-content/uploads/catalogos/{slug}.pdf"
            crear_o_actualizar_pagina_catalogo(nombre, slug, c_cat, parent_id, url_pdf)
        else:
            log.warning(f"  Saltando pÃ¡gina de catÃ¡logo '{nombre}'.")

        time.sleep(DELAY_BETWEEN_ITEMS)

    log.info("\nâ Proceso completo.")
    log.info("PrÃ³ximos pasos:")
    log.info("  1. RevisÃ¡ las pÃ¡ginas en draft: wp-admin â PÃ¡ginas â CatÃ¡logos")
    log.info("  2. SubÃ­ los PDFs a Media â wp-content/uploads/catalogos/")
    log.info("  3. PublicÃ¡ las pÃ¡ginas que aprobÃ©s")
    log.info("  4. PublicÃ¡ los productos WooCommerce")

if __name__ == "__main__":
    main()
