# Roca Ingeniería — SEO Tools

Scripts para generación automática de contenido SEO y linking interno en WooCommerce.

## Archivos

### `woo_content_generator.py`
Genera contenido SEO para productos en draft y páginas de catálogo técnico.

- Lee productos en estado **draft** desde WooCommerce
- Genera con Ollama (local): descripción corta, larga, slug, SEO title, meta description, focus keyword, sugerencias de imágenes
- Crea páginas de catálogo en `/catalogo/nombre-producto/` con contenido optimizado para GEO/AIO (LLMs)
- Sube Yoast SEO meta via WordPress REST API
- Inyecta links internos automáticamente

### `link_auditor.py`
Audita y aplica links internos en el sitio.

```bash
python link_auditor.py analyze   # genera links_propuestos.csv
python link_auditor.py apply     # aplica los links aprobados
```

- `analyze`: lee todos los productos, categorías y páginas de catálogo, propone links faltantes, genera CSV para revisión
- `apply`: aplica solo las filas con `aprobado=SI` del CSV

### `test_suite.py`
72 tests unitarios y de stress. Correr antes de usar en producción:

```bash
pip install pytest requests woocommerce
python -m pytest test_suite.py -v
```

## Setup

Ver `SETUP.md` para instrucciones paso a paso:
1. Instalar Ollama + modelo (`ollama pull mistral`)
2. Generar API keys en WooCommerce → Ajustes → Avanzado → REST API
3. Instalar plugin `wp-rest-yoast-meta`
4. Crear Application Password en WordPress → Usuarios → Tu perfil
5. Completar credenciales en los scripts
6. Correr con `DRY_RUN = True` primero

## Requisitos

```
pip install requests woocommerce
```

- Python 3.10+
- Ollama corriendo localmente (`ollama serve`)
- WordPress con WooCommerce + Yoast SEO
- Plugin: [WP REST API – Yoast SEO](https://wordpress.org/plugins/wp-rest-yoast-meta/)

## Flujo nocturno recomendado

1. Crear productos en WooCommerce como **borrador** (pueden estar vacíos)
2. Correr `woo_content_generator.py` — genera contenido y páginas de catálogo
3. Revisar drafts en wp-admin → aprobar y publicar lo que está bien
4. Periódicamente correr `link_auditor.py analyze` → revisar CSV → `link_auditor.py apply`
