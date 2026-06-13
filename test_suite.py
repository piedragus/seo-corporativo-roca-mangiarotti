# -*- coding: utf-8 -*-
"""
Test suite completo — link_auditor.py + woo_content_generator.py
Cubre: lógica, valores límite, inputs malformados, stress, edge cases
"""

import pytest
import json
import csv
import os
import re
import sys
import tempfile
from unittest.mock import patch, MagicMock, call
from io import StringIO

# ── Importar módulos bajo test ────────────────────────────────────────────────
import link_auditor as la
import woo_content_generator as wg

# =============================================================================
# HELPERS
# =============================================================================

def make_producto(id=1, name="Granalladora GR-500", slug="granalladora-gr-500",
                  status="publish", description="", short_description="",
                  categories=None):
    return {
        "id": id, "name": name, "slug": slug, "status": status,
        "description": description, "short_description": short_description,
        "categories": categories or [{"id": 10, "name": "Granallado"}],
    }

def make_categoria(id=10, name="Granallado", slug="granallado", description=""):
    return {"id": id, "name": name, "slug": slug, "description": description, "count": 3}

def make_pagina_catalogo(id=100, slug="catalogo-granalladora-gr-500", title="Granalladora GR-500", content=""):
    return {
        "id": id,
        "slug": slug,
        "title": {"rendered": title},
        "content": {"rendered": content},
    }

# =============================================================================
# 1. INYECCIÓN DE LINKS — inyectar_link_en_html
# =============================================================================

class TestInyectarLinkEnHtml:

    def test_inyecta_link_basico(self):
        html = "<p>Usamos equipos de aspiración industrial.</p>"
        nuevo, ok = la.inyectar_link_en_html(html, "equipos de aspiración industrial",
                                              "https://rocaingenieria.com.ar/aspiracion/")
        assert ok
        assert 'href="https://rocaingenieria.com.ar/aspiracion/"' in nuevo

    def test_no_inyecta_si_ya_linkeado(self):
        html = '<p>Ver <a href="https://ejemplo.com">equipos de aspiración</a> aquí.</p>'
        nuevo, ok = la.inyectar_link_en_html(html, "equipos de aspiración",
                                              "https://otro.com/")
        # No debe crear link anidado
        assert nuevo.count("<a ") == 1

    def test_anchor_no_existe_en_html(self):
        html = "<p>Texto completamente diferente.</p>"
        nuevo, ok = la.inyectar_link_en_html(html, "texto que no existe", "https://x.com/")
        assert not ok
        assert nuevo == html

    def test_anchor_vacio(self):
        html = "<p>Texto normal.</p>"
        nuevo, ok = la.inyectar_link_en_html(html, "", "https://x.com/")
        # Anchor vacío no debe romper
        assert isinstance(nuevo, str)

    def test_html_vacio(self):
        nuevo, ok = la.inyectar_link_en_html("", "algo", "https://x.com/")
        assert not ok
        assert nuevo == ""

    def test_html_none(self):
        # Debe manejar None sin explotar
        try:
            nuevo, ok = la.inyectar_link_en_html(None, "algo", "https://x.com/")
            assert not ok
        except TypeError:
            pytest.fail("inyectar_link_en_html no maneja None")

    def test_solo_inyecta_primera_ocurrencia(self):
        html = "<p>turbinas industriales y más turbinas industriales.</p>"
        nuevo, ok = la.inyectar_link_en_html(html, "turbinas industriales", "https://x.com/")
        assert ok
        assert nuevo.count("<a ") == 1

    def test_url_con_caracteres_especiales(self):
        html = "<p>Ver especificaciones técnicas.</p>"
        url = "https://rocaingenieria.com.ar/catalogo/granalladora-gr-500/?ref=test&v=2"
        nuevo, ok = la.inyectar_link_en_html(html, "especificaciones técnicas", url)
        assert ok
        assert url in nuevo

    def test_anchor_con_html_entities(self):
        html = "<p>Presión máxima &amp; caudal nominal disponibles.</p>"
        nuevo, ok = la.inyectar_link_en_html(html, "caudal nominal", "https://x.com/")
        # No debe romper con entities
        assert isinstance(nuevo, str)

    def test_html_muy_largo(self):
        base = "<p>equipos de granallado </p>" * 1000
        nuevo, ok = la.inyectar_link_en_html(base, "equipos de granallado", "https://x.com/")
        assert ok
        assert nuevo.count("<a ") == 1  # solo primera ocurrencia

    def test_anchor_con_caracteres_regex_especiales(self):
        html = "<p>Presión (máx. 6 bar) disponible.</p>"
        nuevo, ok = la.inyectar_link_en_html(html, "Presión (máx. 6 bar)", "https://x.com/")
        assert isinstance(nuevo, str)  # no debe explotar con regex


