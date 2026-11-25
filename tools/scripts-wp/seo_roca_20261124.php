<?php
/**
 * SEO rocaingenieria.com.ar — 2026-11-24 (pedido de Agustín: "más visibilidad en Google").
 * Uso (parado en /www/rocaingenieria.com.ar/htdocs):
 *   wp eval-file seo_roca_20261124.php            -> simula (muestra qué cambiaría)
 *   wp eval-file seo_roca_20261124.php aplicar    -> aplica y guarda backup en backups-roca/20261124/seo_backup.json
 * Revertir: wp eval-file revertir_seo_roca_20261124.php aplicar
 *
 * No toca precios, stock, nombres de producto, usuarios ni credenciales. Las descripciones se tocan por SQL
 * (sin disparar hooks de WooCommerce) y solo para: sacar la granalla angular tipo E (regla de negocio),
 * agregar la línea de precio ya publicada en /granalla-de-acero/ y agregar links internos a /granalla-de-acero/.
 */
global $wpdb;
$aplicar = isset( $args[0] ) && 'aplicar' === $args[0];
$dir     = '/www/rocaingenieria.com.ar/backups-roca/20261124';
$bk_file = $dir . '/seo_backup.json';
if ( $aplicar && file_exists( $bk_file ) ) {
	WP_CLI::error( "Ya existe $bk_file: revertir primero o moverlo, para no pisar el backup original." );
}
$bk = array( 'postmeta' => array(), 'content' => array(), 'termdesc' => array(), 'options' => array() );

$G   = 'https://rocaingenieria.com.ar/granalla-de-acero/';
$WA  = 'https://wa.me/541139016625?text=' . rawurlencode( 'Hola, quiero cotizar granalla de acero. Tipo: ___ Granulometría: ___ Cantidad (kg): ___' );

function rs_log( $m ) { WP_CLI::log( $m ); }

/* ---------- 1. Títulos y meta descripciones (Yoast) ---------- */
$metas = array(
	1599  => array( 'Granalla de acero y granalladoras en Buenos Aires | Roca Ingeniería', 'Fabricamos granalladoras y aspiradores industriales en San Justo. Granalla de acero esférica y angular en stock, entrega en AMBA. Repuestos y servicio técnico.' ),
	26873 => array( 'Granalla de acero esférica y angular: precio y stock en AMBA', 'Granalla de acero esférica y angular en stock, bajo norma SAE. USD 1,35 por kg + IVA, entrega en AMBA o con tu transporte. Consultá descuentos por volumen.' ),
	25760 => array( 'Granalla de acero esférica (Steel Shot) S-110 a S-660 | Roca', 'Granalla esférica de acero SAE J444/J827, de S-110 a S-660, para granallado y shot peening. USD 1,35/kg + IVA, entrega en AMBA. Consultá descuentos por volumen.' ),
	26714 => array( 'Granalla de acero angular (Steel Grit) G-16 a G-120 | Roca', 'Granalla angular de acero para decapado y perfil de anclaje, de G-16 a G-120 (SAE J444). USD 1,35/kg + IVA, entrega en AMBA. Consultá descuentos por volumen.' ),
	25797 => array( 'Granalladoras, aspiradores y granalla de acero | Roca Ingeniería', null ),
	1610  => array( 'Nosotros: fabricantes de granalladoras en Argentina | Roca Ingeniería', 'Más de 15 años diseñando, fabricando y manteniendo equipos industriales: granalladoras, aspiración de polvo y repuestos. San Justo, Buenos Aires.' ),
	26045 => array( null, 'Políticas de calidad de Roca Ingeniería y formulario de reclamos para clientes de equipos industriales, granalladoras y repuestos.' ),
	1620  => array( null, 'Repuestos originales para granalladoras, aspiradores y transporte de materiales, y piezas a medida. Turbinas, fundidos y mecanizados. Consultá por WhatsApp.' ),
	26895 => array( null, 'Goma de 6 mm de espesor en rollos de 1 m x 10 m para recubrimiento antiabrasivo. Consultá precio por WhatsApp. Entrega en AMBA o con tu transporte.' ),
	25750 => array( null, 'Mangas filtrantes de poliéster para filtros de mangas y colectores de polvo industriales, con gran capacidad de filtración. Consultá medidas por WhatsApp.' ),
	2573  => array( null, 'Granalladoras de tambor rotativo para granallar y tamborear piezas de fundición: línea económica y línea de alta producción con elevador y limpiador.' ),
	2570  => array( null, 'Tolvas de granallado y arenado portátiles por aire comprimido, de cámara simple y doble, para abrasivo mineral, vegetal o metálico. Consultá capacidades.' ),
	26471 => array( 'Filtros de mangas industriales | Roca Ingeniería', null ),
	26782 => array( 'Granalladora para estructuras metálicas y perfiles | Roca Ingeniería', null ),
);
foreach ( $metas as $id => $td ) {
	foreach ( array( '_yoast_wpseo_title' => $td[0], '_yoast_wpseo_metadesc' => $td[1] ) as $k => $v ) {
		if ( null === $v ) { continue; }
		$old = get_post_meta( $id, $k, true );
		if ( $old === $v ) { continue; }
		$bk['postmeta'][] = array( $id, $k, $old );
		rs_log( "META $id $k: [" . $old . '] -> [' . $v . ']' );
		if ( $aplicar ) { update_post_meta( $id, $k, $v ); }
	}
}
// Página /presupuesto/ (carrito de cotización de YITH, sin contenido propio): noindex.
$old = get_post_meta( 25794, '_yoast_wpseo_meta-robots-noindex', true );
if ( '1' !== $old ) {
	$bk['postmeta'][] = array( 25794, '_yoast_wpseo_meta-robots-noindex', $old );
	rs_log( 'NOINDEX 25794 /presupuesto/' );
	if ( $aplicar ) { update_post_meta( 25794, '_yoast_wpseo_meta-robots-noindex', '1' ); }
}

