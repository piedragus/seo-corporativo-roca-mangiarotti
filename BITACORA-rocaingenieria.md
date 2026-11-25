# Bitácora técnica — rocaingenieria.com.ar

Este repo ya tenía las herramientas SEO de rocaingenieria.com.ar (`woo_content_generator.py`, `link_auditor.py`) y el roadmap del grupo, así que los scripts del servidor quedan acá, en `tools/`. El modelo que se siguió fue la ronda SEO de Línea Magna del mismo día (`piedragus/linea-magna`, commits 395e4a0 y 5c31e8e).

## 2026-11-24 — Visibilidad en Google (pedido de Agustín: "hacer todo lo posible sin que tenga que intervenir")

Objetivo de negocio: vender mucha granalla de acero (esférica y angular).
Reglas que se respetaron:
- precio USD 1,35/kg + IVA, con entrega en AMBA o por el transporte del cliente;
- nada de tramos de descuento, solo "consultá descuentos por volumen";
- nunca se menciona la granalla angular tipo E;
- no se inventaron calibres ni datos;
- no se tocaron precios, stock, usuarios ni credenciales.

### Backup y cómo revertir

Todo está en `/www/rocaingenieria.com.ar/backups-roca/20261124/`, fuera de `htdocs` y con permisos 700.

| Archivo | Qué es |
|---|---|
| `db-antes.sql.gz` | Dump completo de la base antes de tocar nada (`wp db export --ssl=0`). |
| `htaccess.bak`, `robots.txt.bak`, `llms.txt.bak` | Originales. |
| `mu-plugins-antes/` | Copia de los mu-plugins que ya había. |
| `seo_backup.json` | Valores anteriores de todo lo que tocó `seo_roca_20261124.php`: metas, contenido, descripciones de categoría, ALT, opciones de Yoast y widgets. |
| `links_backup.json` | Contenido anterior de los 3 posts/layouts tocados por `links_redireccion_20261124.php`. |
| `borradores.json` | IDs de los borradores creados. |
| `mu-plugins-nuevos/` | Copia de los mu-plugins nuevos. |

Para revertir, parado en `/www/rocaingenieria.com.ar/htdocs`:

1. **Base de datos (metas, textos, ALT, Yoast, widgets):** `wp eval-file ../backups-roca/20261124/revertir_seo_roca_20261124.php aplicar`. Sin `aplicar` solo simula.
2. **Links:** restaurar `post_content` de los IDs de `links_backup.json`. Son 25861, la plantilla de producto de Divi; 26723; y 26773.
3. **mu-plugins:** borrar `wp-content/mu-plugins/roca-seo-schema.php`, `roca-seo-locale.php`, `roca-velocidad.php`, `roca-velocidad-header.php` y `roca-feed-google.php`. Ojo, tardan 1 o 2 minutos en dejar de verse en todos los nodos.
4. **.htaccess:** `cp ../backups-roca/20261124/htaccess.bak .htaccess`. También se puede borrar solo el bloque `# BEGIN Roca SEO … # END Roca SEO`.
5. **llms.txt:** `cp ../backups-roca/20261124/llms.txt.bak llms.txt`.
6. **IndexNow:** borrar `38879130abf5195006ae8fc21ed981e2.txt`.
7. **Borradores:** `wp post delete 26985 26986`.
8. Purgar WP Rocket y la caché de Divi (`ET_Core_PageResource::remove_static_resources('all','all',true)`).

### 1. Velocidad

La API de PageSpeed no se usó: la cuota suele estar agotada. Se midió con Lighthouse 12 local en celular simulado, 2 corridas por página. Las mediciones de laboratorio varían entre corridas.

**Diagnóstico:**
- WP Rocket ya estaba bien configurado: minify, defer, delay JS, RUCSS, lazyload y precarga. Las páginas cacheadas responden en unos 0,06 s y las no cacheadas en 4 a 7 s.
- El problema grave era el **CLS (salto de contenido), de 0,96 a 1,08** en `/granalla-de-acero/`, en la ficha de granalla y en categorías. Lo normal es menos de 0,1.
- **Causa:** el "Critical CSS" de Divi mandaba al pie de la página el CSS del encabezado del Theme Builder. Ese CSS es el que lo oculta en el celular y le da `margin-top`. Al cargar tarde, todo el contenido saltaba.