# =============================================================================
# 2. EXTRAER LINKS DEL HTML
# =============================================================================

class TestExtraerLinks:

    def test_extrae_links_simples(self):
        html = '<p>Ver <a href="https://a.com">esto</a> y <a href="https://b.com">aquello</a>.</p>'
        links = la.extraer_links_del_html(html)
        assert "https://a.com" in links
        assert "https://b.com" in links

    def test_html_sin_links(self):
        links = la.extraer_links_del_html("<p>Sin links aquí.</p>")
        assert links == set()

    def test_html_vacio(self):
        links = la.extraer_links_del_html("")
        assert links == set()

    def test_html_none(self):
        links = la.extraer_links_del_html(None)
        assert links == set()

    def test_links_duplicados_devuelve_set(self):
        html = '<a href="https://a.com">1</a><a href="https://a.com">2</a>'
        links = la.extraer_links_del_html(html)
        assert len(links) == 1

    def test_comillas_simples(self):
        html = "<a href='https://a.com'>texto</a>"
        links = la.extraer_links_del_html(html)
        assert "https://a.com" in links


# =============================================================================
# 3. CONSTRUIR MAPA DEL SITIO
# =============================================================================

class TestConstruirMapaSitio:

    def test_mapa_basico(self):
        prods = [make_producto()]
        cats  = [make_categoria()]
        pags  = [make_pagina_catalogo()]
        mapa  = la.construir_mapa_sitio(prods, cats, pags)
        tipos = [m["tipo"] for m in mapa]
        assert "producto" in tipos
        assert "categoria" in tipos
        assert "catalogo" in tipos

    def test_listas_vacias(self):
        mapa = la.construir_mapa_sitio([], [], [])
        assert mapa == []

    def test_producto_sin_categorias(self):
        prod = {"id": 1, "name": "Prod", "slug": "prod", "status": "publish",
                "description": "", "short_description": "", "categories": []}
        mapa = la.construir_mapa_sitio([prod], [], [])
        assert mapa[0]["categorias"] == []

    def test_url_producto_correcta(self):
        prod = make_producto(slug="turbina-t200")
        mapa = la.construir_mapa_sitio([prod], [], [])
        item = next(m for m in mapa if m["tipo"] == "producto")
        assert "turbina-t200" in item["url"]

    def test_url_catalogo_sin_prefijo_doble(self):
        pag = make_pagina_catalogo(slug="catalogo-turbina-t200")
        mapa = la.construir_mapa_sitio([], [], [pag])
        item = next(m for m in mapa if m["tipo"] == "catalogo")
        # URL no debe tener /catalogo/catalogo/
        assert "/catalogo/catalogo/" not in item["url"]

    def test_producto_draft_incluido(self):
        prod = make_producto(status="draft")
        mapa = la.construir_mapa_sitio([prod], [], [])
        assert len([m for m in mapa if m["tipo"] == "producto"]) == 1

    def test_nombre_categoria_uncategorized_no_filtrado_aqui(self):
        # construir_mapa_sitio no filtra uncategorized, eso lo hace obtener_categorias
        cat = make_categoria(slug="uncategorized", name="Uncategorized")
        mapa = la.construir_mapa_sitio([], [cat], [])
        assert len([m for m in mapa if m["tipo"] == "categoria"]) == 1


# =============================================================================
# 4. MODO APPLY — lógica del CSV
# =============================================================================

