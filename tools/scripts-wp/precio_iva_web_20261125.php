<?php
/* Muestra el precio de la granalla con y sin IVA (USD 1,35 + IVA / USD 1,63 con IVA), 2026-11-25.
 * wp eval-file precio_iva_web_20261125.php           -> simula
 * wp eval-file precio_iva_web_20261125.php aplicar   -> aplica (guarda originales en originales.json)
 * wp eval-file precio_iva_web_20261125.php revertir  -> restaura desde originales.json */
global $wpdb;
$modo = $args[0] ?? 'simular';
$dir  = '/www/rocaingenieria.com.ar/backups-roca/20261125/precio-iva-web';
$json = "$dir/originales.json";
$C = ' (USD 1,63 por kg con IVA incluido)';
$posts = array(
	25760 => array( array( 'USD 1,35 por kg + IVA. Entrega', 'USD 1,35 por kg + IVA' . $C . '. Entrega' ) ),
	26714 => array( array( 'USD 1,35 por kg + IVA. Entrega', 'USD 1,35 por kg + IVA' . $C . '. Entrega' ) ),
	26873 => array( array( '<b>Desde <i>1,35 USD</i> por kg + IVA</b><span>', '<b>Desde <i>1,35 USD</i> por kg + IVA</b><span>USD 1,63 por kg con IVA incluido</span><span>' ) ),
	26985 => array( array( '<strong>USD 1,35 por kg + IVA</strong>', '<strong>USD 1,35 por kg + IVA</strong>' . $C ) ),
	26986 => array( array( '<strong>USD 1,35 por kg + IVA</strong>', '<strong>USD 1,35 por kg + IVA</strong>' . $C ) ),
);
$metas = array(
	25760 => 'Granalla esférica de acero SAE J444, S-110 a S-660, para shot peening. USD 1,35/kg + IVA (USD 1,63 con IVA), entrega en AMBA. Consultá descuentos por volumen.',
	26714 => 'Granalla angular de acero G-16 a G-120 (SAE J444) para decapado y anclaje. USD 1,35/kg + IVA (USD 1,63 con IVA), entrega en AMBA. Consultá descuentos por volumen.',
	26873 => 'Granalla de acero esférica y angular en stock. USD 1,35/kg + IVA (USD 1,63 con IVA), entrega en AMBA o con tu transporte. Consultá descuentos por volumen.',
);
$cat_id = 105;
$cat_desc = 'Granalla de acero esférica (Steel Shot) y angular (Steel Grit) en stock en Buenos Aires. USD 1,35/kg + IVA (USD 1,63 con IVA), entrega en AMBA o con tu transporte.';
$llms = ABSPATH . 'llms.txt';
$llms_de = 'Precio: USD 1,35 por kg + IVA;';
$llms_a  = 'Precio: USD 1,35 por kg + IVA (USD 1,63 por kg con IVA incluido);';

function roca_limpiar( $ids ) {
	foreach ( $ids as $id ) { clean_post_cache( $id ); }
	if ( function_exists( 'rocket_clean_domain' ) ) { rocket_clean_domain(); }
	if ( function_exists( 'et_core_clear_wp_cache' ) ) { et_core_clear_wp_cache(); }
	echo "Cachés limpiadas\n";
}

if ( 'revertir' === $modo ) {
	$o = json_decode( file_get_contents( $json ), true );
	foreach ( $o['posts'] as $id => $c ) { $wpdb->update( $wpdb->posts, array( 'post_content' => $c ), array( 'ID' => $id ) ); echo "post $id restaurado\n"; }
	foreach ( $o['metas'] as $id => $m ) { update_post_meta( $id, '_yoast_wpseo_metadesc', $m ); echo "meta $id restaurada\n"; }
	$tm = get_option( 'wpseo_taxonomy_meta' ); $tm['product_cat'][ $cat_id ]['wpseo_desc'] = $o['cat']; update_option( 'wpseo_taxonomy_meta', $tm ); echo "categoría restaurada\n";
	file_put_contents( $llms, $o['llms'] ); echo "llms.txt restaurado\n";
	roca_limpiar( array_keys( $o['posts'] ) );
	exit;
}

$orig = array( 'posts' => array(), 'metas' => array() );
$nuevo = array();
foreach ( $posts as $id => $reps ) {
	$c = get_post_field( 'post_content', $id, 'raw' );
	$orig['posts'][ $id ] = $c;
	foreach ( $reps as $r ) {
		$n = substr_count( $c, $r[0] );
		if ( strpos( $c, $r[1] ) !== false ) { echo "post $id: ya aplicado\n"; continue 2; }
		if ( 1 !== $n ) { fwrite( STDERR, "post $id: el texto aparece $n veces, aborto\n" ); exit( 1 ); }
		$c = str_replace( $r[0], $r[1], $c );
	}
	$nuevo[ $id ] = $c;
	echo "post $id: 1 reemplazo\n";
}
foreach ( $metas as $id => $m ) { $orig['metas'][ $id ] = get_post_meta( $id, '_yoast_wpseo_metadesc', true ); echo "meta $id: " . mb_strlen( $m ) . " caracteres\n"; }
$tm = get_option( 'wpseo_taxonomy_meta' );
$orig['cat'] = $tm['product_cat'][ $cat_id ]['wpseo_desc'];
$orig['llms'] = file_get_contents( $llms );
if ( 1 !== substr_count( $orig['llms'], $llms_de ) && false === strpos( $orig['llms'], $llms_a ) ) { fwrite( STDERR, "llms.txt: no encuentro el texto, aborto\n" ); exit( 1 ); }

if ( 'aplicar' !== $modo ) { echo "Simulación OK. Usar: aplicar\n"; exit; }
if ( ! file_exists( $json ) ) { file_put_contents( $json, wp_json_encode( $orig, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT ) ); echo "Originales en $json\n"; }
foreach ( $nuevo as $id => $c ) { $wpdb->update( $wpdb->posts, array( 'post_content' => $c ), array( 'ID' => $id ) ); }
foreach ( $metas as $id => $m ) { update_post_meta( $id, '_yoast_wpseo_metadesc', $m ); }
$tm['product_cat'][ $cat_id ]['wpseo_desc'] = $cat_desc; update_option( 'wpseo_taxonomy_meta', $tm );
file_put_contents( $llms, str_replace( $llms_de, $llms_a, $orig['llms'] ) );
// Yoast indexables guardan la meta: forzar que se reconstruyan.
$wpdb->query( "DELETE FROM {$wpdb->prefix}yoast_indexable WHERE (object_type='post' AND object_id IN (25760,26714,26873)) OR (object_type='term' AND object_id=$cat_id)" );
echo "Aplicado\n";
roca_limpiar( array_keys( $posts ) );