**Cambios:**
- **`roca-velocidad.php`:**
  - `et_pb_critical_css_enabled` en false, sin tocar la base; el CSS deja de quedar "diferido";
  - el logo del encabezado queda fuera del lazyload de WP Rocket. En la ficha de producto era el LCP y se cargaba en diferido.
  - Solo con esto el CSS seguía impreso al final del `<body>`, porque Divi tiene "Static CSS File Generation" en off. El CLS bajó apenas, de 0,96 a 0,81.
- **`roca-velocidad-header.php`:** copia en el `<head>` las reglas `*_tb_header` del encabezado del Theme Builder (layout 773). La copia sale de ese bloque de CSS del pie con un script de Python que separa las reglas, incluidas las de `@media`, y guarda solo las que tienen `_tb_header` en el selector.
  - Las reglas de Divi siguen saliendo al final y, como son iguales y posteriores, el resultado final no cambia: solo se evita el salto.
  - **Si alguien edita el encabezado en Divi**, hay que regenerar la copia o borrar el archivo. Si queda vieja, solo afecta el primer pintado.
- **Se probó y se descartó** forzar "Static CSS File Generation" en on (`roca-velocidad-css.php`, por filtro, sin tocar la base). Divi regeneró `et-cache/global/et-divi-customizer-global.min.css` incompleto: 237 bytes contra 9,2 KB inline. Las páginas regeneradas en ese rato perdieron el CSS del personalizador y, por ejemplo, apareció el sidebar "Categorías" en `/granalla-de-acero/`.
  - Se borró a los 4 minutos (00:46 a 00:50 UTC) y se limpiaron las cachés de Divi y de WP Rocket. Se verificó con captura que la página volvió a verse como antes.
  - No reintentar sin revisar primero por qué Divi genera mal ese archivo en este hosting.

**Lighthouse móvil:** ver la tabla "Mediciones" al final.

### 2. Redirecciones, 404, sitemap y robots

**.htaccess:** se agregó un bloque propio `# BEGIN Roca SEO` arriba del de WP Rocket. WP Rocket no lo pisa.
- `https://www.` → `https://` sin www lo hace ahora Apache, en 0,1 s. Antes lo hacía WordPress y tardaba 6 s.
- `http://www.` hace 2 saltos: el 308 del proxy de LatinCloud y después el 301. No se puede bajar a uno desde el sitio.
- Las URLs sin barra final (`/granalla-de-acero`) respondían 200 desde la caché de WP Rocket, como contenido duplicado. Ahora hacen 301 a la versión con barra. La regla aplica solo cuando existe esa carpeta de caché.
- Los cambios de `.htaccess` tardaron alrededor de 1 minuto en verse.

**Sitemap:**
- se sacó `/shop/` (hace 301 a `/productos/`);
- `/presupuesto/` quedó en noindex: es el carrito de cotización de YITH, sin contenido;
- la categoría Granalla tiene canonical a la página madre (ver punto 4).

**Crawl de links internos:**
- 52 páginas del sitemap y 211 links internos, sin ningún 404;
- 3 links pasaban por un 301 y ahora apuntan directo con `links_redireccion_20261124.php`:
  - el botón "Volver a la tienda" de la plantilla de producto (`/tienda` → `/productos/`), presente en 28 páginas;
  - `/producto/cartucho-filtrante/`;
  - `/servicio-tecnico-2/`.

Revisado y sin cambios: `robots.txt`; la búsqueda (`noindex`); tags de producto (`noindex`); adjuntos y autor (desactivados); 404 reales; canonical en todas las páginas.

### 3. Schema (sin duplicar Yoast)

Mu-plugin `roca-seo-schema.php`.

**Organization:** el de Yoast pasa a `["Organization","LocalBusiness"]`. Se usaron solo datos visibles en la home y en el mapa:
- dirección Dr. Enrique Eizaguirre 737, B1754FLA San Justo;
- coordenadas del mapa embebido;
- teléfono +54 9 11 5005-2904;
- email;
- `areaServed` AMBA y Argentina;
- `contactPoint` de ventas con el WhatsApp de granalla, +54 9 11 3901-6625.

No se cargaron horarios: el sitio no dice qué días.

