<?php
/**
 * Plugin Name: Roca - CSS de Divi en el <head> (CLS)
 * Description: Fuerza "Static CSS File Generation" de Divi (su valor por defecto; en este sitio estaba en off). Con off, Divi imprime el CSS de los módulos (encabezado del Theme Builder y secciones de cada página) al final del <body>: el navegador pinta primero sin esos estilos y después todo salta (CLS ~0,8 a 1 en Lighthouse). Con on, ese CSS va a un archivo en et-cache que se pide desde el <head>. No toca la base. Agregado 2026-11-24. Para revertir: borrar este archivo y limpiar la caché de Divi y de WP Rocket.
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }
add_filter( 'et_get_option_et_divi_et_pb_static_css_file', function () { return 'on'; } );
