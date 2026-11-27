<?php
/**
 * Plugin Name: Roca - tocar la foto del producto abre el visor
 * Description: Con el zoom de WooCommerce activo, tocar la foto grande no hacía nada, y antes de que cargara el JS (o con JS demorado) abría el archivo de imagen en la misma pestaña, sin forma de salir salvo "atrás". Este script en línea (vanilla, con nowprocket para que WP Rocket no lo demore) intercepta el toque sobre la foto (incluida la capa .zoomImg que pone jquery.zoom encima del link, que era la que se comía el toque) y abre el visor PhotoSwipe de WooCommerce (el mismo del botón de lupa); si el JS de la galería todavía no cargó, espera hasta 4 s. Agregado 2026-11-27. Para revertir: borrar este archivo y vaciar la caché.
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

add_action( 'wp_footer', function () {
	if ( ! function_exists( 'is_product' ) || ! is_product() ) { return; }
	?>
<script nowprocket id="galeria-toque">
document.addEventListener('click',function(e){var a=e.target.closest&&e.target.closest('.woocommerce-product-gallery__image');if(!a)return;e.preventDefault();var g=a.closest('.woocommerce-product-gallery'),t0=Date.now();(function abrir(){var t=g&&g.querySelector('.woocommerce-product-gallery__trigger');if(t&&window.PhotoSwipe){t.click();return;}if(Date.now()-t0<4000)setTimeout(abrir,100);})();},true);
</script>
	<?php
}, 99 );