**Product:** WooCommerce no lo emitía, porque el cuerpo de la ficha es de Divi. Ahora hay uno solo, con `@id …#product`, en las dos fichas de granalla:
- `Offer` de USD 1,35;
- `UnitPriceSpecification` por kg (`KGM`), con `valueAddedTaxIncluded=false`;
- InStock y New;
- `areaServed` AMBA.

Para que el precio del schema coincida con lo visible, se agregó una línea de precio en la descripción de las dos fichas: "Precio: USD 1,35 por kg + IVA. Entrega en AMBA o despacho por el transporte que indique el cliente. Consultá descuentos por volumen. Cotizar por WhatsApp". Es el mismo dato que ya estaba en `/granalla-de-acero/`.

No se agregó Product en el resto de los productos: no tienen precio, y un Product sin `offers` da error en Search Console.

**Idioma:**
- `<html lang="es-AR">`;
- `og:locale` es `es_AR` (antes `es_ES`).

El `og:locale` va en un mu-plugin aparte, `roca-seo-locale.php`, porque `wpseo_locale` no alcanza y el PHP editado no se recarga.

### 4. Títulos y meta descripciones

El script es `seo_roca_20261124.php`. Antes había 10 páginas del sitemap sin meta descripción; ahora hay 0. Antes había 1 título duplicado; ahora hay 0.

**Home:**
- título "Granalla de acero y granalladoras en Buenos Aires | Roca Ingeniería" (antes "Roca Ingeniería - Soluciones industriales");
- meta con San Justo, granalla esférica y angular, AMBA.

**`/granalla-de-acero/`:**
- título "Granalla de acero esférica y angular: precio y stock en AMBA";
- meta con el precio y "Consultá descuentos por volumen" (antes 177 caracteres, cortada por Google).

**Fichas de granalla:**
- títulos con las granulometrías que ya figuran en la ficha (S-110 a S-660 y G-16 a G-120);
- metas con precio y AMBA;
- la meta vieja de la esférica decía "S-70 a S-780", que no coincide con la ficha, y se reemplazó.

**Categoría Granalla (`/categoria-producto/granalla-de-acero/`):**
- título "Comprar granalla de acero esférica y angular | Roca Ingeniería";
- canonical a `/granalla-de-acero/`, para que no compitan por la misma búsqueda.

**Otras:**
- metas nuevas en Nosotros, Políticas de calidad, Repuestos (página), Sistema de movimiento de materiales, Goma 6 mm, Mangas filtrantes, Tambor rotativo y Tolva 250;
- títulos arreglados en "Filtros de mangas" y "Granalladora para estructuras", que repetían "Roca Ingeniería" dos veces, y en Productos.

Yoast guarda las metas de categorías en indexables. Hay que disparar `edited_term` después de tocar `wpseo_taxonomy_meta`, si no, no se ven.

### 5. Enlazado interno hacia `/granalla-de-acero/`

Se agregó una línea con link al final de la descripción de:
- 8 equipos: T4, ganchera, tumblast, granalladora de estructuras, cuartos, cabinas por succión, tolva 250 y tambor rotativo;
- 5 categorías: Abrasivos, Granalladoras, Aire comprimido, Turbina y Repuestos de turbinas.

El texto es "¿Necesitás abrasivo para tu equipo? También vendemos granalla de acero esférica y angular en stock, con entrega en AMBA."

En Abrasivos el texto es distinto y nombra la guía. Las descripciones de categoría las inserta por JS un snippet del sitio, con el texto escapado en JSON. Están, aunque un `grep` del HTML no las encuentre.

### 6. Granalla angular tipo E (regla de negocio)

Estaba publicada:
- en la tabla de durezas de `/granalla-de-acero/`, como "E > 697 Hv (> 60 HRC)", más la frase "E permanece angular…";
- en la ficha angular, como "GH (>60 HRC)" y "(GH, GL o GP)".

Se sacó y se dejó "Otras durezas, bajo consulta.", igual que en el folleto técnico PDF, que ya no la nombraba.

### 7. Imágenes, Open Graph y widgets