class TestModoApply:

    def _escribir_csv(self, rows, path):
        campos = ["aprobado", "tipo_origen", "id_origen", "nombre_origen",
                  "url_origen", "anchor_text", "url_destino", "nombre_destino", "razon"]
        with open(path, "w", newline="", encoding="utf-8") as f:
            w = csv.DictWriter(f, fieldnames=campos)
            w.writeheader()
            w.writerows(rows)

    def test_csv_vacio_no_falla(self, tmp_path, monkeypatch):
        csv_path = tmp_path / "links.csv"
        self._escribir_csv([], csv_path)
        monkeypatch.setattr(la, "CSV_FILE", str(csv_path))
        monkeypatch.setattr(la, "obtener_productos_publicados", lambda: [])
        monkeypatch.setattr(la, "obtener_categorias", lambda: [])
        monkeypatch.setattr(la, "obtener_paginas_catalogo", lambda: [])
        # No debe explotar
        la.modo_apply()

    def test_solo_aprobado_si_se_aplica(self, tmp_path, monkeypatch):
        prod = make_producto(id=1, description="<p>equipos de granallado aquí.</p>")
        csv_path = tmp_path / "links.csv"
        self._escribir_csv([
            {"aprobado": "SI",  "tipo_origen": "producto", "id_origen": "1",
             "nombre_origen": "Prod A", "url_origen": "https://x.com/a",
             "anchor_text": "equipos de granallado", "url_destino": "https://x.com/b",
             "nombre_destino": "Cat B", "razon": "test"},
            {"aprobado": "NO",  "tipo_origen": "producto", "id_origen": "1",
             "nombre_origen": "Prod A", "url_origen": "https://x.com/a",
             "anchor_text": "aquí", "url_destino": "https://x.com/c",
             "nombre_destino": "Cat C", "razon": "test"},
        ], csv_path)

        llamadas = []
        def fake_put(endpoint, payload):
            llamadas.append(endpoint)
            r = MagicMock()
            r.status_code = 200
            return r

        monkeypatch.setattr(la, "CSV_FILE", str(csv_path))
        monkeypatch.setattr(la, "obtener_productos_publicados", lambda: [prod])
        monkeypatch.setattr(la, "obtener_categorias", lambda: [])
        monkeypatch.setattr(la, "obtener_paginas_catalogo", lambda: [])
        monkeypatch.setattr(la.wcapi, "put", fake_put)

        la.modo_apply()
        assert len(llamadas) == 1  # solo el SI

    def test_aprobado_mayusculas_minusculas(self, tmp_path, monkeypatch):
        prod = make_producto(id=1, description="<p>equipos industriales disponibles.</p>")
        csv_path = tmp_path / "links.csv"
        # "si" minúsculas también debe funcionar
        self._escribir_csv([
            {"aprobado": "si", "tipo_origen": "producto", "id_origen": "1",
             "nombre_origen": "Prod A", "url_origen": "https://x.com/a",
             "anchor_text": "equipos industriales", "url_destino": "https://x.com/b",
             "nombre_destino": "Cat B", "razon": "test"},
        ], csv_path)

        llamadas = []
        def fake_put(endpoint, payload):
            llamadas.append(endpoint)
            r = MagicMock(); r.status_code = 200; return r

        monkeypatch.setattr(la, "CSV_FILE", str(csv_path))
        monkeypatch.setattr(la, "obtener_productos_publicados", lambda: [prod])
        monkeypatch.setattr(la, "obtener_categorias", lambda: [])
        monkeypatch.setattr(la, "obtener_paginas_catalogo", lambda: [])
        monkeypatch.setattr(la.wcapi, "put", fake_put)

        la.modo_apply()
        assert len(llamadas) == 1

    def test_csv_no_existe(self, monkeypatch):
        monkeypatch.setattr(la, "CSV_FILE", "/tmp/no_existe_nunca.csv")
        # No debe explotar, solo loguear error
        la.modo_apply()

    def test_id_origen_no_existe_en_sitio(self, tmp_path, monkeypatch):
        csv_path = tmp_path / "links.csv"
        self._escribir_csv([
            {"aprobado": "SI", "tipo_origen": "producto", "id_origen": "9999",
             "nombre_origen": "No existe", "url_origen": "https://x.com/a",
             "anchor_text": "algo", "url_destino": "https://x.com/b",
             "nombre_destino": "Dest", "razon": "test"},
        ], csv_path)
        monkeypatch.setattr(la, "CSV_FILE", str(csv_path))
        monkeypatch.setattr(la, "obtener_productos_publicados", lambda: [])
        monkeypatch.setattr(la, "obtener_categorias", lambda: [])
        monkeypatch.setattr(la, "obtener_paginas_catalogo", lambda: [])
        # No debe explotar, solo loguear warning
        la.modo_apply()

    def test_id_origen_no_numerico(self, tmp_path, monkeypatch):
        csv_path = tmp_path / "links.csv"
        self._escribir_csv([
            {"aprobado": "SI", "tipo_origen": "producto", "id_origen": "abc",
             "nombre_origen": "Prod", "url_origen": "https://x.com/a",
             "anchor_text": "texto", "url_destino": "https://x.com/b",
             "nombre_destino": "Dest", "razon": "test"},
        ], csv_path)
        monkeypatch.setattr(la, "CSV_FILE", str(csv_path))
        monkeypatch.setattr(la, "obtener_productos_publicados", lambda: [])
        monkeypatch.setattr(la, "obtener_categorias", lambda: [])
        monkeypatch.setattr(la, "obtener_paginas_catalogo", lambda: [])
        # No debe explotar con ValueError al hacer int("abc")
        try:
            la.modo_apply()
        except ValueError:
            pytest.fail("modo_apply explota con id no numérico")

    def test_tipo_desconocido_no_explota(self, tmp_path, monkeypatch):
        prod = make_producto(id=1, description="<p>texto aquí.</p>")
        csv_path = tmp_path / "links.csv"
        self._escribir_csv([
            {"aprobado": "SI", "tipo_origen": "TIPO_RARO", "id_origen": "1",
             "nombre_origen": "Prod A", "url_origen": "https://x.com/a",
             "anchor_text": "texto", "url_destino": "https://x.com/b",
             "nombre_destino": "Dest", "razon": "test"},
        ], csv_path)
        monkeypatch.setattr(la, "CSV_FILE", str(csv_path))
        monkeypatch.setattr(la, "obtener_productos_publicados", lambda: [prod])
        monkeypatch.setattr(la, "obtener_categorias", lambda: [])
        monkeypatch.setattr(la, "obtener_paginas_catalogo", lambda: [])
        la.modo_apply()  # No debe explotar

    def test_csv_con_campos_faltantes(self, tmp_path, monkeypatch):
        csv_path = tmp_path / "links.csv"
        # CSV con columnas mínimas (campos faltantes)
        with open(csv_path, "w", newline="", encoding="utf-8") as f:
            f.write("aprobado,tipo_origen,id_origen\n")
            f.write("SI,producto,1\n")
        monkeypatch.setattr(la, "CSV_FILE", str(csv_path))
        monkeypatch.setattr(la, "obtener_productos_publicados", lambda: [])
        monkeypatch.setattr(la, "obtener_categorias", lambda: [])
        monkeypatch.setattr(la, "obtener_paginas_catalogo", lambda: [])
        try:
            la.modo_apply()
        except KeyError:
            pytest.fail("modo_apply explota con campos CSV faltantes")

    def test_dry_run_no_llama_api(self, tmp_path, monkeypatch):
        prod = make_producto(id=1, description="<p>equipos de granallado aquí.</p>")
        csv_path = tmp_path / "links.csv"
        self._escribir_csv([
            {"aprobado": "SI", "tipo_origen": "producto", "id_origen": "1",
             "nombre_origen": "Prod A", "url_origen": "https://x.com/a",
             "anchor_text": "equipos de granallado", "url_destino": "https://x.com/b",
             "nombre_destino": "Cat B", "razon": "test"},
        ], csv_path)

        llamadas = []
        def fake_put(endpoint, payload):
            llamadas.append(endpoint)
            r = MagicMock(); r.status_code = 200; return r

        monkeypatch.setattr(la, "CSV_FILE", str(csv_path))
        monkeypatch.setattr(la, "DRY_RUN", True)
        monkeypatch.setattr(la, "obtener_productos_publicados", lambda: [prod])
        monkeypatch.setattr(la, "obtener_categorias", lambda: [])
        monkeypatch.setattr(la, "obtener_paginas_catalogo", lambda: [])
        monkeypatch.setattr(la.wcapi, "put", fake_put)

        la.modo_apply()
        assert llamadas == []  # DRY_RUN no debe llamar API


