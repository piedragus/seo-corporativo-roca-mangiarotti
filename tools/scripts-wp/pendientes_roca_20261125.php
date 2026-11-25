<?php
/**
 * Pendientes técnicos rocaingenieria.com.ar, 2026-11-25.
 * Uso (parado en htdocs):
 *   wp eval-file ../backups-roca/20261125/pendientes_roca_20261125.php            -> simula
 *   wp eval-file ../backups-roca/20261125/pendientes_roca_20261125.php aplicar    -> aplica (guarda backup antes)
 *   wp eval-file ../backups-roca/20261125/pendientes_roca_20261125.php revertir   -> restaura desde el backup
 * Backup: ../backups-roca/20261125/pendientes_backup.json
 */
global $wpdb;
$modo = isset( $args[0] ) ? $args[0] : 'simular';
$bk   = dirname( ABSPATH ) . '/backups-roca/20261125/pendientes_backup.json';

$posts = array(
	// Ficha angular: sacar el uso "Corte de piedra y granito (durezas altas)" (regla: no mencionar tipo E / >60 HRC).
	26714 => array( array( 're', '~\s*<li>\s*<p[^>]*>Corte de piedra y granito \(durezas altas\)\.</p>\s*</li>~u', '' ) ),
	// Válvula dosificadora: el botón de WhatsApp tenía solo el texto como href (404 relativo).
	26601 => array( array( 'str', 'href="Hola%2C%20quiero%20cotizar', 'href="https://wa.me/541139016625?text=Hola%2C%20quiero%20cotizar' ) ),
	// Home y Nosotros: botones sin barra final (pasaban por 301).
	1599  => array(
		array( 'str', 'button_url="/nosotros"', 'button_url="/nosotros/"' ),
		array( 'str', 'button_url="/productos"', 'button_url="/productos/"' ),
	),
	1610  => array(
		array( 'str', 'button_url="/repuestos"', 'button_url="/repuestos/"' ),
		array( 'str', 'button_url="/servicios"', 'button_url="/mantenimiento-industrial-integral/"' ),
	),
);
$terms = array(
	// Categoría Abrasivos: no publicar durezas por encima de 60 HRC.
	62 => array( array( 'str', '40 a 68 HRC', '40 a 60 HRC; otras durezas, bajo consulta' ) ),
);

function roca_aplica( $txt, $ops, &$n ) {
	foreach ( $ops as $o ) {
		if ( 'str' === $o[0] ) {
			$c = substr_count( $txt, $o[1] );
			$txt = str_replace( $o[1], $o[2], $txt );
		} else {
			$txt = preg_replace( $o[1], $o[2], $txt, -1, $c );
		}
		$n[] = $c;
	}
	return $txt;
}

if ( 'revertir' === $modo ) {
	$b = json_decode( file_get_contents( $bk ), true );
	foreach ( $b['posts'] as $id => $c ) {
		$wpdb->update( $wpdb->posts, array( 'post_content' => $c ), array( 'ID' => (int) $id ) );
		clean_post_cache( (int) $id );
		echo "revertido post $id\n";
	}
	foreach ( $b['terms'] as $id => $d ) {
		$wpdb->update( $wpdb->term_taxonomy, array( 'description' => $d ), array( 'term_id' => (int) $id, 'taxonomy' => 'product_cat' ) );
		clean_term_cache( (int) $id, 'product_cat' );
		do_action( 'edited_term', (int) $id, 0, 'product_cat' );
		echo "revertido term $id\n";
	}
	return;
}

$backup = array( 'posts' => array(), 'terms' => array() );
if ( 'aplicar' === $modo && file_exists( $bk ) ) {
	echo "Ya existe $bk: no se pisa. Revertir primero o borrar a mano.\n";
	return;
}
foreach ( $posts as $id => $ops ) {
	$old = $wpdb->get_var( $wpdb->prepare( "SELECT post_content FROM {$wpdb->posts} WHERE ID=%d", $id ) );
	$n = array();
	$new = roca_aplica( $old, $ops, $n );
	echo "post $id: reemplazos " . implode( ',', $n ) . ( $new === $old ? ' (sin cambios)' : '' ) . "\n";
	$backup['posts'][ $id ] = $old;
	if ( 'aplicar' === $modo && $new !== $old ) {
		$wpdb->update( $wpdb->posts, array( 'post_content' => $new ), array( 'ID' => $id ) );
		clean_post_cache( $id );
	}
}
foreach ( $terms as $id => $ops ) {
	$old = $wpdb->get_var( $wpdb->prepare( "SELECT description FROM {$wpdb->term_taxonomy} WHERE term_id=%d AND taxonomy='product_cat'", $id ) );
	$n = array();
	$new = roca_aplica( $old, $ops, $n );
	echo "term $id: reemplazos " . implode( ',', $n ) . "\n";
	$backup['terms'][ $id ] = $old;
	if ( 'aplicar' === $modo && $new !== $old ) {
		$wpdb->update( $wpdb->term_taxonomy, array( 'description' => $new ), array( 'term_id' => $id, 'taxonomy' => 'product_cat' ) );
		clean_term_cache( $id, 'product_cat' );
		do_action( 'edited_term', $id, 0, 'product_cat' );
	}
}
if ( 'aplicar' === $modo ) {
	file_put_contents( $bk, wp_json_encode( $backup, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT ) );
	chmod( $bk, 0600 );
	echo "Backup en $bk\n";
}