/* ---------- 2. Categorías: meta de Yoast ---------- */
$tm = get_option( 'wpseo_taxonomy_meta' );
$bk['options']['wpseo_taxonomy_meta'] = $tm;
$cat_meta = array(
	105 => array( 'wpseo_title' => 'Comprar granalla de acero esférica y angular | Roca Ingeniería', 'wpseo_desc' => 'Granalla de acero esférica (Steel Shot) y angular (Steel Grit) en stock en Buenos Aires. USD 1,35 por kg + IVA, entrega en AMBA o con el transporte del cliente.', 'wpseo_canonical' => $G ),
	73  => array( 'wpseo_desc' => 'Cintas transportadoras y sistemas de movimiento de materiales a medida, para granalla, arena, granos y más. Fabricación propia en San Justo, Buenos Aires.' ),
);
foreach ( $cat_meta as $tid => $vals ) {
	foreach ( $vals as $k => $v ) {
		rs_log( "TERMMETA $tid $k: [" . ( isset( $tm['product_cat'][ $tid ][ $k ] ) ? $tm['product_cat'][ $tid ][ $k ] : '' ) . "] -> [$v]" );
		$tm['product_cat'][ $tid ][ $k ] = $v;
	}
}
if ( $aplicar ) { update_option( 'wpseo_taxonomy_meta', $tm ); }