# =============================================================================
# 5. PARSING DE OLLAMA — generar_con_ollama
# =============================================================================

class TestGenerarConOllama:

    def _mock_ollama(self, monkeypatch, respuesta_texto):
        def fake_post(url, json=None, timeout=None):
            r = MagicMock()
            r.status_code = 200
            r.json.return_value = {"response": respuesta_texto}
            r.raise_for_status = lambda: None
            return r
        monkeypatch.setattr("requests.post", fake_post)

    def test_json_limpio(self, monkeypatch):
        payload = {"focus_keyword": "granallado", "slug": "granalladora"}
        self._mock_ollama(monkeypatch, json.dumps(payload))
        result = la.generar_con_ollama("prompt test", intentos=1)
        assert result["focus_keyword"] == "granallado"

    def test_json_con_backticks(self, monkeypatch):
        payload = {"focus_keyword": "turbina", "slug": "turbina-t200"}
        texto = f"```json\n{json.dumps(payload)}\n```"
        self._mock_ollama(monkeypatch, texto)
        result = la.generar_con_ollama("prompt test", intentos=1)
        assert result is not None
        assert result.get("slug") == "turbina-t200"

    def test_json_invalido_reintenta(self, monkeypatch):
        intentos = []
        def fake_post(url, json=None, timeout=None):
            intentos.append(1)
            r = MagicMock()
            r.status_code = 200
            r.json.return_value = {"response": "esto no es json {{{"}
            r.raise_for_status = lambda: None
            return r
        monkeypatch.setattr("requests.post", fake_post)
        result = la.generar_con_ollama("prompt", intentos=3)
        assert result is None
        assert len(intentos) == 3

    def test_ollama_timeout(self, monkeypatch):
        import requests as req
        monkeypatch.setattr(req, "post", MagicMock(side_effect=req.exceptions.Timeout))
        result = la.generar_con_ollama("prompt", intentos=2)
        assert result is None

    def test_ollama_connection_error(self, monkeypatch):
        import requests as req
        monkeypatch.setattr(req, "post", MagicMock(side_effect=req.exceptions.ConnectionError))
        result = la.generar_con_ollama("prompt", intentos=1)
        assert result is None

    def test_respuesta_vacia(self, monkeypatch):
        self._mock_ollama(monkeypatch, "")
        result = la.generar_con_ollama("prompt", intentos=1)
        assert result is None

    def test_json_con_texto_previo(self, monkeypatch):
        payload = {"focus_keyword": "aspiracion", "slug": "aspirador"}
        texto = f"Claro, aquí está el resultado:\n{json.dumps(payload)}"
        self._mock_ollama(monkeypatch, texto)
        # Si el modelo agrega texto previo, debe intentar extraer el JSON igual
        result = la.generar_con_ollama("prompt", intentos=1)
        # Puede fallar — lo importante es que no explote con excepción no controlada
        assert result is None or isinstance(result, dict)

    def test_json_anidado_no_rompe(self, monkeypatch):
        payload = {"links_propuestos": [
            {"anchor_text": "granallado", "url_destino": "https://x.com",
             "nombre_destino": "Cat", "razon": "test"}
        ]}
        self._mock_ollama(monkeypatch, json.dumps(payload))
        result = la.generar_con_ollama("prompt", intentos=1)
        assert result is not None
        assert "links_propuestos" in result


