<?php
/**
 * Plugin Name: Roca - datos legales en el Organization de Yoast
 * Description: Pasa al Organization de Yoast la razón social, el CUIT y el año de fundación que estaban en el snippet WPCode 26851 «Schema Organization» (desactivado el 2026-11-24 para que haya un solo Organization). Para revertir: borrar este archivo y reactivar el snippet 26851.
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }
add_filter( 'wpseo_schema_organization', function ( $data ) {
	$data['legalName']    = 'Roca Agustin Federico';
	$data['taxID']        = '23-32507446-9';
	$data['foundingDate'] = '2021';
	return $data;
}, 40 );
