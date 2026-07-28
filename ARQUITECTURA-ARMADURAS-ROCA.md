# Arquitectura Armaduras Roca (reconversion)

## Rol dentro del grupo
Armaduras Roca deja de ser fabricante de estructuras grandes (eso pasa a Tek-Met) y se reconvierte en
tienda web de productos metalicos de obra, estandar y semi a medida, de venta rapida.

## Stack
WordPress + WooCommerce (mismo stack que Roca Ingenieria, sin curva de aprendizaje nueva).
Para el producto configurable (estribo especial) se necesita un plugin de campos adicionales /
precio dinamico para que el cliente cargue medidas y el precio se calcule al toque.

## Estructura de la pagina (todo es una sola tienda, no hay catalogo institucional aparte ni cotizador separado)

### 1. Home
Directa: catalogo/tienda y producto configurable visibles arriba de todo, sin texto largo institucional.
El que entra ya sabe lo que busca (precio rapido).

### 2. Tienda (WooCommerce)
Dos tipos de producto conviviendo en el mismo catalogo:

a) Productos de stock fijo (medida estandar, precio y disponibilidad ya cargados):
   - Estribos (varias medidas)
   - Mallas especiales
   - Armadura redonda
   - Columnas soldadas estandar
   - Andamios

b) Productos configurables (medida especial, sin catalogo fijo):
   - Ej. "Estribo especial": el cliente carga sus propias medidas en el producto mismo,
     el precio se calcula al toque, y compra directo ahi sin pasar por cotizacion manual.
   - Frontera de negocio: Armaduras Roca SI hace medidas especiales dentro de su rubro
     (estribos mas gruesos, columnetas mas grandes ya armadas). Lo que NO hace y se deriva
     a Tek-Met es proyectos de ingenieria completos o estructuras de gran escala.

### 3. Preguntas frecuentes (FAQ)
- Cuanto tarda la entrega
- Hacen envios / zona de cobertura
- Hacen medidas especiales -> si, estribos mas gruesos y columnetas mas grandes ya armadas;
  solo para proyectos de ingenieria completos o estructuras de gran escala se deriva a Tek-Met (con link).

## Notas
- Esta reconversion se ejecuta DESPUES de que el sitio de Tek-Met este vivo (para poder derivar
  ahi el contenido de fabricacion de estructuras grandes que hoy todavia esta en Armaduras Roca).
- Ver ARQUITECTURA-TEKMET.md para la contraparte (fabricacion bajo plano / recipientes / estructuras grandes).
