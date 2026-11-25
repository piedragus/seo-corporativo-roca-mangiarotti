<?php
/**
 * Plugin Name: Roca - ocultar la categoría vacía "62"
 * Description: La categoría de producto "62" (term_id 107, slug 62, 0 productos; parece creada por error) aparecía en el filtro de productos (Themify Product Filter) y en listados. No se borra: se excluye de get_terms() en el frontend (filtro, listados de subcategorías, widgets), su archivo /categoria-producto/62/ queda en noindex y fuera del sitemap de Yoast. Agregado 2026-11-25. v2: no se excluye en búsquedas por slug/nombre/ID (la v1 hacía que /categoria-producto/62/ diera 404). Para revertir: borrar este archivo (1-2 min en todos los nodos) y purgar WP Rocket.
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

if ( ! defined( 'ROCA_CAT_OCULTA' ) ) {
	define( 'ROCA_CAT_OCULTA', 107 );
}

add_filter( 'get_terms_args', function ( $args, $taxonomies ) {
	if ( is_admin() && ! wp_doing_ajax() ) {
		return $args;
	}
	// Las búsquedas puntuales (por slug, nombre o ID: resolver la URL de la categoría, get_term_by) no se tocan.
	if ( ! empty( $args['slug'] ) || ! empty( $args['name'] ) || ! empty( $args['term_taxonomy_id'] ) || ! empty( $args['object_ids'] ) ) {
		return $args;
	}
	if ( ! in_array( 'product_cat', (array) $taxonomies, true ) || ! empty( $args['include'] ) ) {
		return $args;
	}
	$ex = isset( $args['exclude'] ) ? $args['exclude'] : array();
	$ex = is_array( $ex ) ? $ex : wp_parse_id_list( $ex );
	$ex[] = ROCA_CAT_OCULTA;
	$args['exclude'] = array_values( array_unique( array_map( 'intval', $ex ) ) );
	return $args;
}, 10, 2 );

add_filter( 'wpseo_robots', function ( $robots ) {
	if ( is_tax( 'product_cat', ROCA_CAT_OCULTA ) ) {
		return 'noindex, follow';
	}
	return $robots;
}, 99 );

add_filter( 'wpseo_exclude_from_sitemap_by_term_ids', function ( $ids ) {
	$ids   = (array) $ids;
	$ids[] = ROCA_CAT_OCULTA;
	return $ids;
} );
