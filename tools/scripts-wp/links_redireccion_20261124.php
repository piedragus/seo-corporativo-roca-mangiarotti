<?php
/**
 * Links internos que pasaban por un 301: los apunta directo al destino final.
 * Uso: wp eval-file links_redireccion_20261124.php [aplicar]
 * Backup: backups-roca/20261124/links_backup.json ({ID: contenido anterior}). Revertir: restaurar post_content desde ese JSON.
 */
global $wpdb;
$aplicar = isset( $args[0] ) && 'aplicar' === $args[0];
$cambios = array(
	25861 => array( 'button_url="/tienda"' => 'button_url="/productos/"' ), // botón "Volver a la tienda" de la plantilla de producto (Divi)
	26723 => array( 'https://rocaingenieria.com.ar/producto/cartucho-filtrante/' => 'https://rocaingenieria.com.ar/producto/cartucho-filtrante-polvo-y-humo/' ),
	26773 => array( 'https://rocaingenieria.com.ar/servicio-tecnico-2/' => 'https://rocaingenieria.com.ar/mantenimiento-industrial-integral/' ),
);
$bk = array();
foreach ( $cambios as $id => $rep ) {
	$c = get_post_field( 'post_content', $id, 'raw' );
	$n = strtr( $c, $rep );
	if ( $n === $c ) { WP_CLI::log( "$id sin cambios" ); continue; }
	$bk[ $id ] = $c;
	WP_CLI::log( "$id: " . implode( ', ', array_keys( $rep ) ) );
	if ( $aplicar ) { $wpdb->update( $wpdb->posts, array( 'post_content' => $n ), array( 'ID' => $id ) ); clean_post_cache( $id ); }
}
if ( $aplicar && $bk ) {
	$f = '/www/rocaingenieria.com.ar/backups-roca/20261124/links_backup.json';
	file_put_contents( $f, wp_json_encode( $bk, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ) );
	chmod( $f, 0600 );
	if ( class_exists( 'ET_Core_PageResource' ) ) { ET_Core_PageResource::remove_static_resources( 'all', 'all', true ); }
	if ( function_exists( 'rocket_clean_domain' ) ) { rocket_clean_domain(); }
	WP_CLI::log( "backup $f; cachés de Divi y WP Rocket limpiadas" );
}
