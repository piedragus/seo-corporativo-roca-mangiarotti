#!/bin/bash
# Pide todas las URLs del sitemap de rocaingenieria.com.ar (escritorio y celular) para que WP Rocket
# tenga la caché armada antes de que llegue una visita real. Uso: tools/calentar_cache.sh
B=https://rocaingenieria.com.ar
MOV='Mozilla/5.0 (Linux; Android 11) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/130.0 Mobile Safari/537.36'
for sm in $(curl -s "$B/sitemap_index.xml" | grep -o '<loc>[^<]*' | cut -c6-); do
  curl -s "$sm" | grep -o '<loc>[^<]*' | cut -c6-
done | grep -vE '\.(avif|webp|jpe?g|png|gif)$' | sort -u | while read -r u; do
  curl -s -o /dev/null -w "%{http_code} %{time_total}s $u\n" "$u"
  curl -s -o /dev/null -A "$MOV" "$u"
done