- **ALT:** se cargó el texto alternativo en 37 imágenes de producto que no lo tenían. Las de granalla tienen un ALT descriptivo; el resto lleva el nombre del producto y "foto N".
- **Imagen OG por defecto:** el muestrario de granalla angular (G10 a G120). La home y 22 páginas no tenían `og:image`; ahora la tienen todas.
- **Widgets:** el sidebar de páginas tenía un widget "Conócenos" con *Lorem Ipsum* y links de redes falsos (`www.twitter.com`, etc.). Se movieron a widgets inactivos. No estaban a la vista, porque el CSS del tema oculta el sidebar, pero estaban en el HTML de `/granalla-de-acero/`.
- **llms.txt:** agrega el precio y las dos fichas de granalla.
- **Imágenes y fuentes:** Imagify ya sirve AVIF/WebP, OMGF ya aloja las fuentes en el sitio y WP Rocket hace lazyload. No hacía falta más.

### 8. Feed para Google Merchant Center

`mu-plugins/roca-feed-google.php` publica **https://rocaingenieria.com.ar/feed-google-merchant.xml**:
- RSS 2.0 con `g:`, `noindex` y caché de 6 h;
- 2 ítems, granalla esférica y angular, con precio `1.35 USD` y `unit_pricing_measure` de 1 kg.

**OJO:** para Argentina, Merchant Center puede exigir el precio en ARS. Si lo pide, definir `ROCA_FEED_ARS_POR_USD` (por ejemplo en `wp-config.php`) con la cotización que decida Agustín y el feed pasa solo a ARS.

### 9. IndexNow

- Clave en `/38879130abf5195006ae8fc21ed981e2.txt`.
- `roca-seo-schema.php` avisa a api.indexnow.org al publicar o editar productos, páginas y entradas. El aviso es no bloqueante y se repite como máximo cada 10 min por URL.
- Envío masivo de 52 URLs: HTTP 202 (aceptado). Script reutilizable: `tools/indexnow_ping.sh`.
- `tools/calentar_cache.sh` pide todo el sitemap para dejar armada la caché de WP Rocket.

### 10. Borradores (NO publicados)

- **ID 26985:** «Granalla esférica o angular: cuál elegir según la pieza y el acabado», en `/granalla-esferica-o-angular/`.
- **ID 26986:** «Granalla de acero vs. arena para granallado: costo por m², polvo y residuos», en `/granalla-de-acero-vs-arena/`.

Usan solo datos del sitio: tablas y FAQ de `/granalla-de-acero/` y el PDF "Comparación de abrasivos". Tienen meta descripción, palabra clave, links a las fichas y a la página madre, y el precio. Los crea el script `borradores_granalla_20261124.php`.

### Incidentes

- **CSS de Divi incompleto durante unos 4 minutos** (00:46 a 00:50 UTC, 21:46 a 21:50 hora de Argentina). Pasó al probar "Static CSS File Generation" (ver punto 1). Se revirtió y se verificó.
- Ninguna caída. Cada PHP se probó con `php -l` en el servidor antes de copiarlo, y después con curl a varias páginas, todas 200.
- Dos respuestas raras y transitorias mientras WP Rocket regeneraba la caché recién purgada:
  - un `403 Forbidden` en la categoría Granalla;
  - una home servida sin el `<html lang>` nuevo.

  Al reintentar, a los pocos segundos, todo dio 200 y correcto.
- Como en Línea Magna, los mu-plugins nuevos y el `.htaccess` tardaron entre 1 y 2 minutos en verse en todos los nodos.

### Pendiente / necesita a Agustín

- **Revisar y publicar** los 2 borradores.
- **Google Merchant Center:**
  - abrir la cuenta y verificar el dominio;
  - cargar el feed programado;
  - decidir la moneda (USD o ARS, y en ese caso la cotización);
  - configurar el envío a AMBA.
- **Search Console:** pedir "Solicitar indexación" de `/granalla-de-acero/` y de las 2 fichas de granalla. Windsor está bloqueado (plan Free) y no tengo acceso de escritura.
- **Datos contradictorios en textos viejos** (no los toqué para no inventar):
  - **Categoría Abrasivos:** dice "G10 a G300 / S70 a S780" y "40 a 68 HRC". El tramo de más de 60 HRC equivale a la dureza de la tipo E.
  - **Ficha angular:** "Corte de piedra y granito (durezas altas)" es un uso de las durezas altas.
  - **Las dos fichas:** dicen "Dureza GL/GP (53-60 HRC)", mientras la página madre dice C/D (48-52 / 54-61 HRC).

  Conviene que Agustín defina un solo juego de datos.
