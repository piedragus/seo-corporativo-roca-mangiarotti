<?php
/**
 * Crea 2 notas en BORRADOR (no se publican) para búsquedas sin marca sobre granalla.
 * Usan solo datos que ya están en el sitio: tablas y FAQ de /granalla-de-acero/ y el PDF "Comparación de abrasivos".
 * Uso: wp eval-file borradores_granalla_20261124.php aplicar
 * Revertir: borrar los borradores (IDs en backups-roca/20261124/borradores.json).
 */
$aplicar = isset( $args[0] ) && 'aplicar' === $args[0];
$G  = 'https://rocaingenieria.com.ar/granalla-de-acero/';
$ES = 'https://rocaingenieria.com.ar/producto/granalla-acero-esferica-shot-peening/';
$AN = 'https://rocaingenieria.com.ar/producto/granalla-de-acero-angular-steel-grit/';
$PDF = 'https://rocaingenieria.com.ar/wp-content/uploads/descargas/comparacion-abrasivos-roca-ingenieria.pdf';
$WA = 'https://wa.me/541139016625?text=' . rawurlencode( 'Hola, quiero cotizar granalla de acero. Tipo: ___ Granulometría: ___ Cantidad (kg): ___' );
$cierre = "<h2>Precio y entrega</h2>\n<p>La granalla de acero esférica y angular está en stock a <strong>USD 1,35 por kg + IVA</strong>, con entrega en AMBA o despacho por el transporte que indique el cliente. Consultá descuentos por volumen. <a href=\"$WA\">Cotizar por WhatsApp</a> o ver la <a href=\"$G\">guía completa de granalla de acero</a>.</p>";

