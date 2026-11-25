<?php
/**
 * Revierte seo_roca_20261124.php usando backups-roca/20261124/seo_backup.json.
 * Uso: wp eval-file revertir_seo_roca_20261124.php [aplicar]   (sin "aplicar" solo simula)
 * Los mu-plugins nuevos (roca-seo-*.php, roca-velocidad.php, roca-feed-google.php) se revierten borrando el archivo.
 */
global $wpdb;
$aplicar = isset( $args[0] ) && 'aplicar' === $args[0];
$bk_file = '/www/rocaingenieria.com.ar/backups-roca/20261124/seo_backup.json';
$bk      = json_decode( file_get_contents( $bk_file ), true );
if ( ! $bk ) { WP_CLI::error( "No pude leer $bk_file" ); }

foreach ( $bk['postmeta'] as $r ) {
	list( $id, $k, $v ) = $r;
	WP_CLI::log( "postmeta $id $k -> [$v]" );
	if ( $aplicar ) { ( '' === $v || null === $v ) ? delete_post_meta( $id, $k ) : update_post_meta( $id, $k, $v ); }
}
foreach ( $bk['content'] as $id => $c ) {
	WP_CLI::log( "content $id (" . strlen( $c ) . ' bytes)' );
	if ( $aplicar ) { $wpdb->update( $wpdb->posts, array( 'post_content' => $c ), array( 'ID' => (int) $id ) ); clean_post_cache( (int) $id ); }
}
foreach ( $bk['termdesc'] as $tid => $d ) {
	WP_CLI::log( "term desc $tid" );
	if ( $aplicar ) { $wpdb->update( $wpdb->term_taxonomy, array( 'description' => $d ), array( 'term_id' => (int) $tid, 'taxonomy' => 'product_cat' ) ); clean_term_cache( (int) $tid, 'product_cat' ); }
}
foreach ( (array) $bk['alts'] as $att => $v ) {
	if ( $aplicar ) { ( '' === $v || null === $v ) ? delete_post_meta( (int) $att, '_wp_attachment_image_alt' ) : update_post_meta( (int) $att, '_wp_attachment_image_alt', $v ); }
}
WP_CLI::log( 'alts: ' . count( (array) $bk['alts'] ) );
foreach ( $bk['options'] as $o => $v ) {
	WP_CLI::log( "option $o" );
	if ( $aplicar ) { update_option( $o, $v ); }
}
if ( $aplicar ) {
	if ( function_exists( 'rocket_clean_domain' ) ) { rocket_clean_domain(); }
	rename( $bk_file, $bk_file . '.revertido-' . gmdate( 'YmdHis' ) );
	WP_CLI::success( 'Revertido.' );
} else {
	WP_CLI::log( 'SIMULACIÓN. Usar "aplicar".' );
}