- **Categoría vacía llamada "62":** aparece en el filtro de productos con 0 ítems. Borrarla queda a su criterio.
- **Teléfonos:** la home muestra el +54 9 11 5005-2904 y la granalla usa el WhatsApp +54 9 11 3901-6625. Para Google Business conviene un solo número principal.
- **LatinCloud** (igual que en Línea Magna): `opcache.validate_timestamps=0` hace que un PHP editado no se recargue.
- **Merchant/schema:** si hay presentación fija (bolsas de N kg) o una política de devoluciones, sumarlas al feed y al schema.

### Mediciones

Las páginas estaban cacheadas por WP Rocket. Se hicieron 2 corridas por página; "antes" es de las 00:10 UTC y "después" de las 01:05 UTC.

| Página | Rendimiento | CLS | LCP | SEO |
|---|---|---|---|---|
| `/granalla-de-acero/` | 68-69 → **90-92** | 0,961 → **0,002** | 3,1 → 3,2-3,3 s | 100 → 100 |
| Ficha granalla esférica | 64 → **88-89** | 1,083 → **0,033** | 3,7-3,8 → 3,3-3,4 s | 100 → 100 |
| Categoría Abrasivos | 62-65 → **84-86** | 0,958 → **0,105** | 3,6 → 3,6 s | 100 → 100 |
| Home | 66-90 → **73-74** | 0-1 → **0,361** | 3,4-3,5 → 3,4 s | 100 → 100 |

- **Home:** el CLS que queda viene del CSS de las secciones de la propia home, que Divi también imprime al pie. El arreglo de fondo es "Static CSS File Generation", pero hoy genera mal el CSS global (ver punto 1).
- **SEO en Lighthouse:** ya daba 100. Lo que se ganó no lo mide Lighthouse: metas, títulos, schema, canonical, idioma, OG, sitemap y enlaces internos.

**Crawl del sitemap:**

| Medida | Antes | Después |
|---|---|---|
| URLs en el sitemap | 55 | 52 (sin `/shop/`, sin `/presupuesto/` en noindex y sin la categoría Granalla, que tiene canonical a la página madre) |
| Páginas sin meta descripción | 10 | 0 |
| Títulos duplicados | 1 | 0 |
| Páginas sin `og:image` | 23 | 0 |
| `lang` / `og:locale` es-AR | 0 | todas |
| Fichas con Product schema | 0 | 2 (granalla) |
| Organization con LocalBusiness | no | sí |
| www → sin www | 6,8 s (WordPress) | 0,1 s (Apache) |
| Links internos rotos (404) | 0 | 0 |
| Links internos que pasan por 301 | 3 | 0 |


## 2026-11-24 (más tarde) — Teléfono único +54 9 11 3901-6625 (decisión de Agustín)

Se reemplazó el +54 9 11 5005-2904 en:
- la home (1599), Servicio técnico (26051) y el layout de Divi «Sección Contacto» (26177), que mostraban `+549-1150052904`;
- la caché `_et_pb_truncate_post` de 1599, 1610, 1620 y 26051;
- el snippet de WPCode «Schema Organization» (26851, y su caché en la opción `wpcode_snippets`);
- el schema LocalBusiness de Yoast. Lo corrige el mu-plugin nuevo `roca-seo-telefono.php`, porque el hosting no recarga PHP editados; la copia del repo de `roca-seo-schema.php` también quedó con el número nuevo.

No había links `tel:` ni widgets con el número viejo. Los WhatsApp ya usaban 541139016625.

**Script:** `tools/scripts-wp/telefono_unico_20261124.php`.
- Backup en `backups-roca/20261124/telefono_backup.json`.
- Revertir: `wp eval-file …/telefono_unico_20261124.php revertir` y borrar `roca-seo-telefono.php`.

**Verificado con curl:** 0 apariciones del número viejo en la home, Nosotros, Repuestos, Servicio técnico y Granalla, y en todas responde 200. WP Rocket y la caché de Divi se purgaron y se volvió a precargar el sitemap.