$notas = array(
	array(
		'slug'  => 'granalla-esferica-o-angular',
		'title' => 'Granalla esférica o angular: cuál elegir según la pieza y el acabado',
		'desc'  => 'Diferencias entre granalla de acero esférica (shot) y angular (grit), qué granulometría usar en cada caso y cuándo conviene mezclarlas.',
		'kw'    => 'granalla esférica o angular',
		'html'  => "<p>La granalla de acero se vende en dos formas: <strong>esférica</strong> (Steel Shot, denominaciones S) y <strong>angular</strong> (Steel Grit, denominaciones G). Las dos se proyectan con turbina o con aire comprimido, pero dejan superficies distintas. Elegir bien ahorra tiempo de granallado y evita retrabajos.</p>
<h2>Granalla esférica: limpieza pareja y shot peening</h2>
<p>Sus partículas son redondas. La impronta es menos agresiva y deja un acabado parejo. Se usa para:</p>
<ul><li>limpieza y rebabado de piezas forjadas, fundidas o inyectadas;</li><li>desarenado de piezas de fundición;</li><li>shot peening (aumento de la resistencia a la fatiga);</li><li>piezas que quedan con la terminación de granallado a la vista.</li></ul>
<p>Ver <a href=\"$ES\">granalla de acero esférica (S-110 a S-660)</a>.</p>
<h2>Granalla angular: perfil de anclaje para pintar</h2>
<p>Sus partículas tienen aristas vivas: remueven óxido y recubrimientos y generan la rugosidad que necesita la pintura o el metalizado. Se usa para:</p>
<ul><li>decapado de laminados, perfiles y estructuras;</li><li>preparación de superficies para pintura y metalizado;</li><li>chapas y cañerías de espesor grueso;</li><li>granallado de cilindros de laminación.</li></ul>
<p>Ver <a href=\"$AN\">granalla de acero angular (G-16 a G-120)</a>.</p>
<h2>Tabla rápida</h2>
<table><thead><tr><th>Si necesitás…</th><th>Conviene</th></tr></thead><tbody>
<tr><td>Desarenar piezas de fundición</td><td>Esférica</td></tr>
<tr><td>Limpiar y rebabar piezas forjadas, fundidas o inyectadas</td><td>Esférica</td></tr>
<tr><td>Shot peening</td><td>Esférica</td></tr>
<tr><td>Remover óxido y pintura vieja</td><td>Angular</td></tr>
<tr><td>Generar perfil de anclaje para pintar o metalizar</td><td>Angular</td></tr>
<tr><td>Preparar chapas y cañerías de espesor grueso</td><td>Angular</td></tr>
</tbody></table>
<h2>Qué granulometría usar</h2>
<p><strong>Esférica:</strong> S-110 a S-230 para chapa fina, aluminio inyectado y terminaciones finas; S-280 y S-330 para forja y fundición con terminación fina y caño de pared delgada; S-390 y S-460 para forja y fundición de porte mediano, perfiles y estructuras soldadas de espesor medio y grueso; S-550 y S-660 para fundición de gran porte con mucha arena ocluida.</p>
<p><strong>Angular:</strong> G-16 y G-18 para espesores gruesos y rugosidad elevada; G-25 y G-40 para decapado y anclaje en piezas delgadas o con rugosidad baja (muy usada en equipos de aire comprimido); G-50 a G-120 cuando se busca rugosidad muy baja.</p>
<h2>¿Se pueden mezclar?</h2>
<p>Sí. Muchas instalaciones trabajan con una mezcla de esférica y angular para equilibrar limpieza y rugosidad. Contanos tu pieza y tu equipo (turbina o aire comprimido) y te recomendamos la proporción.</p>
$cierre",
	),
	array(
		'slug'  => 'granalla-de-acero-vs-arena',
		'title' => 'Granalla de acero vs. arena para granallado: costo por m², polvo y residuos',
		'desc'  => 'Comparativa de abrasivos: cuánto abrasivo se gasta por m² con arena, óxido de aluminio, escoria de cobre y granalla de acero, y por qué la granalla sale más barata.',
		'kw'    => 'granalla de acero vs arena',
		'html'  => "<p>El precio por kilo de un abrasivo dice poco: lo que importa es cuánto se gasta para dejar limpio cada metro cuadrado. La arena es barata por kilo, pero se rompe en el primer impacto; la granalla de acero se recupera y se vuelve a usar durante cientos de ciclos.</p>
<h2>Abrasivo gastado por metro cuadrado</h2>
<p>Comparación para chapa de acero laminada en caliente, desde oxidación grado A hasta terminación semi-blanca (Sa 2½, SP 10), con rugosidad de 40 a 50 micrones, trabajando donde el abrasivo se puede recuperar:</p>
<table><thead><tr><th>Abrasivo</th><th>Kg/m² necesarios</th><th>Vida útil (ciclos)</th><th>Gastado por m² (kg)</th><th>Factor de gasto</th></tr></thead><tbody>
<tr><td>Arena</td><td>25</td><td>1</td><td>25</td><td>446</td></tr>
<tr><td>Óxido de aluminio</td><td>20</td><td>5</td><td>4</td><td>71</td></tr>
<tr><td>Escoria de cobre</td><td>25</td><td>5</td><td>5</td><td>89</td></tr>
<tr><td>Granalla de acero</td><td>70</td><td>1.250</td><td>0,06</td><td>1</td></tr>
</tbody></table>
<p>El abrasivo gastado por m² es la cantidad necesaria dividida por la vida útil. Con arena se gasta unas 446 veces más abrasivo que con granalla de acero. <a href=\"$PDF\">Descargar la comparativa en PDF</a>.</p>
<h2>Además del ahorro directo</h2>
<ul><li><strong>Menos polvo:</strong> más vida útil de los colectores, mejor visibilidad en el puesto de trabajo y menos emisión al ambiente. La arena de sílice, además, expone al operario al riesgo de silicosis.</li>
<li><strong>Menos residuos sólidos</strong> y menores costos logísticos, porque se necesita poco volumen de abrasivo.</li>
<li><strong>Rugosidad acotada y controlada</strong>, gracias a la granulometría y la dureza bajo norma SAE.</li>
<li><strong>Más duración de los consumibles</strong> del equipo de impulsión, porque es menos abrasiva que los minerales.</li></ul>
<h2>¿Esférica o angular?</h2>
<p>Para limpieza, desarenado y shot peening se usa la <a href=\"$ES\">granalla esférica</a>; para remover óxido y dejar perfil de anclaje para pintura, la <a href=\"$AN\">granalla angular</a>.</p>
$cierre",
	),
);

$creados = array();
foreach ( $notas as $n ) {
	$ya = get_page_by_path( $n['slug'], OBJECT, 'post' );
	if ( $ya ) { WP_CLI::log( "Ya existe {$n['slug']} (ID {$ya->ID}), no se crea." ); continue; }
	WP_CLI::log( "Borrador: {$n['title']} (/{$n['slug']}/) " . strlen( $n['html'] ) . ' bytes' );
	if ( ! $aplicar ) { continue; }
	$id = wp_insert_post( array(
		'post_type'    => 'post',
		'post_status'  => 'draft',
		'post_title'   => $n['title'],
		'post_name'    => $n['slug'],
		'post_content' => $n['html'],
		'post_excerpt' => $n['desc'],
	), true );
	if ( is_wp_error( $id ) ) { WP_CLI::warning( $id->get_error_message() ); continue; }
	update_post_meta( $id, '_yoast_wpseo_metadesc', $n['desc'] );
	update_post_meta( $id, '_yoast_wpseo_focuskw', $n['kw'] );
	$creados[] = $id;
	WP_CLI::log( "  -> ID $id" );
}
if ( $aplicar && $creados ) {
	file_put_contents( '/www/rocaingenieria.com.ar/backups-roca/20261124/borradores.json', wp_json_encode( $creados ) );
}
