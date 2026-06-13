# Setup — WooCommerce Content Generator
## rocaingenieria.com.ar

---

## Paso 1 — Instalar Ollama

1. Ir a https://ollama.com/download y bajar el instalador para Windows
2. Instalarlo (siguiente, siguiente, instalar)
3. Abrir una terminal (CMD o PowerShell) y ejecutar:

```
ollama pull mistral
```

Esto baja el modelo (~4 GB). Tarda según tu conexión, hacelo antes de dormir si querés.

Para verificar que funciona:
```
ollama run mistral "Hola, ¿cómo estás?"
```

Debería responderte. Con Ctrl+D salís.

---

## Paso 2 — Obtener las credenciales de WooCommerce

1. Entrá al admin de WordPress: https://rocaingenieria.com.ar/wp-admin
2. Ir a **WooCommerce → Ajustes → Avanzado → REST API**
3. Hacer clic en **"Agregar clave"**
4. Completar:
   - Descripción: `Script generador local`
   - Usuario: tu usuario admin
   - Permisos: **Lectura/Escritura**
5. Clic en **"Generar clave API"**
6. ⚠️ **COPIAR en ese momento** la Consumer Key y Consumer Secret — no las vas a ver de nuevo

---

## Paso 3 — Instalar Python y dependencias

Si no tenés Python instalado:
- Bajar de https://python.org/downloads (tildar "Add to PATH" en la instalación)

Luego en la terminal:
```
pip install requests woocommerce
```

---

## Paso 4 — Configurar el script

Abrir `woo_content_generator.py` con cualquier editor (Notepad, VS Code, etc.)
y completar estas tres líneas al principio:

```python
WC_KEY    = "ck_..."    # la Consumer Key del paso 2
WC_SECRET = "cs_..."    # la Consumer Secret del paso 2
```

El modelo ya viene configurado como `mistral`. Si querés probar con llama3.1:
```python
OLLAMA_MODEL = "llama3.1:8b"
# (primero hacer: ollama pull llama3.1:8b)
```

---

## Paso 5 — Hacer un test en seco antes de dejarlo correr

En el script, cambiar temporalmente:
```python
DRY_RUN = True
```

Ejecutar:
```
python woo_content_generator.py
```

Va a mostrar en pantalla todo lo que *haría* sin modificar nada en el sitio.
Si se ve bien, volver a poner `DRY_RUN = False`.

---

## Paso 5b — Configurar Yoast SEO (title, meta description y focus keyword)

Yoast no expone sus campos por la WooCommerce API sola. Necesitás dos cosas:

### 1. Instalar el plugin "WP REST API – Yoast SEO"
1. Ir a **WordPress → Plugins → Añadir nuevo**
2. Buscar: `wp-rest-yoast-meta`
3. Instalar y activar **"WP REST API – Yoast SEO"** (autor: Niels Garve)

### 2. Crear una Application Password en WordPress
1. Ir a **WordPress → Usuarios → Tu perfil**
2. Bajar hasta la sección **"Contraseñas de aplicación"**
3. En "Nombre de la nueva contraseña" escribir: `Script generador local`
4. Clic en **"Añadir nueva contraseña de aplicación"**
5. ⚠️ Copiar la contraseña que aparece (formato: `xxxx xxxx xxxx xxxx xxxx`)
6. En el script completar:
```python
WP_USER = "tu_usuario_admin"
WP_PASS = "xxxx xxxx xxxx xxxx xxxx"   # con los espacios incluidos está bien
```

---

## Paso 6 — Dejarlo correr de noche

1. Asegurarse de que Ollama está corriendo (si cerró la terminal, abrir una nueva y escribir `ollama serve`)
2. Abrir otra terminal y ejecutar:
```
python woo_content_generator.py
```
3. Dejar la PC encendida
4. A la mañana revisar el archivo `woo_generator.log` que se crea en la misma carpeta

---

## Resultado esperado

- Todas las **categorías sin descripción** van a tener su texto SEO cargado
- Todos los **productos sin descripción** van a tener descripción corta y larga
- Los cambios quedan **publicados** (con el mismo status que tenía cada item)
- El log registra todo: qué se procesó, qué falló, los keywords generados

---

## Solución de problemas comunes

| Error | Causa probable | Solución |
|---|---|---|
| "Ollama no responde" | Ollama no está corriendo | Ejecutar `ollama serve` en otra terminal |
| "No se pudo conectar a WooCommerce" | Credenciales incorrectas o API deshabilitada | Verificar las keys y que REST API esté activa en WooCommerce |
| JSON inválido (reintentos) | El modelo tuvo un mal output | Normal ocasionalmente, el script reintenta 3 veces automáticamente |
| Muy lento | GPU no acelerada | Verificar en Task Manager que la GPU AMD tenga uso durante la ejecución |

---

## Tip: forzar uso de GPU en Ollama

Por defecto Ollama detecta la GPU automáticamente. Para verificar que la está usando,
mientras corre el script revisar el Task Manager → GPU → Radeon — debería mostrar uso > 0%.

Si no la usa, ejecutar Ollama con:
```
set OLLAMA_GPU_LAYERS=99
ollama serve
```
