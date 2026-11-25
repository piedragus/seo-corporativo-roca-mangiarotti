#!/usr/bin/env python3
"""Extrae las reglas *_tb_header del CSS que Divi imprime al pie (style id="et-builder-module-design-tb-773-...").
Uso: python3 tools/extraer_css_header_divi.py [URL] > header.css
Sirve para regenerar la copia de mu-plugins/roca-velocidad-header.php si se edita el encabezado en Divi."""
import re, sys, urllib.request
url = sys.argv[1] if len(sys.argv) > 1 else 'https://rocaingenieria.com.ar/granalla-de-acero/?nc=1'
h = urllib.request.urlopen(urllib.request.Request(url, headers={'User-Agent': 'Mozilla/5.0'})).read().decode()
m = re.search(r'<style id="et-builder-module-design-tb-773[^"]*"[^>]*>(.*?)</style>', h, re.S)
if not m:
    sys.exit('no encontré el bloque de CSS del Theme Builder (¿cambió el layout o se activó el CSS estático?)')
def split(css):
    out, d, s = [], 0, 0
    for j, c in enumerate(css):
        if c == '{': d += 1
        elif c == '}':
            d -= 1
            if d == 0: out.append(css[s:j + 1]); s = j + 1
    return out
keep = []
for r in split(m.group(1)):
    if r.startswith('@media'):
        sub = [x for x in split(r[r.find('{') + 1:-1]) if '_tb_header' in x.split('{')[0]]
        if sub: keep.append(r[:r.find('{') + 1] + ''.join(sub) + '}')
    elif '_tb_header' in r.split('{')[0]:
        keep.append(r)
print(''.join(keep))