# =============================================================================
# 6. CONSTRUIR CATÁLOGO DE LINKS — woo_content_generator
# =============================================================================

class TestConstruirCatalogo:

    def test_catalogo_basico(self):
        prods = [make_producto(slug="granalladora-gr-500", status="publish")]
        cats  = [make_categoria(slug="granallado")]
        result = wg.construir_catalogo(prods, cats)
        assert "granalladora-gr-500" in result
        assert "granallado" in result

    def test_solo_publicados_en_catalogo(self):
        prods = [
            make_producto(id=1, slug="prod-a", status="publish"),
            make_producto(id=2, slug="prod-b", status="draft"),
        ]
        result = wg.construir_catalogo(prods, [])
        assert "prod-a" in result
        assert "prod-b" not in result

    def test_catalogo_vacio(self):
        result = wg.construir_catalogo([], [])
        assert isinstance(result, str)

    def test_slugs_con_caracteres_especiales(self):
        prods = [make_producto(slug="turbina-5-bar-alta-presión", status="publish")]
        result = wg.construir_catalogo(prods, [])
        assert "turbina-5-bar" in result

    def test_url_ficha_tecnica_incluida(self):
        prods = [make_producto(slug="granalladora-gr-500", status="publish")]
        result = wg.construir_catalogo(prods, [])
        assert "/catalogo/granalladora-gr-500/" in result


