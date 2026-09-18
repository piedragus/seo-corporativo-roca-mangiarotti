# Royal MCP — conexión a rocaingenieria.com.ar

## Qué es

Royal MCP es un plugin de WordPress que expone un servidor MCP (Model Context Protocol)
sobre el propio sitio, en `/wp-json/royal-mcp/v1/mcp`. Permite que un agente de Claude Code
opere directamente sobre WordPress/WooCommerce del sitio (leer/gestionar contenido, productos,
etc.) sin pasar por la REST API "a mano" — el plugin decide qué tools expone.

- **Endpoint**: `https://rocaingenieria.com.ar/wp-json/royal-mcp/v1/mcp`
- **Transporte**: HTTP
- **Autenticación**: por header `X-Royal-MCP-API-Key: <api-key>`

## Por qué se registró desde esta máquina (Franky) y no corre en la nube de Claude

Según lo indicado por Agustín: las conexiones salientes desde los servidores de Claude.ai
(la nube de Anthropic) están siendo bloqueadas por el hosting de LatinCloud, mientras que
desde una IP normal (como la de esta PC, Franky) sí funciona. Por eso el servidor se registró
a nivel **local** de esta máquina (`--scope local` de `claude mcp add`), no a nivel de
proyecto — si se registrara a nivel de proyecto, la sesión de Claude Code en cualquier otra
máquina/nube intentaría conectarse también y fallaría por el mismo bloqueo. No se verificó
de forma independiente el motivo exacto del bloqueo del lado de LatinCloud; se documenta acá
tal como fue reportado.

## Cómo se registró

```bash
claude mcp add --transport http royal-mcp \
  https://rocaingenieria.com.ar/wp-json/royal-mcp/v1/mcp \
  --header "X-Royal-MCP-API-Key: <api-key>" \
  --scope local
```

**La API key NO está en ningún archivo de este repo.** Vive en dos lugares, ambos fuera del
control de versiones:

1. La configuración local de Claude Code (`~/.claude.json`, sección del proyecto
   `/home/aroca/roca-ingenieria-seo-tools`) — es lo que usa `claude mcp add --scope local`.
2. `pass show rocaingenieria/royal-mcp-api-key` — copia de respaldo en el password store.

Si hay que re-registrar el servidor en otra máquina o después de perder la config local,
sacar la key de ahí, nunca pegarla en un archivo del repo.

## Verificación

Conectividad confirmada con `claude mcp list`:

```
royal-mcp: https://rocaingenieria.com.ar/wp-json/royal-mcp/v1/mcp (HTTP) - ✔ Connected
```

**Nota operativa importante, confirmada en vivo (18/9)**: ni la sesión que registró el
servidor ni una sesión/subagente nuevo arrancado de cero en este mismo repo ven las tools de
`royal-mcp` a través de `ToolSearch` — el índice de deferred-tools de este harness solo cubre
los conectores tipo claude.ai (Gmail, WordPress.com, etc.), no los servidores MCP registrados
por `claude mcp add`. `claude mcp list` sí confirma la conexión (`✔ Connected`), pero para
**usar** las tools hay que hablarle directo al endpoint por HTTP con JSON-RPC 2.0 (`curl`),
mandando el header `X-Royal-MCP-API-Key` con la key (ver `pass show
rocaingenieria/royal-mcp-api-key`):

```bash
curl -s https://rocaingenieria.com.ar/wp-json/royal-mcp/v1/mcp \
  -H "Content-Type: application/json" \
  -H "X-Royal-MCP-API-Key: <api-key>" \
  -d '{"jsonrpc":"2.0","id":1,"method":"tools/call","params":{"name":"wp_get_posts","arguments":{"per_page":5,"status":"publish"}}}'
```

## Tools que expone (209 en total)

Servidor: **Royal MCP WordPress v1.5.2**. Categorías principales:

- **WordPress core / contenido**: posts, páginas, media, taxonomías, comentarios, usuarios,
  opciones, menús, plugins/temas, widgets, permalinks, revisiones (`wp_get_posts`,
  `wp_create_post`, `wp_upload_media`, `wp_get_menus`, `wp_update_custom_css`,
  `wp_get_post_revisions`, `wp_publish_and_promote`, etc.)
- **SEO** (auto-detecta Yoast/RankMath/AIOSEO/SEOPress): `wp_get_seo_meta`,
  `wp_update_seo_meta`, `seo_audit_meta_tags`, `yoast_get_schema`, `yoast_get_redirects`, etc.
- **WooCommerce**: `wc_get_products`, `wc_create_product`, `wc_get_orders`,
  `wc_update_order_status`, `wc_get_store_stats`, `wc_get_product_variations`,
  `wc_create_coupon`, etc.
- **Elementor** y **Divi**: clonar/editar páginas, reemplazar texto/imágenes, importar
  templates, reconstruir contenido (`elementor_clone_page`, `divi_replace_text`, etc.)
- **ACF**: `acf_get_field(s)`, `acf_update_field`, `acf_get_field_groups`.
- **Seguridad/infraestructura**: GoPatrol (escaneo de vulnerabilidades, IPs bloqueadas),
  SiteVault y UpdraftPlus (backups), Royal AI Firewall, Solid Security, Duplicator.
- **Otros plugins**: Royal Ledger (costos/renovaciones), ForgeCache, Royal Links,
  Redirection, WPForms, Contact Form 7, MonsterInsights, W3 Total Cache, BuddyPress.

Listado completo con descripciones de cada una de las 209 tools: pedir a la sesión de Code
que lo regenere con `tools/list` (JSON-RPC) si hace falta el detalle completo — no se pegó
entero acá por tamaño.

## Prueba de punta a punta (18/9/2026)

Llamada real a `wp_get_posts` (`per_page=5, status=publish`):

```json
[
  {
    "id": 1540,
    "title": "Post ejemplo 1",
    "status": "publish",
    "type": "post",
    "url": "https://rocaingenieria.com.ar/post-1/",
    "date": "2021-12-20 09:34:35"
  }
]
```

Solo hay 1 post publicado (parece un post de prueba/ejemplo, no contenido real todavía) — pero
la conexión, autenticación y el roundtrip completo funcionan correctamente.
