#!/bin/bash
# Revierte el precio con/sin IVA en la web y el schema con IVA, 2026-11-25. En el server:
#   bash /www/rocaingenieria.com.ar/backups-roca/20261125/precio-iva-web/revertir_precio_iva_web_20261125.sh aplicar
set -e
B=/www/rocaingenieria.com.ar/backups-roca/20261125/precio-iva-web
P=/www/rocaingenieria.com.ar/htdocs
cd "$P"
if [ "$1" != "aplicar" ]; then
  echo "Simulación: restauraría post_content de 25760, 26714, 26873, 26985, 26986, las metas Yoast de las fichas, la madre y la categoría 105, y llms.txt desde $B/originales.json;"
  echo "borraría wp-content/mu-plugins/roca-seo-precio-iva.php (schema vuelve a 1.35 sin IVA) y purgaría WP Rocket. Dump completo: $B/db-antes.sql.gz. Usar: $0 aplicar"
  exit 0
fi
rm -f wp-content/mu-plugins/roca-seo-precio-iva.php
wp eval-file "$B/precio_iva_web_20261125.php" revertir
wp db query --ssl=0 "DELETE FROM $(wp db prefix)yoast_indexable WHERE (object_type='post' AND object_id IN (25760,26714,26873)) OR (object_type='term' AND object_id=105)" || true
echo "Listo. Esperar 1-2 min y verificar con curl."
