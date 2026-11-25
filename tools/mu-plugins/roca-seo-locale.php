<?php
/**
 * Plugin Name: Roca - og:locale es_AR
 * Description: Yoast arma og:locale con el idioma del sitio (es_ES); el público es argentino. Va en archivo aparte porque el hosting no recarga PHP editados (opcache.validate_timestamps=0). Agregado 2026-11-24. Para revertir: borrar este archivo.
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }
add_filter( 'wpseo_og_locale', function () { return 'es_AR'; } );
