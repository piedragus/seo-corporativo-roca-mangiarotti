<?php
/**
 * Plugin Name: Roca - velocidad (CLS y LCP)
 * Description: 1) Apaga el "Critical CSS" de Divi: dejaba en el pie de la página el CSS del encabezado del Theme Builder (que lo oculta en el celular y le da margen), y al cargar tarde movía todo el contenido (CLS de 0,96 a 1,08 en Lighthouse). 2) El logo del encabezado no se carga en diferido (WP Rocket lo hacía con lazyload y era el LCP en las fichas). Agregado 2026-11-24. Para revertir: borrar este archivo y purgar la caché de WP Rocket.
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

add_filter( 'et_pb_critical_css_enabled', '__return_false' );

add_filter( 'rocket_lazyload_excluded_attributes', function ( $attrs ) {
	$attrs[] = 'logo-ingenieria-roca';
	return $attrs;
} );
