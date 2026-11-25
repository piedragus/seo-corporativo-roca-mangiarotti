#!/bin/bash
# Avisa a IndexNow (Bing, Yandex, Seznam, Naver...) todas las URLs del sitemap de rocaingenieria.com.ar.
# La clave se sirve en /<clave>.txt (archivo en htdocs) y la usa también el mu-plugin roca-seo-schema.php,
# que avisa solo al publicar o editar productos, páginas y entradas. Este script es para envíos masivos
# (por ejemplo después de cambios grandes). Uso: tools/indexnow_ping.sh
B=https://rocaingenieria.com.ar
KEY=38879130abf5195006ae8fc21ed981e2
curl -sf "$B/$KEY.txt" | grep -q "$KEY" || { echo "la clave no responde en $B/$KEY.txt"; exit 1; }
urls=$(for sm in $(curl -s "$B/sitemap_index.xml" | grep -o '<loc>[^<]*' | cut -c6-); do
         curl -s "$sm" | grep -o '<loc>[^<]*' | cut -c6-; done | grep -vE '\.(avif|webp|jpe?g|png|gif)$' | sort -u)
json=$(printf '%s\n' $urls | python3 -c "import sys,json; print(json.dumps({'host':'rocaingenieria.com.ar','key':'$KEY','keyLocation':'$B/$KEY.txt','urlList':[l.strip() for l in sys.stdin if l.strip()]}))")
echo "$(printf '%s\n' $urls | wc -l) URLs"
curl -s -o /dev/null -w "IndexNow: HTTP %{http_code}\n" -H 'Content-Type: application/json; charset=utf-8' -d "$json" https://api.indexnow.org/indexnow