**Ojo (pendiente):** el snippet WPCode 26851 emite un **segundo** Organization en todas las páginas, además del de Yoast. Tiene `legalName`, `taxID` y `foundingDate`. Conviene pasar esos datos al de Yoast y desactivar el snippet, para que no haya dos entidades distintas.

## 2026-11-24 (más tarde) — Un solo Organization

- El snippet WPCode 26851 «Schema Organization» emitía un segundo Organization en todas las páginas. Sus datos pasaron al Organization de Yoast con el mu-plugin `roca-seo-org-datos.php`:
  - `legalName` "Roca Agustin Federico";
  - `taxID` 23-32507446-9;
  - `foundingDate` 2021.
- El snippet se **desactivó sin borrarlo**: quedó en `post_status` draft con `WPCode_Snippet::force_deactivate()`. Se usó ese método porque `deactivate()` pide permisos de usuario y en WP-CLI no los hay.
  - WPCode puede mostrar en el admin un aviso de "snippet desactivado"; es esperado.
- **Script:** `tools/scripts-wp/wpcode_org_20261124.php`.
  - Backup en `backups-roca/20261124/wpcode_org_backup.json`.
  - Revertir: `wp eval-file …/wpcode_org_20261124.php revertir`, que reactiva el snippet por SQL, y borrar `roca-seo-org-datos.php`.
- **Verificado:** un solo Organization, con los 3 datos y el teléfono único, en la home, Productos, Nosotros, `/granalla-de-acero/` y la ficha angular. Se purgó WP Rocket y se precargó el sitemap: 52 URLs, todas 200.

## 2026-11-25 — Pendientes técnicos sin Agustín (tipo E, categoría "62", H1, CLS de la home, links)

No se tocaron cuentas de Google, Merchant ni Search Console (las maneja otra sesión), ni precios, stock, usuarios o credenciales. No se borró contenido.

### Backup y cómo revertir

Todo en `/www/rocaingenieria.com.ar/backups-roca/20261125/` (permisos 700):
- `db-antes.sql.gz`: dump completo previo (`wp db export --ssl=0`).
- `mu-plugins-antes/`: copia de los mu-plugins previos. `mu-plugins-nuevos/`: copia de los nuevos.
- `pendientes_backup.json`: contenido anterior de los posts 26714, 26601, 1599, 1610 y de la descripción del term 62.
- **Revertir la base:** `wp eval-file ../backups-roca/20261125/pendientes_roca_20261125.php revertir` (script en `tools/scripts-wp/`).
- **Revertir los mu-plugins:** borrar `roca-h1.php`, `roca-cat-oculta2.php` y/o `roca-velocidad-home.php` (1-2 min en todos los nodos) y purgar WP Rocket.

### 1. Granalla tipo E / durezas > 60 HRC
- Categoría Abrasivos (term 62): "40 a 68 HRC" → "40 a 60 HRC; otras durezas, bajo consulta".
- Ficha angular (26714): se sacó el ítem "Corte de piedra y granito (durezas altas)".
- Barrido de todo el sitio (contenido, metas, términos, opciones, HTML de las 52 URLs, schema y los 14 PDFs enlazados). Queda, a propósito o para decidir:
  - `/granalla-de-acero/` y el folleto técnico PDF: tipo **D 577–720 Hv (54–61 HRC)**. Es la ficha de la granalla D (tipo que sí se vende); el tope de 61 HRC pasa apenas de 60. No lo cambié para no alterar un dato técnico: **decide Agustín**.
  - Guías de normas en PDF (J1993 rango H "60 HRC o más", J827 "40 a 65", J1830 "57 a 63", guía general "55–62"): por la regla del 2026-10-01 las guías deben ser completas aunque incluyan productos que no se venden. No se tocaron.
  - Turbina T4: "placas de desgaste de fundición templada (60-64 Hrc)": es la dureza de una pieza del equipo, no de la granalla. Sin cambios.
  - Las copias viejas en opciones `post_content_bak*_20261001` (no se publican) siguen nombrando GH/E; no se borraron.

### 2. Durezas GL/GP (fichas) vs C/D (página madre)
No hay fuente para GL/GP en el sitio ni en los repos. La tabla C/D de la página madre sale del catálogo del proveedor (`~/folletos/src/cym/…angular-grit-ficha-tecnica…pdf`: C 48-52, D 54-61, H >60). GL/GP es nomenclatura SAE J1993 (L 54-61, la guía propia no define GP). **No se unificó**: la ficha angular sigue diciendo "GL/GP (53-60 HRC aprox.)". Decide Agustín.