/* ---------- 3. Contenido ---------- */
$reemplazos = array(
	// Regla de negocio: la granalla angular tipo E no se menciona nunca. Se alinea con el folleto técnico (que ya dice "Otras durezas, bajo consulta").
	26873 => array(
		'<tr><td><b>E</b></td><td>&gt; 697 Hv (&gt; 60 HRC)</td></tr>' => '',
		' <b>E</b> permanece angular: superficies uniformemente rugosas, sobre todo con aire comprimido.' => ' Otras durezas, bajo consulta.',
	),
	26714 => array(
		'GH (&gt;60 HRC), GL/GP (53-60 HRC aprox.)' => 'GL/GP (53-60 HRC aprox.). Otras durezas, bajo consulta.',
		'(GH, GL o GP)' => '(GL o GP)',
	),
);
$precio = '<p class="roca-precio-granalla"><strong>Precio:</strong> USD 1,35 por kg + IVA. Entrega en AMBA o despacho por el transporte que indique el cliente. Consultá descuentos por volumen. <a href="' . esc_url( $WA ) . '">Cotizar por WhatsApp</a></p>';
$agregar = array(
	25760 => "\n" . $precio,
	26714 => "\n" . $precio,
);
$link_equipo = "\n<p>¿Necesitás abrasivo para tu equipo? También vendemos <a href=\"$G\">granalla de acero esférica y angular</a> en stock, con entrega en AMBA.</p>";
foreach ( array( 25756, 25752, 26773, 26782, 26552, 2569, 2570, 2573 ) as $pid ) {
	$agregar[ $pid ] = $link_equipo;
}
$ids = array_unique( array_merge( array_keys( $reemplazos ), array_keys( $agregar ) ) );
foreach ( $ids as $pid ) {
	$c   = get_post_field( 'post_content', $pid, 'raw' );
	$new = $c;
	if ( isset( $reemplazos[ $pid ] ) ) {
		foreach ( $reemplazos[ $pid ] as $a => $b ) {
			if ( false === strpos( $new, $a ) ) { rs_log( "AVISO $pid: no encontré [$a]" ); continue; }
			$new = str_replace( $a, $b, $new );
		}
	}
	if ( isset( $agregar[ $pid ] ) ) {
		$ya = ( $agregar[ $pid ] === $link_equipo ) ? ( false !== strpos( $new, '/granalla-de-acero/' ) ) : ( false !== strpos( $new, 'roca-precio-granalla' ) );
		if ( $ya ) {
			rs_log( "SKIP $pid ya tiene el agregado" );
		} else {
			$new .= $agregar[ $pid ];
		}
	}
	if ( $new === $c ) { continue; }
	$bk['content'][ $pid ] = $c;
	rs_log( "CONTENT $pid " . get_the_title( $pid ) . ': ' . strlen( $c ) . ' -> ' . strlen( $new ) . ' bytes' );
	if ( $aplicar ) {
		$wpdb->update( $wpdb->posts, array( 'post_content' => $new ), array( 'ID' => $pid ) );
		clean_post_cache( $pid );
	}
}

/* Descripciones de categorías: link a la página madre de granalla. */
$cat_add = array(
	62  => "\n<p>Para granallado con turbina o con aire comprimido, mirá la guía de <a href=\"$G\">granalla de acero esférica y angular</a>: granulometrías SAE J444, usos, precio por kg y entrega en AMBA.</p>",
	66  => $link_equipo,
	67  => $link_equipo,
	68  => $link_equipo,
	108 => $link_equipo,
);
foreach ( $cat_add as $tid => $add ) {
	$t = get_term( $tid, 'product_cat' );
	if ( ! $t || is_wp_error( $t ) ) { continue; }
	if ( false !== strpos( $t->description, '/granalla-de-acero/' ) ) { rs_log( "SKIP cat $tid ya enlaza" ); continue; }
	$bk['termdesc'][ $tid ] = $t->description;
	rs_log( "TERMDESC $tid {$t->slug}: +" . strlen( $add ) . ' bytes' );
	if ( $aplicar ) {
		$wpdb->update( $wpdb->term_taxonomy, array( 'description' => $t->description . $add ), array( 'term_id' => $tid, 'taxonomy' => 'product_cat' ) );
		clean_term_cache( $tid, 'product_cat' );
	}
}