# =============================================================================
# 7. PROMPTS — estructura y campos requeridos
# =============================================================================

class TestPrompts:

    def _catalogo_fake(self):
        return wg.construir_catalogo(
            [make_producto(slug="turbina-t200", status="publish")],
            [make_categoria(slug="turbinas")]
        )

    def test_prompt_producto_contiene_nombre(self):
        prompt = wg.prompt_producto("Granalladora GR-500", "Granallado",
                                    self._catalogo_fake())
        assert "Granalladora GR-500" in prompt

    def test_prompt_producto_contiene_json_fields(self):
        prompt = wg.prompt_producto("Turbina T200", "Turbinas", self._catalogo_fake())
        for campo in ["focus_keyword", "slug", "seo_title", "meta_descripcion",
                      "descripcion_corta", "descripcion_larga", "imagenes"]:
            assert campo in prompt

    def test_prompt_producto_sin_descripcion_existente(self):
        prompt = wg.prompt_producto("Prod", "Cat", self._catalogo_fake())
        assert isinstance(prompt, str)
        assert len(prompt) > 100

    def test_prompt_producto_con_descripcion_existente(self):
        prompt = wg.prompt_producto("Prod", "Cat", self._catalogo_fake(),
                                    "descripción existente de prueba")
        assert "descripción existente de prueba" in prompt

    def test_prompt_catalogo_contiene_geo_keywords(self):
        prompt = wg.prompt_pagina_catalogo(
            "Granalladora GR-500", "granalladora-gr-500", "Granallado",
            self._catalogo_fake(), "https://rocaingenieria.com.ar/producto/granalladora-gr-500/"
        )
        assert "GEO" in prompt or "LLM" in prompt or "IA" in prompt

    def test_prompt_catalogo_contiene_faq(self):
        prompt = wg.prompt_pagina_catalogo(
            "Granalladora", "granalladora", "Granallado",
            self._catalogo_fake(), "https://x.com/"
        )
        assert "FAQ" in prompt.upper() or "preguntas" in prompt.lower()

    def test_prompt_catalogo_contiene_dl_dt_dd(self):
        prompt = wg.prompt_pagina_catalogo(
            "Granalladora", "granalladora", "Granallado",
            self._catalogo_fake(), "https://x.com/"
        )
        assert "<dl>" in prompt or "dl/dt/dd" in prompt

    def test_prompt_analizar_links_contiene_urls(self):
        prod = make_producto()
        mapa = la.construir_mapa_sitio([prod], [make_categoria()], [])
        item = mapa[0]
        prompt = la.prompt_analizar_links(item, mapa)
        assert item["url"] in prompt

    def test_prompt_analizar_links_lista_destinos(self):
        prods = [make_producto(id=1), make_producto(id=2, name="Turbina T200",
                                                    slug="turbina-t200")]
        mapa  = la.construir_mapa_sitio(prods, [], [])
        item  = mapa[0]
        prompt = la.prompt_analizar_links(item, mapa)
        # El destino no debe ser el propio item
        assert item["url"] not in prompt.split("DESTINOS")[1] if "DESTINOS" in prompt else True


# =============================================================================
# 8. STRESS — volumen alto de datos
# =============================================================================

