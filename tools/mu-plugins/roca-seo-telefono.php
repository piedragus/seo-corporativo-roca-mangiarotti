<?php
/**
 * Plugin Name: Roca - teléfono único en el schema
 * Description: Agustín eligió +54 9 11 3901-6625 como único teléfono (2026-11-24). Pisa el telephone que pone roca-seo-schema.php (archivo aparte porque el hosting no recarga PHP editados). Para revertir: borrar este archivo.
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }
add_filter( 'wpseo_schema_organization', function ( $data ) {
	$data['telephone'] = '+54 9 11 3901-6625';
	if ( isset( $data['contactPoint'] ) && is_array( $data['contactPoint'] ) ) {
		$data['contactPoint']['telephone'] = '+54 9 11 3901-6625';
	}
	return $data;
}, 30 );
