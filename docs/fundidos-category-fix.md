# Investigación: descripción de la categoría "Fundidos" no aparece en vivo

## Resumen — la causa NO era la que se sospechaba

El diagnóstico previo (caché, hooks de Divi, OPcache) asumía que la descripción de la
categoría **ya estaba guardada** en la base de datos y que el problema era de renderizado.
Verificado hoy (18/9) por **dos vías independientes** — la tool `wp_get_terms` de royal-mcp y
la API REST de WooCommerce (`GET /wc/v3/products/categories/71`) — que **eso es falso**: el
campo `description` del término "Fundidos" (ID 71, `product_cat`) está vacío (`""`), y no hay
ningún rastro del texto guardado en otro lado (`wp_get_term_meta` tampoco tiene nada parecido,
y no hay ningún borrador en este repo con el texto).

Lo único que sí está cargado correctamente es el **meta de Yoast** (título/descripción SEO
para buscadores, `og:description`, schema, etc.) — es un campo completamente separado del
`term_description` real que WooCommerce/WordPress usan para el contenido visible de la
categoría. Alguien cargó uno y no el otro.

## Snippet 26865 (WPCode) — código correcto, sin nada que inyectar

Contenido verificado tal cual está guardado, estado `publish` (activo):

```php
add_action( 'wp_footer', function() {
    if ( is_product_category() ) {
        $term = get_queried_object();
        if ( $term && ! empty( $term->description ) ) {
            $desc = wpautop( wp_kses_post( $term->description ) );
            ?>
            <script>
            document.addEventListener('DOMContentLoaded', function() {
                var shop = document.querySelector('.et_pb_shop');
                if (shop) {
                    var div = document.createElement('div');
                    div.className = 'roca-category-description';
                    div.style.marginTop = '30px';
                    div.innerHTML = <?php echo wp_json_encode( $desc ); ?>;
                    shop.parentNode.insertBefore(div, shop.nextSibling);
                }
            });
            </script>
            <?php
        }
    }
});
```

La condición `! empty( $term->description )` es exactamente la que bloquea todo: como está
vacío, el bloque completo (incluido el `<script>`) nunca se imprime. **No hace falta tocar
este código para nada** — la lógica está bien planteada para el caso real (Divi Shop module
sin hooks estándar de WooCommerce), simplemente no tiene contenido que mostrar.

## Verificación en el HTML real servido

`curl` a `https://rocaingenieria.com.ar/categoria-producto/repuestos/fundidos/` (200 OK):
- `.et_pb_shop` **sí** aparece en el HTML (el módulo de Divi está ahí, confirmado).
- `roca-category-description` (el div que inyectaría el snippet) **no** aparece — consistente
  con que el bloque PHP nunca llegó a ejecutar su parte de salida.

## Qué NO hizo falta hacer

Los pasos 3 y 4 del plan original (desactivar/reactivar el snippet para forzar un re-guardado
por si era OPcache, o migrar a un mecanismo distinto tipo template de Divi/bloque nativo) no
se ejecutaron porque el diagnóstico de la causa raíz los volvió innecesarios — no hay ningún
problema de caché, hooks o renderizado que resolver. El mecanismo del snippet en sí funciona
como está diseñado.

## Resuelto (18/9/2026) — descripción real cargada, snippet renderizando en vivo

Agustín pasó el copy real (`repuestos_fundidos_seo.zip`: contenido SEO/GEO completo + fotos +
un render 3D). Se cargó vía `wp_update_term` (royal-mcp) en el término 71 (`product_cat`).

**Hallazgo al guardar**: la sanitización nativa de WordPress para `term_description`
(`pre_term_description` → `wp_filter_kses`) es más estricta que la del snippet
(`wp_kses_post`) — permite `<strong>`/`<em>`/etc. pero **descarta `<h2>`, `<h3>`, `<ul>`,
`<li>` y `<p>`** antes de que el dato llegue a la base. El primer intento (con esas etiquetas,
replicando 1:1 la estructura del doc) se guardó igual pero perdió toda la jerarquía visual
(subtítulos mezclados con el párrafo). Se reescribió usando solo lo que sobrevive
(`<strong>` para subtítulos/énfasis, `&mdash;` + saltos de línea para la lista de
especificaciones, párrafos separados por doble salto de línea que `wpautop()` convierte en
`<p>` al renderizar) — mismo contenido, misma información, formato compatible con la
sanitización real del campo.

**Verificado en el HTML servido en vivo** (`curl` a la URL pública, dos veces — antes y
después del segundo guardado): el snippet 26865 ahora sí inyecta
`<div class="roca-category-description">` con el texto completo, bien formateado (párrafos
separados, subtítulos en negrita, lista de especificaciones con guiones). **El bug original
queda cerrado.**

**Imagen nueva subida**: el render 3D de la rueda de 6 paletas (venía en el zip como
`muestra_rueda_r2.png`, pese a que el doc decía que no se había podido incluir) se subió a la
Media Library como `rueda-turbina-6-paletas-roca-ingenieria.png` (ID 26868), con el
alt/título/leyenda/descripción exactos que pedía el doc de contenido. **No se insertó en el
layout de Divi de la página** — queda disponible en la Media Library para que quien edite la
página la agregue donde corresponda visualmente.

Las fotos reales que ya estaban publicadas (rueda de turbina ID 26637, protector de tapa
curvo ID 26635) ya tenían el alt/título correcto del doc — no se tocaron.

La "Imagen 4" (vista isométrica de conjunto) sigue pendiente tal como indicaba el doc original
— no hay archivo real todavía, no se inventó ninguno.
