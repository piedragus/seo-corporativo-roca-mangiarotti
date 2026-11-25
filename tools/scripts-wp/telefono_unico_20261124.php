<?php
/**
 * Teléfono único de rocaingenieria.com.ar: +54 9 11 3901-6625 (decisión de Agustín, 2026-11-24).
 * Reemplaza el +54 9 11 5005-2904 en: home (1599), Servicio técnico (26051), layout "Sección Contacto" (26177),
 * snippet WPCode "Schema Organization" (26851, y su caché en la opción wpcode_snippets) y la caché _et_pb_truncate_post.
 * El schema LocalBusiness de Yoast lo corrige el mu-plugin nuevo roca-seo-telefono.php.
 * Uso: wp eval-file telefono_unico_20261124.php [aplicar]     Revertir: wp eval-file telefono_unico_20261124.php revertir
 */
global $wpdb;
$modo = isset( $args[0] ) ? $args[0] : 'simular';
$bkf  = '/www/rocaingenieria.com.ar/backups-roca/20261124/telefono_backup.json';
$NUEVO = '+54 9 11 3901-6625';
$rep  = array( '+549-1150052904' => $NUEVO, '+549115005-2904' => $NUEVO, '+54 9 11 5005-2904' => $NUEVO, '1150052904' => '1139016625' );

if ( 'revertir' === $modo ) {
	$bk = json_decode( file_get_contents( $bkf ), true );
	foreach ( $bk['posts'] as $id => $c ) { $wpdb->update( $wpdb->posts, array( 'post_content' => $c ), array( 'ID' => (int) $id ) ); clean_post_cache( (int) $id ); }
	foreach ( $bk['meta'] as $r ) { update_post_meta( (int) $r[0], $r[1], $r[2] ); }
	foreach ( $bk['options'] as $o => $v ) { update_option( $o, $v ); }
	rename( $bkf, $bkf . '.revertido-' . gmdate( 'YmdHis' ) );
	if ( function_exists( 'rocket_clean_domain' ) ) { rocket_clean_domain(); }
	WP_CLI::success( 'Revertido (borrar además wp-content/mu-plugins/roca-seo-telefono.php).' );
	return;
}
$aplicar = 'aplicar' === $modo;
if ( $aplicar && file_exists( $bkf ) ) { WP_CLI::error( "Ya existe $bkf" ); }
$bk = array( 'posts' => array(), 'meta' => array(), 'options' => array() );
foreach ( array( 1599, 26051, 26177, 26851 ) as $id ) {
	$c = get_post_field( 'post_content', $id, 'raw' );
	$n = strtr( $c, $rep );
	if ( $n === $c ) { continue; }
	$bk['posts'][ $id ] = $c;
	WP_CLI::log( "post $id " . get_the_title( $id ) );
	if ( $aplicar ) { $wpdb->update( $wpdb->posts, array( 'post_content' => $n ), array( 'ID' => $id ) ); clean_post_cache( $id ); }
}
foreach ( $wpdb->get_results( "SELECT post_id, meta_key, meta_value FROM {$wpdb->postmeta} WHERE meta_value LIKE '%50052904%' OR meta_value LIKE '%5005-2904%'" ) as $r ) {
	$v = maybe_unserialize( $r->meta_value );
	if ( ! is_string( $v ) ) { WP_CLI::warning( "meta no string {$r->post_id} {$r->meta_key}" ); continue; }
	$bk['meta'][] = array( $r->post_id, $r->meta_key, $v );
	WP_CLI::log( "meta {$r->post_id} {$r->meta_key}" );
	if ( $aplicar ) { update_post_meta( $r->post_id, $r->meta_key, strtr( $v, $rep ) ); }
}
$walk = function ( $v ) use ( &$walk, $rep ) { return is_array( $v ) ? array_map( $walk, $v ) : ( is_string( $v ) ? strtr( $v, $rep ) : $v ); };
foreach ( array( 'wpcode_snippets' ) as $o ) {
	$v = get_option( $o );
	$n = $walk( $v );
	if ( $n === $v ) { continue; }
	$bk['options'][ $o ] = $v;
	WP_CLI::log( "option $o" );
	if ( $aplicar ) { update_option( $o, $n ); }
}
if ( $aplicar ) {
	file_put_contents( $bkf, wp_json_encode( $bk, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ) );
	chmod( $bkf, 0600 );
	if ( class_exists( 'ET_Core_PageResource' ) ) { ET_Core_PageResource::remove_static_resources( 'all', 'all', true ); }
	if ( function_exists( 'rocket_clean_domain' ) ) { rocket_clean_domain(); }
	WP_CLI::log( "backup $bkf; cachés purgadas" );
}
