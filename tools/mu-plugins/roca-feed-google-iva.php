<?php
/**
 * Plugin Name: Roca - feed de granalla para Merchant, precio con IVA (v2)
 * Description: Reemplaza la salida de roca-feed-google.php en https://rocaingenieria.com.ar/feed-google-merchant.xml con dos cambios (2026-11-25): (1) g:price con IVA: 1,35 x 1,21 = 1,6335 -> 1.63 USD por kg (la web sigue diciendo USD 1,35/kg + IVA); (2) descripciones sin texto doble escapado: se quitan los tags, se decodifican las entidades HTML (&gt;, &nbsp;...) y se escapa una sola vez. El resto del feed sale igual. Va en un archivo aparte porque el opcache del hosting no recarga PHP editados; se engancha en init con prioridad 19, antes que roca-feed-google.php (20), y usa su propio transient. Para revertir: borrar este archivo (1-2 min en todos los nodos) y pedir el feed con ?regenerar=1.
 * OJO: si se define ROCA_FEED_ARS_POR_USD, el precio sale en ARS, también con IVA.
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

define( 'ROCA_FEED_USD_KG_SIN_IVA', 1.35 );
define( 'ROCA_FEED_IVA', 0.21 );

/** Texto plano para el feed: quita tags, decodifica entidades (incluidas las dobles) y deja espacios simples. Se escapa después, una sola vez. */
function roca_feed_iva_texto( $html ) {
	$s = wp_strip_all_tags( (string) $html );
	for ( $i = 0; $i < 3; $i++ ) {
		$d = html_entity_decode( $s, ENT_QUOTES | ENT_HTML5, 'UTF-8' );
		if ( $d === $s ) {
			break;
		}
		$s = $d;
	}
	return trim( preg_replace( '~[\s\x{00A0}]+~u', ' ', $s ) );
}

function roca_feed_iva_generar() {
	$x   = function ( $s ) { return htmlspecialchars( (string) $s, ENT_XML1 | ENT_QUOTES, 'UTF-8' ); };
	$usd = round( ROCA_FEED_USD_KG_SIN_IVA * ( 1 + ROCA_FEED_IVA ), 2 ); // 1.63 por kg, IVA incluido
	$cur = 'USD';
	$val = $usd;
	if ( defined( 'ROCA_FEED_ARS_POR_USD' ) && ROCA_FEED_ARS_POR_USD > 0 ) {
		$cur = 'ARS';
		$val = round( ROCA_FEED_USD_KG_SIN_IVA * ( 1 + ROCA_FEED_IVA ) * ROCA_FEED_ARS_POR_USD, 2 );
	}
	$items = array(
		25760 => 'Granalla de acero esférica (Steel Shot) S-110 a S-660, precio por kg',
		26714 => 'Granalla de acero angular (Steel Grit) G-16 a G-120, precio por kg',
	);
	$out  = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
	$out .= '<rss version="2.0" xmlns:g="http://base.google.com/ns/1.0"><channel>' . "\n";
	$out .= '<title>Roca Ingeniería - granalla de acero</title><link>https://rocaingenieria.com.ar/granalla-de-acero/</link><description>Granalla de acero esférica y angular. San Justo, Buenos Aires. Entrega en AMBA o por el transporte del cliente.</description>' . "\n";
	foreach ( $items as $id => $titulo ) {
		$p = wc_get_product( $id );
		if ( ! $p || 'publish' !== $p->get_status() ) {
			continue;
		}
		$imgs = array();
		foreach ( array_filter( array_merge( array( $p->get_image_id() ), $p->get_gallery_image_ids() ) ) as $a ) {
			if ( $u = wp_get_attachment_url( $a ) ) { $imgs[] = $u; }
		}
		$imgs = array_values( array_unique( $imgs ) );
		$desc = roca_feed_iva_texto( $p->get_short_description() . ' ' . $p->get_description() );
		$out .= "<item>\n";
		$out .= '<g:id>RI-' . $id . "</g:id>\n";
		$out .= '<g:title>' . $x( mb_substr( $titulo, 0, 150 ) ) . "</g:title>\n";
		$out .= '<g:description>' . $x( mb_substr( $desc, 0, 4900 ) ) . "</g:description>\n";
		$out .= '<g:link>' . $x( get_permalink( $id ) ) . "</g:link>\n";
		if ( $imgs ) {
			$out .= '<g:image_link>' . $x( array_shift( $imgs ) ) . "</g:image_link>\n";
			foreach ( array_slice( $imgs, 0, 10 ) as $e ) {
				$out .= '<g:additional_image_link>' . $x( $e ) . "</g:additional_image_link>\n";
			}
		}
		$out .= "<g:availability>in_stock</g:availability>\n";
		$out .= '<g:price>' . number_format( $val, 2, '.', '' ) . ' ' . $cur . "</g:price>\n";
		$out .= "<g:unit_pricing_measure>1 kg</g:unit_pricing_measure>\n<g:unit_pricing_base_measure>1 kg</g:unit_pricing_base_measure>\n";
		$out .= "<g:condition>new</g:condition>\n<g:brand>Roca Ingeniería</g:brand>\n<g:identifier_exists>no</g:identifier_exists>\n";
		$out .= "<g:product_type>Abrasivos para granallado &gt; Granalla de acero</g:product_type>\n";
		$out .= "<g:material>Acero</g:material>\n";
		$out .= "</item>\n";
	}
	$out .= "</channel></rss>\n<!-- precio por kg con IVA 21 % incluido (USD 1,35 + IVA); entrega en AMBA o por el transporte del cliente; generado " . gmdate( 'c' ) . " -->\n";
	return $out;
}

add_action( 'init', function () {
	$path = wp_parse_url( isset( $_SERVER['REQUEST_URI'] ) ? $_SERVER['REQUEST_URI'] : '', PHP_URL_PATH );
	if ( '/feed-google-merchant.xml' !== $path || ! function_exists( 'wc_get_product' ) ) {
		return;
	}
	if ( ! defined( 'DONOTCACHEPAGE' ) ) {
		define( 'DONOTCACHEPAGE', true );
	}
	$xml = get_transient( 'roca_feed_google_iva' );
	if ( false === $xml || isset( $_GET['regenerar'] ) ) {
		$xml = roca_feed_iva_generar();
		set_transient( 'roca_feed_google_iva', $xml, 6 * HOUR_IN_SECONDS );
	}
	status_header( 200 );
	header( 'Content-Type: application/xml; charset=UTF-8' );
	header( 'X-Robots-Tag: noindex' );
	echo $xml; // phpcs:ignore
	exit;
}, 19 );

add_action( 'woocommerce_update_product', function () { delete_transient( 'roca_feed_google_iva' ); } );