class TestStress:

    def test_mapa_con_500_productos(self):
        prods = [make_producto(id=i, name=f"Producto {i}", slug=f"producto-{i}")
                 for i in range(500)]
        cats  = [make_categoria(id=i+1000, name=f"Cat {i}", slug=f"cat-{i}")
                 for i in range(50)]
        mapa  = la.construir_mapa_sitio(prods, cats, [])
        assert len(mapa) == 550

    def test_inyectar_link_en_html_1mb(self):
        # HTML de 1MB
        html = ("<p>equipos de granallado industriales. " + "X " * 5000 + "</p>") * 20
        nuevo, ok = la.inyectar_link_en_html(html, "equipos de granallado industriales",
                                             "https://x.com/")
        assert ok
        assert nuevo.count("<a ") == 1

    def test_catalogo_con_200_productos(self):
        prods = [make_producto(id=i, slug=f"prod-{i}", status="publish")
                 for i in range(200)]
        result = wg.construir_catalogo(prods, [])
        assert isinstance(result, str)
        assert len(result) > 1000

    def test_csv_con_1000_filas_apply(self, tmp_path, monkeypatch):
        prod = make_producto(id=1, description="<p>" + " texto_unico_{} ".format(0) * 1 + "</p>")
        # Generar descripción con 1000 anchor texts únicos
        desc = "<p>" + " ".join(f"item_{i}" for i in range(1000)) + "</p>"
        prod["description"] = desc

        rows = [
            {"aprobado": "SI", "tipo_origen": "producto", "id_origen": "1",
             "nombre_origen": "Prod", "url_origen": "https://x.com/a",
             "anchor_text": f"item_{i}", "url_destino": "https://x.com/b",
             "nombre_destino": "Dest", "razon": "test"}
            for i in range(1000)
        ]
        csv_path = tmp_path / "links.csv"
        campos = ["aprobado", "tipo_origen", "id_origen", "nombre_origen",
                  "url_origen", "anchor_text", "url_destino", "nombre_destino", "razon"]
        with open(csv_path, "w", newline="", encoding="utf-8") as f:
            w = csv.DictWriter(f, fieldnames=campos)
            w.writeheader()
            w.writerows(rows)

        llamadas = []
        def fake_put(endpoint, payload):
            llamadas.append(1)
            r = MagicMock(); r.status_code = 200; return r

        monkeypatch.setattr(la, "CSV_FILE", str(csv_path))
        monkeypatch.setattr(la, "DELAY", 0)
        monkeypatch.setattr(la, "obtener_productos_publicados", lambda: [prod])
        monkeypatch.setattr(la, "obtener_categorias", lambda: [])
        monkeypatch.setattr(la, "obtener_paginas_catalogo", lambda: [])
        monkeypatch.setattr(la.wcapi, "put", fake_put)

        la.modo_apply()
        # Debe completar sin explotar


# =============================================================================
# 9. EDGE CASES — datos raros del mundo real
# =============================================================================

class TestEdgeCases:

    def test_producto_sin_slug(self):
        prod = make_producto(slug="")
        mapa = la.construir_mapa_sitio([prod], [], [])
        assert mapa[0]["slug"] == ""

    def test_producto_nombre_con_html(self):
        prod = make_producto(name="Granalladora <b>GR-500</b> & Aspirador")
        mapa = la.construir_mapa_sitio([prod], [], [])
        assert mapa[0]["nombre"] == "Granalladora <b>GR-500</b> & Aspirador"

    def test_descripcion_con_unicode_mixto(self):
        html = "<p>Equipos de granallación — alta eficiencia. Ø 500mm. 6÷8 bar.</p>"
        nuevo, ok = la.inyectar_link_en_html(html, "alta eficiencia", "https://x.com/")
        assert ok

    def test_url_destino_con_parametros(self):
        html = "<p>Ver especificaciones aquí.</p>"
        url = "https://rocaingenieria.com.ar/catalogo/prod/?utm_source=interno&ref=123"
        nuevo, ok = la.inyectar_link_en_html(html, "especificaciones", url)
        assert ok
        assert url in nuevo

    def test_anchor_es_toda_la_frase_del_html(self):
        html = "<p>Granalladora industrial de alto rendimiento.</p>"
        nuevo, ok = la.inyectar_link_en_html(
            html, "Granalladora industrial de alto rendimiento.", "https://x.com/")
        assert ok

    def test_producto_con_multiples_categorias(self):
        prod = make_producto(categories=[
            {"id": 1, "name": "Granallado"},
            {"id": 2, "name": "Automatización"},
            {"id": 3, "name": "Industrial"},
        ])
        mapa = la.construir_mapa_sitio([prod], [], [])
        assert len(mapa[0]["categorias"]) == 3

    def test_categoria_con_descripcion_con_links(self):
        cat = make_categoria(description='<p>Ver <a href="https://x.com">esto</a>.</p>')
        mapa = la.construir_mapa_sitio([], [cat], [])
        assert "https://x.com" in mapa[0]["links_actuales"]

    def test_slug_con_numeros_y_guiones(self):
        prod = make_producto(slug="turbina-5-bar-3000-rpm-v2")
        mapa = la.construir_mapa_sitio([prod], [], [])
        assert "turbina-5-bar-3000-rpm-v2" in mapa[0]["url"]

    def test_inyectar_link_no_rompe_atributos_existentes(self):
        html = '<p class="intro">equipos industriales disponibles hoy.</p>'
        nuevo, ok = la.inyectar_link_en_html(html, "equipos industriales", "https://x.com/")
        assert ok
        assert 'class="intro"' in nuevo  # no debe romper atributos existentes

    def test_wp_auth_formato_correcto(self):
        import base64
        la.WP_USER = "admin"
        la.WP_PASS = "xxxx xxxx xxxx"
        headers = la.wp_auth()
        assert "Authorization" in headers
        assert headers["Authorization"].startswith("Basic ")
        token = headers["Authorization"].split(" ")[1]
        decoded = base64.b64decode(token).decode()
        assert "admin" in decoded


