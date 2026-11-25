<?php
/**
 * Plugin Name: Roca - CSS de módulos de la home en el <head> (CLS)
 * Description: Divi imprime el CSS de diseño de los módulos (<style id="et-builder-module-design-...-cached-inline-styles">) al final del <body>; en la home la primera sección se pintaba sin ese CSS y saltaba (CLS 0,36). Este mu-plugin MUEVE ese bloque, sin tocarlo, al final del <head>, solo en la home. No activa "Static CSS File Generation". Agregado 2026-11-25. Para revertir: borrar este archivo (tarda 1-2 min en todos los nodos) y purgar WP Rocket.
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

add_action( 'template_redirect', function () {
	if ( is_admin() || wp_doing_ajax() || is_feed() || ! is_front_page() || isset( $_GET['et_fb'] ) || is_user_logged_in() ) {
		return;
	}
	ob_start( 'roca_velocidad_home_mover_css' );
}, 99999 );

function roca_velocidad_home_mover_css( $html ) {
	if ( ! is_string( $html ) || '' === $html ) {
		return $html;
	}
	$head_fin = stripos( $html, '</head>' );
	if ( false === $head_fin ) {
		return $html;
	}
	if ( ! preg_match( '~<style[^>]*id="et-builder-module-design-[^"]*-cached-inline-styles"[^>]*>.*?</style>~s', $html, $m, PREG_OFFSET_CAPTURE ) ) {
		return $html;
	}
	$bloque = $m[0][0];
	$pos    = $m[0][1];
	if ( $pos < $head_fin ) {
		return $html; // ya está en el head.
	}
	// Sacar del body y poner justo antes de </head>.
	$html = substr( $html, 0, $pos ) . substr( $html, $pos + strlen( $bloque ) );
	return substr( $html, 0, $head_fin ) . $bloque . "\n" . substr( $html, $head_fin );
}
