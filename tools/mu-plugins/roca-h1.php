<?php
/**
 * Plugin Name: Roca - un solo H1 por página
 * Description: (1) Si una ficha/página tiene más de un <h1> (título de la plantilla de Divi + otro H1 en la descripción), los siguientes pasan a <h2>. (2) Si la página no tiene ningún <h1> (Nosotros, Políticas de calidad, Servicio técnico, Repuestos, Productos y categorías), el primer párrafo del primer módulo de texto (el título visible: "Quiénes somos", "Nuestros productos", etc.) pasa a <h1> con el mismo estilo (font/color heredados). En categorías el H1 dice "Nuestros productos: <nombre de la categoría>". (3) El H1 que algunas descripciones de categoría insertan por JS pasa a H2. Solo cambia el HTML de salida; no toca la base. Agregado 2026-11-25. Para revertir: borrar este archivo (1-2 min en todos los nodos) y purgar WP Rocket.
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

add_action( 'template_redirect', function () {
	if ( is_admin() || wp_doing_ajax() || is_feed() || is_user_logged_in() || isset( $_GET['et_fb'] ) ) {
		return;
	}
	$es_archivo = function_exists( 'is_shop' ) && ( is_shop() || is_product_taxonomy() );
	if ( ! is_singular() && ! $es_archivo ) {
		return;
	}
	$nombre = '';
	if ( function_exists( 'is_product_category' ) && is_product_category() ) {
		$t      = get_queried_object();
		$nombre = $t && isset( $t->name ) ? $t->name : '';
	}
	ob_start( function ( $html ) use ( $nombre ) {
		return roca_h1_unico( $html, $nombre );
	} );
}, 99998 );

/** Separa el HTML en trozos "código" (script/style/noscript/template) y "marcado". */
function roca_h1_trozos( $html ) {
	return preg_split( '~(<(script|style|noscript|template)\b[^>]*>.*?</\2>)~si', $html, -1, PREG_SPLIT_DELIM_CAPTURE );
}

function roca_h1_unico( $html, $nombre ) {
	if ( ! is_string( $html ) || false === stripos( $html, '</html>' ) ) {
		return $html;
	}
	$partes = roca_h1_trozos( $html );
	// preg_split con 2 grupos: [texto, bloque, nombre_tag, texto, bloque, nombre_tag, ...]
	$visto = 0;
	$out   = array();
	$n     = count( $partes );
	for ( $i = 0; $i < $n; $i++ ) {
		$p = $partes[ $i ];
		if ( 1 === $i % 3 ) { // bloque de código.
			$out[] = $p;
			continue;
		}
		if ( 2 === $i % 3 ) { // nombre del tag capturado: descartar.
			continue;
		}
		// Marcado: dejar el primer <h1>, el resto pasa a <h2>.
		$p = preg_replace_callback( '~<(/?)h1(\s[^>]*)?>~i', function ( $m ) use ( &$visto ) {
			if ( '' === $m[1] ) {
				$visto++;
			}
			if ( $visto <= 1 ) {
				return $m[0];
			}
			return '<' . $m[1] . 'h2' . ( isset( $m[2] ) ? $m[2] : '' ) . '>';
		}, $p );
		$out[] = $p;
	}
	$html = implode( '', $out );

	if ( 0 === $visto ) {
		// Ningún H1: el primer párrafo del primer módulo de texto del cuerpo pasa a H1.
		$ini = false;
		foreach ( array( 'et-l--body', 'et-l--post' ) as $marca ) {
			$ini = strpos( $html, $marca );
			if ( false !== $ini ) {
				break;
			}
		}
		if ( false !== $ini ) {
			$re = '~(<div class="et_pb_text_inner">)\s*<p>(.*?)</p>~s';
			if ( preg_match( $re, $html, $m, PREG_OFFSET_CAPTURE, $ini ) ) {
				$texto = $m[2][0];
				if ( '' !== $nombre ) {
					$texto = 'Nuestros productos: <strong>' . esc_html( $nombre ) . '</strong>';
				}
				$h1   = $m[1][0] . '<h1 class="roca-h1" style="font:inherit;color:inherit;letter-spacing:inherit;text-transform:inherit;text-align:inherit;margin:0;padding:0">' . $texto . '</h1>';
				$html = substr( $html, 0, $m[0][1] ) . $h1 . substr( $html, $m[0][1] + strlen( $m[0][0] ) );
				$visto = 1;
			}
		}
	}
	if ( $visto >= 1 ) {
		// Descripciones de categoría que se insertan por JS con un <h1>: pasan a <h2>.
		$html = preg_replace_callback( '~<script\b[^>]*>[^<]*?roca-category-description.*?</script>~s', function ( $m ) {
			return str_replace( array( '<h1', '<\/h1>', '</h1>' ), array( '<h2', '<\/h2>', '</h2>' ), $m[0] );
		}, $html );
	}
	return $html;
}
