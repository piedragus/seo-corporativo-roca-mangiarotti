<?php
/**
 * Plugin Name: Roca - Schema Product de granalla con IVA incluido
 * Description: El Product de las dos fichas de granalla (roca-seo-schema.php) sale con price 1.63 USD/kg y valueAddedTaxIncluded=true, igual que el feed de Merchant (roca-feed-google-iva.php). En pantalla se muestran las dos cifras: USD 1,35 + IVA y USD 1,63 con IVA. El precio de WooCommerce no se toca. Agregado 2026-11-25. Para revertir: borrar este archivo.
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

define( 'ROCA_GRANALLA_PRECIO_CON_IVA', '1.63' ); // 1,35 x 1,21 = 1,6335

add_filter( 'wpseo_schema_graph', function ( $graph ) {
	if ( ! function_exists( 'is_product' ) || ! is_product() ) {
		return $graph;
	}
	if ( ! in_array( (int) get_queried_object_id(), array( 25760, 26714 ), true ) ) {
		return $graph;
	}
	foreach ( $graph as &$node ) {
		if ( ! is_array( $node ) || ( $node['@type'] ?? '' ) !== 'Product' || empty( $node['offers'] ) ) {
			continue;
		}
		$node['offers']['price'] = ROCA_GRANALLA_PRECIO_CON_IVA;
		$node['offers']['priceSpecification'] = array(
			'@type'                 => 'UnitPriceSpecification',
			'price'                 => ROCA_GRANALLA_PRECIO_CON_IVA,
			'priceCurrency'         => 'USD',
			'unitCode'              => 'KGM',
			'referenceQuantity'     => array( '@type' => 'QuantitativeValue', 'value' => 1, 'unitCode' => 'KGM' ),
			'valueAddedTaxIncluded' => true,
		);
	}
	unset( $node );
	return $graph;
}, 30 );
