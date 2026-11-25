<?php
/**
 * Plugin Name: Roca - SEO: schema local, Product de granalla, es_AR, sitemap e IndexNow
 * Description: Completa el grafo de Yoast (no agrega un segundo Organization): datos de negocio local tomados del sitio (dirección, teléfono, mapa), Product con precio por kg en las dos fichas de granalla, og:locale/lang es_AR, saca /shop/ (redirige) del sitemap y avisa a IndexNow al publicar o editar. Agregado 2026-11-24. Para revertir: borrar este archivo.
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

define( 'ROCA_INDEXNOW_KEY', '38879130abf5195006ae8fc21ed981e2' );
/** Fichas de granalla con precio público (USD 1,35/kg + IVA, mismo dato que /granalla-de-acero/). */
function roca_granalla_ids() {
	return array( 25760 => 'esferica', 26714 => 'angular' );
}

/* 1. Idioma: público argentino. */
add_filter( 'wpseo_locale', function () { return 'es_AR'; } );
add_filter( 'language_attributes', function ( $out ) {
	return preg_replace( '~lang="[^"]*"~', 'lang="es-AR"', $out );
} );

/* 2. Organization de Yoast -> también LocalBusiness, con los datos visibles en la home. */
add_filter( 'wpseo_schema_organization', function ( $data ) {
	$data['@type']     = array( 'Organization', 'LocalBusiness' );
	$data['address']   = array(
		'@type'           => 'PostalAddress',
		'streetAddress'   => 'Dr. Enrique Eizaguirre 737',
		'addressLocality' => 'San Justo',
		'addressRegion'   => 'Buenos Aires',
		'postalCode'      => 'B1754FLA',
		'addressCountry'  => 'AR',
	);
	$data['geo']       = array( '@type' => 'GeoCoordinates', 'latitude' => -34.6717565, 'longitude' => -58.5451022 );
	$data['telephone'] = '+54 9 11 5005-2904';
	$data['email']     = 'contacto@rocaingenieria.com.ar';
	$data['areaServed'] = array(
		array( '@type' => 'Place', 'name' => 'AMBA (Ciudad de Buenos Aires y Gran Buenos Aires)' ),
		array( '@type' => 'Country', 'name' => 'Argentina' ),
	);
	$data['contactPoint'] = array(
		'@type'             => 'ContactPoint',
		'contactType'       => 'sales',
		'telephone'         => '+54 9 11 3901-6625',
		'availableLanguage' => 'es',
		'areaServed'        => 'AR',
	);
	if ( empty( $data['image'] ) && ! empty( $data['logo'] ) ) {
		$data['image'] = $data['logo'];
	}
	return $data;
}, 20 );

/* 3. Product en las fichas de granalla (WooCommerce no lo emite porque el cuerpo de la ficha es de Divi). */
add_filter( 'wpseo_schema_graph', function ( $graph ) {
	if ( ! function_exists( 'is_product' ) || ! is_product() ) {
		return $graph;
	}
	$id  = get_queried_object_id();
	$ids = roca_granalla_ids();
	if ( ! isset( $ids[ $id ] ) ) {
		return $graph;
	}
	$p = wc_get_product( $id );
	if ( ! $p ) {
		return $graph;
	}
	$url  = get_permalink( $id );
	$org  = home_url( '/#organization' );
	$imgs = array();
	foreach ( array_filter( array_merge( array( $p->get_image_id() ), $p->get_gallery_image_ids() ) ) as $a ) {
		if ( $u = wp_get_attachment_url( $a ) ) { $imgs[] = $u; }
	}
	$desc = trim( preg_replace( '~\s+~u', ' ', wp_strip_all_tags( $p->get_short_description() ) ) );
	if ( '' === $desc ) {
		$desc = (string) get_post_meta( $id, '_yoast_wpseo_metadesc', true );
	}
	$graph[] = array(
		'@type'            => 'Product',
		'@id'              => $url . '#product',
		'name'             => wp_strip_all_tags( $p->get_name() ),
		'description'      => $desc,
		'url'              => $url,
		'mainEntityOfPage' => array( '@id' => $url ),
		'image'            => array_values( array_unique( $imgs ) ),
		'sku'              => 'RI-' . $id,
		'category'         => 'Abrasivos para granallado > Granalla de acero',
		'brand'            => array( '@type' => 'Brand', 'name' => 'Roca Ingeniería' ),
		'material'         => 'Acero',
		'offers'           => array(
			'@type'              => 'Offer',
			'url'                => $url,
			'price'              => '1.35',
			'priceCurrency'      => 'USD',
			'priceSpecification' => array(
				'@type'                 => 'UnitPriceSpecification',
				'price'                 => '1.35',
				'priceCurrency'         => 'USD',
				'unitCode'              => 'KGM',
				'referenceQuantity'     => array( '@type' => 'QuantitativeValue', 'value' => 1, 'unitCode' => 'KGM' ),
				'valueAddedTaxIncluded' => false,
			),
			'availability'       => 'https://schema.org/InStock',
			'itemCondition'      => 'https://schema.org/NewCondition',
			'areaServed'         => 'AMBA, Buenos Aires, Argentina',
			'seller'             => array( '@id' => $org ),
		),
	);
	return $graph;
}, 20 );

/* 4. Sitemap: el archivo de productos (/shop/) redirige a /productos/, que ya está en el sitemap de páginas. */
add_filter( 'wpseo_sitemap_post_type_archive_link', function ( $link, $post_type ) {
	return 'product' === $post_type ? false : $link;
}, 10, 2 );

/* 5. IndexNow (Bing, Yandex, Seznam, Naver...): aviso al publicar o editar productos, páginas y entradas. */
add_action( 'wp_after_insert_post', function ( $post_id, $post ) {
	if ( wp_is_post_revision( $post_id ) || wp_is_post_autosave( $post_id ) || 'publish' !== $post->post_status ) {
		return;
	}
	if ( ! in_array( $post->post_type, array( 'product', 'page', 'post' ), true ) ) {
		return;
	}
	$url = get_permalink( $post_id );
	if ( ! $url || get_transient( 'roca_indexnow_' . md5( $url ) ) ) {
		return;
	}
	set_transient( 'roca_indexnow_' . md5( $url ), 1, 10 * MINUTE_IN_SECONDS );
	wp_remote_post( 'https://api.indexnow.org/indexnow', array(
		'blocking' => false,
		'timeout'  => 3,
		'headers'  => array( 'Content-Type' => 'application/json; charset=utf-8' ),
		'body'     => wp_json_encode( array(
			'host'        => 'rocaingenieria.com.ar',
			'key'         => ROCA_INDEXNOW_KEY,
			'keyLocation' => 'https://rocaingenieria.com.ar/' . ROCA_INDEXNOW_KEY . '.txt',
			'urlList'     => array( $url ),
		) ),
	) );
}, 20, 2 );