### 3. Categoría vacía "62" (term 107, slug `62`, 0 productos)
Mu-plugin `roca-cat-oculta2.php`: se excluye de `get_terms()` en el frontend (filtro Themify de /productos/ y categorías, listados), `/categoria-producto/62/` queda `noindex, follow` y fuera del sitemap de Yoast. No se borró.
- Incidente: la v1 (`roca-cat-oculta.php`) la excluía también al resolver la URL y `/categoria-producto/62/` dio **404 unos 3 minutos** (11:36-11:40 UTC). Se reemplazó por la v2, que no toca las búsquedas por slug/nombre/ID. Ninguna otra URL se afectó.

### 4. CLS de la home (mu-plugin `roca-velocidad-home.php`)
Divi imprimía el CSS de diseño de los módulos (`et-builder-module-design-tb-773-tb-1150-1599-cached-inline-styles`, 48 KB) al final del `<body>`; la fila `#Fondo` de la primera sección saltaba. El mu-plugin mueve ese bloque, sin modificarlo, al final del `<head>` solo en la home (buffer de salida antes de WP Rocket). **No** se activó "Static CSS File Generation". Capturas antes/después idénticas (celular y escritorio).

| Home (Lighthouse 12 móvil, 3 corridas, cacheada) | Antes | Después |
|---|---|---|
| CLS | 0,361 | **0** |
| Rendimiento | 73-74 | **89-91** |
| LCP | 3,4-3,5 s | 3,35 s |

### 5. Un solo H1 (mu-plugin `roca-h1.php`)
Antes (DOM renderizado): 20 URLs del sitemap sin H1 (17 categorías, Nosotros, Políticas de calidad, Servicio técnico, Productos, Repuestos) y 9 fichas con 2 H1 (título de la plantilla + H1 en la descripción).
- Si hay más de un H1, los siguientes pasan a H2 (solo en la salida HTML).
- Si no hay ninguno, el primer párrafo del primer módulo de texto (el título visible: "Quiénes somos", "Políticas de calidad", "Nuestros productos"…) pasa a `<h1>` con estilo heredado: se ve igual (capturas comparadas). En categorías el H1 dice "Nuestros productos: **<categoría>**".
- Las descripciones de categoría que se insertan por JS con un H1 pasan a H2.

### 6. Links internos
- Botón "Cotizar por WhatsApp" de la Válvula dosificadora (26601): el href era solo el texto (`Hola%2C…`), daba 404. Ahora `https://wa.me/541139016625?text=…`, como las fichas de granalla.
- Home (1599) y Nosotros (1610): botones a `/nosotros`, `/productos`, `/repuestos` y `/servicios` (301) ahora apuntan directo con barra final (`/servicios` → `/mantenimiento-industrial-integral/`).

### Hallazgo (no resuelto, para decidir)
Las descripciones de categoría (con sus textos SEO) las inserta un snippet por JS **dentro de un `rocketlazyloadscript`**: el "Delay JS" de WP Rocket no las ejecuta hasta que el usuario interactúa, así que Google probablemente no las ve. Arreglo posible: excluir ese script del Delay JS (`rocket_delay_js_exclusions`) o imprimirlas en el servidor. No lo hice porque puede agregar CLS al final de la página y conviene medirlo aparte.

### Barrido final del sitemap
Ver la tabla al final de esta entrada.

| Barrido (52 URLs del sitemap, después de purgar y precargar) | Antes | Después |
|---|---|---|
| Respuesta 200 | 52/52 | 52/52 |
| Sin H1 / con 2 H1 (DOM renderizado) | 20 / 9 | **0 / 0** |
| Sin title / sin meta description | 0 / 0 | 0 / 0 |
| Organization o Product duplicados | 0 | 0 |
| Links internos rotos | 1 (WhatsApp de la válvula) | **0** (159 links) |
| Links internos que pasan por 301 | 4 | **0** |

Capturas antes/después en frankia: `~/capturas-20261125/` (cmp-*.png). Purgados WP Rocket y la caché de Divi, sitemap precargado (escritorio y celular).
