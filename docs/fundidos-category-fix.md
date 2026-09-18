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

## Pendiente — falta el texto real

No inventé una descripción de marketing por mi cuenta. Falta:

1. Definir el texto real de la descripción de la categoría "Fundidos" (Agustín/equipo de Roca
   Ingeniería, o generarlo con `woo_content_generator.py` de este repo si aplica el mismo
   criterio que se usó para el resto del catálogo).
2. Guardarlo en el campo `description` del término 71 (`product_cat`) — con `wp_update_term`
   de royal-mcp, o desde wp-admin → Productos → Categorías → Fundidos → Descripción.
3. Una vez guardado, volver a bajar el HTML en vivo para confirmar que el snippet ahora sí
   inyecta el `<div class="roca-category-description">` — este paso si hace falta repetirlo.

Dato de contexto real ya disponible (del meta de Yoast, que sí está cargado) por si sirve de
punto de partida para el copy: la categoría cubre "repuestos fundidos originales para
granalladoras a turbina: ruedas, protectores y cajas de control en acero aleado
antiabrasivo".
