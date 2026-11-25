#!/bin/bash
# Revierte el feed de Merchant con IVA y descripciones sin doble escape, 2026-11-25. Correr en el server:
#   bash /www/rocaingenieria.com.ar/backups-roca/20261125/feed-iva/revertir_feed_iva_20261125.sh aplicar
# (sin argumentos solo muestra qué haría)
set -e
B=/www/rocaingenieria.com.ar/backups-roca/20261125/feed-iva
P=/www/rocaingenieria.com.ar/htdocs
cd "$P"
if [ "$1" != "aplicar" ]; then
  echo "Simulación: borraría $P/wp-content/mu-plugins/roca-feed-google-iva.php, los transients roca_feed_google_iva y roca_feed_google"
  echo "y purgaría WP Rocket. El feed vuelve a salir de roca-feed-google.php (1.35 USD; copia en $B/mu-plugins-antes y $B/feed-antes.xml). Usar: $0 aplicar"
  exit 0
fi
rm -f wp-content/mu-plugins/roca-feed-google-iva.php
wp transient delete roca_feed_google_iva || true
wp transient delete roca_feed_google || true
wp eval 'if (function_exists("rocket_clean_domain")) { rocket_clean_domain(); echo "WP Rocket purgado\n"; }'
echo "Listo. Esperar 1-2 min; después: curl -s 'https://rocaingenieria.com.ar/feed-google-merchant.xml?regenerar=1' | grep g:price  (debe dar 1.35 USD)"
