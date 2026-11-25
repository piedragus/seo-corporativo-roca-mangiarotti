<?php
/**
 * Desactiva (sin borrar) el snippet WPCode 26851 «Schema Organization», que emitía un segundo Organization.
 * Sus datos (legalName, taxID, foundingDate) pasan al de Yoast con el mu-plugin roca-seo-org-datos.php.
 * Uso: wp eval-file wpcode_org_20261124.php [aplicar|revertir]
 */
$modo = isset( $args[0] ) ? $args[0] : 'simular';
$bkf  = '/www/rocaingenieria.com.ar/backups-roca/20261124/wpcode_org_backup.json';
$id   = 26851;
$rebuild = function () {
	if ( function_exists( 'wpcode' ) && isset( wpcode()->cache ) && method_exists( wpcode()->cache, 'cache_all_loaded_snippets' ) ) { wpcode()->cache->cache_all_loaded_snippets(); return 'cache WPCode regenerada'; }
	return 'sin método de caché';
};
if ( 'revertir' === $modo ) {
	$bk = json_decode( file_get_contents( $bkf ), true );
	// Directo por SQL: wp_update_post puede filtrar el código del snippet si no hay unfiltered_html.
	global $wpdb;
	$wpdb->update( $wpdb->posts, array( 'post_status' => $bk['post_status'] ), array( 'ID' => $id ) );
	clean_post_cache( $id );
	update_option( 'wpcode_snippets', $bk['wpcode_snippets'] );
	WP_CLI::log( $rebuild() );
	if ( function_exists( 'rocket_clean_domain' ) ) { rocket_clean_domain(); }
	rename( $bkf, $bkf . '.revertido-' . gmdate( 'YmdHis' ) );
	WP_CLI::success( 'Snippet reactivado (borrar además roca-seo-org-datos.php).' );
	return;
}
WP_CLI::log( "snippet $id estado: " . get_post_status( $id ) . ' / ' . get_the_title( $id ) );
if ( 'aplicar' !== $modo ) { WP_CLI::log( 'SIMULACIÓN' ); return; }
if ( file_exists( $bkf ) ) { WP_CLI::log( "Ya existe $bkf (se conserva el backup original)" ); } else {
file_put_contents( $bkf, wp_json_encode( array( 'post_status' => get_post_status( $id ), 'wpcode_snippets' => get_option( 'wpcode_snippets' ) ), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ) );
chmod( $bkf, 0600 );
}
if ( class_exists( 'WPCode_Snippet' ) ) {
	$s = new WPCode_Snippet( $id );
	// deactivate() exige permisos de usuario (en WP-CLI no hay); force_deactivate() hace lo mismo sin ese chequeo.
	$s->force_deactivate();
	WP_CLI::log( 'desactivado con WPCode_Snippet::force_deactivate()' );
} else {
	wp_update_post( array( 'ID' => $id, 'post_status' => 'draft' ) );
}
WP_CLI::log( $rebuild() . '; estado ahora: ' . get_post_status( $id ) );
if ( function_exists( 'rocket_clean_domain' ) ) { rocket_clean_domain(); }