# =============================================================================
# 10. MODO ANALYZE — mock completo sin red
# =============================================================================

class TestModoAnalyze:

    def test_analyze_sin_items_genera_csv_vacio(self, tmp_path, monkeypatch):
        monkeypatch.setattr(la, "CSV_FILE", str(tmp_path / "links.csv"))
        monkeypatch.setattr(la, "obtener_productos_publicados", lambda: [])
        monkeypatch.setattr(la, "obtener_categorias", lambda: [])
        monkeypatch.setattr(la, "obtener_paginas_catalogo", lambda: [])
        la.modo_analyze()
        # Sin items no se crea el CSV (no hay nada que proponer)
        assert not os.path.exists(str(tmp_path / "links.csv"))

    def test_analyze_con_un_producto_genera_csv(self, tmp_path, monkeypatch):
        prod = make_producto()
        cat  = make_categoria()
        csv_path = str(tmp_path / "links.csv")

        monkeypatch.setattr(la, "CSV_FILE", csv_path)
        monkeypatch.setattr(la, "DELAY", 0)
        monkeypatch.setattr(la, "obtener_productos_publicados", lambda: [prod])
        monkeypatch.setattr(la, "obtener_categorias", lambda: [cat])
        monkeypatch.setattr(la, "obtener_paginas_catalogo", lambda: [])

        # Mock Ollama que devuelve 1 link propuesto
        respuesta = json.dumps({"links_propuestos": [{
            "anchor_text": "Granallado industrial",
            "url_destino": "https://rocaingenieria.com.ar/product-category/granallado/",
            "nombre_destino": "Granallado",
            "razon": "Misma categoría"
        }]})

        def fake_post(url, json=None, timeout=None):
            r = MagicMock()
            r.status_code = 200
            r.json.return_value = {"response": respuesta}
            r.raise_for_status = lambda: None
            return r

        monkeypatch.setattr("requests.post", fake_post)
        la.modo_analyze()

        assert os.path.exists(csv_path)
        with open(csv_path, encoding="utf-8") as f:
            rows = list(csv.DictReader(f))
        assert len(rows) >= 1
        assert rows[0]["aprobado"] == "SI"

    def test_analyze_ollama_falla_no_genera_csv(self, tmp_path, monkeypatch):
        prod = make_producto()
        csv_path = str(tmp_path / "links.csv")

        monkeypatch.setattr(la, "CSV_FILE", csv_path)
        monkeypatch.setattr(la, "DELAY", 0)
        monkeypatch.setattr(la, "obtener_productos_publicados", lambda: [prod])
        monkeypatch.setattr(la, "obtener_categorias", lambda: [])
        monkeypatch.setattr(la, "obtener_paginas_catalogo", lambda: [])

        import requests as req
        monkeypatch.setattr(req, "post", MagicMock(side_effect=req.exceptions.ConnectionError))

        la.modo_analyze()
        # Sin propuestas exitosas, no debe crear CSV
        assert not os.path.exists(csv_path)


# =============================================================================
# MAIN
# =============================================================================

if __name__ == "__main__":
    pytest.main([__file__, "-v", "--tb=short"])