/* ---------- 4. ALT de imágenes de producto vacíos ---------- */
$alts_fijos = array(
	'GRANALLA_ANGULAR_MUESTRARIO.png'                 => 'Muestrario de granalla de acero angular (Steel Grit) por granulometría',
	'GRANALLA_ANGULAR_GRANULOMETRIA.png'              => 'Granulometrías de granalla de acero angular (Steel Grit)',
	'Granalla-de-acero-esferica-S170-s390-s460-s330.png' => 'Granalla de acero esférica S-170, S-330, S-390 y S-460',
	'Granalla-esferica_-granulometrias.png'           => 'Granulometrías de granalla de acero esférica (Steel Shot)',
	'granalla_esferica_muestrario-s780-a-s110.png'    => 'Muestrario de granalla de acero esférica (Steel Shot) por granulometría',
	'Granalla-de-acero-esferica.png'                  => 'Granalla de acero esférica (Steel Shot)',
);
$bk['alts'] = array();
foreach ( wc_get_products( array( 'status' => 'publish', 'limit' => -1 ) ) as $p ) {
	$n = 1;
	foreach ( array_values( array_unique( array_filter( array_merge( array( $p->get_image_id() ), $p->get_gallery_image_ids() ) ) ) ) as $att ) {
		$alt = get_post_meta( $att, '_wp_attachment_image_alt', true );
		if ( '' !== trim( (string) $alt ) || isset( $bk['alts'][ $att ] ) ) { $n++; continue; }
		$f   = basename( (string) get_attached_file( $att ) );
		$nom = trim( preg_replace( '~\s*\|.*$~', '', $p->get_name() ) );
		$v   = isset( $alts_fijos[ $f ] ) ? $alts_fijos[ $f ] : ( $nom . ( $n > 1 ? " - foto $n" : '' ) . ' - Roca Ingeniería' );
		$bk['alts'][ $att ] = $alt;
		rs_log( "ALT $att ($f): [$v]" );
		if ( $aplicar ) { update_post_meta( $att, '_wp_attachment_image_alt', $v ); }
		$n++;
	}
}

/* ---------- 5. Yoast: imagen OG por defecto (la home y varias páginas no tenían og:image) ---------- */
$soc = get_option( 'wpseo_social' );
$bk['options']['wpseo_social'] = $soc;
$img_id = 26717; // GRANALLA_ANGULAR_MUESTRARIO.png
$img    = wp_get_attachment_url( $img_id );
if ( $img && empty( $soc['og_default_image'] ) ) {
	$soc['og_default_image']    = $img;
	$soc['og_default_image_id'] = $img_id;
	rs_log( "OG default -> $img" );
	if ( $aplicar ) { update_option( 'wpseo_social', $soc ); }
}

/* ---------- 6. Widgets con texto de relleno (Lorem Ipsum) y redes falsas (www.twitter.com, etc.) -> inactivos ---------- */
$sw = get_option( 'sidebars_widgets' );
$bk['options']['sidebars_widgets'] = $sw;
$mover = array( 'aboutmewidget-3', 'block-6', 'block-7' );
if ( isset( $sw['sidebar-1'] ) ) {
	$quedan = array_values( array_diff( $sw['sidebar-1'], $mover ) );
	if ( $quedan !== $sw['sidebar-1'] ) {
		$sw['wp_inactive_widgets'] = array_values( array_unique( array_merge( $sw['wp_inactive_widgets'], array_intersect( $sw['sidebar-1'], $mover ) ) ) );
		$sw['sidebar-1']           = $quedan;
		rs_log( 'WIDGETS sidebar-1 -> ' . implode( ',', $quedan ) );
		if ( $aplicar ) { update_option( 'sidebars_widgets', $sw ); }
	}
}

if ( $aplicar ) {
	file_put_contents( $bk_file, wp_json_encode( $bk, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ) );
	chmod( $bk_file, 0600 );
	rs_log( "Backup: $bk_file" );
	if ( function_exists( 'rocket_clean_domain' ) ) { rocket_clean_domain(); rs_log( 'WP Rocket: caché purgada' ); }
} else {
	rs_log( 'SIMULACIÓN: no se cambió nada. Usar "aplicar".' );
}
