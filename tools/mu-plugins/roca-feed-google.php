<?php
/**
 * Plugin Name: Roca - feed de granalla para Google Merchant Center
 * Description: Publica https://rocaingenieria.com.ar/feed-google-merchant.xml (RSS 2.0 con g:) con las dos fichas de granalla de acero (esférica y angular), precio por kg (USD 1,35 + IVA, el mismo dato publicado en /granalla-de-acero/) y precio unitario por kg. Listo para cargar como "feed programado" cuando se abra la cuenta de Merchant Center. noindex, cacheado 6 h. Agregado 2026-11-24. Para revertir: borrar este archivo.
 * OJO: Merchant Center para Argentina puede exigir el precio en ARS. Si lo pide, definir ROCA_FEED_ARS_POR_USD (por ejemplo en wp-config.php) con la cotización que decida Agustín y el feed pasa a ARS.
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

function roca_feed_generar() {
	$x   = function ( $s ) { return htmlspecialchars( (string) $s, ENT_XML1 | ENT_QUOTES, 'UTF-8' ); };
	$usd = 1.35; // por kg, + IVA
	$cur = 'USD';
	$val = $usd;
	if ( defined( 'ROCA_FEED_ARS_POR_USD' ) && ROCA_FEED_ARS_POR_USD > 0 ) {
		$cur = 'ARS';
		$val = round( $usd * ROCA_FEED_ARS_POR_USD, 2 );
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
		$imgs  = array_values( array_unique( $imgs ) );
		$desc  = trim( preg_replace( '~\s+~u', ' ', wp_strip_all_tags( $p->get_short_description() . ' ' . $p->get_description() ) ) );
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
	$out .= "</channel></rss>\n<!-- precio + IVA; entrega en AMBA o por el transporte del cliente; generado " . gmdate( 'c' ) . " -->\n";
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
	$xml = get_transient( 'roca_feed_google' );
	if ( false === $xml || isset( $_GET['regenerar'] ) ) {
		$xml = roca_feed_generar();
		set_transient( 'roca_feed_google', $xml, 6 * HOUR_IN_SECONDS );
	}
	status_header( 200 );
	header( 'Content-Type: application/xml; charset=UTF-8' );
	header( 'X-Robots-Tag: noindex' );
	echo $xml; // phpcs:ignore
	exit;
}, 20 );

add_action( 'woocommerce_update_product', function () { delete_transient( 'roca_feed_google' ); } );
