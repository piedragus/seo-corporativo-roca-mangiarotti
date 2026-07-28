# SEO Corporativo — Grupo Roca Mangiarotti SRL

## Contexto

Roca Mangiarotti SRL es la razón social que agrupa las distintas marcas comerciales operadas por el mismo equipo. El objetivo es reestructurar la presencia web de cada marca para que se potencien entre sí (link building interno + branding claro), en lugar de competir o confundirse.

Este roadmap reemplaza el enfoque anterior del proyecto (herramientas SEO solo para rocaingenieria.com.ar) por un enfoque de grupo, cubriendo las cuatro marcas.

## Las cuatro marcas

### 1. Roca Ingeniería (rocaingenieria.com.ar)
- Rol: cabecera del grupo, industrial pesado, equipos.
- Punto de entrada principal. Contiene el menú/hover que enlaza a las demás marcas.
- Menú propuesto: botón "Nuestras Empresas" o "Grupo Roca" con desplegable hacia Línea Magna y Tek-Met, con una línea descriptiva corta por marca (adaptado a hover en desktop, tap en mobile).

### 2. Línea Magna (lineamagna.com.ar)
- Rol: mobiliario industrial.
- Marca ya independiente y consolidada, no lleva "Roca" en el nombre.
- Llevada operativamente por la mujer de Agustín.

### 3. Tek-Met (nombre nuevo, dominio a definir y verificar disponibilidad — ej. tekmet.com.ar)
- Razón social sugerida: "Tekton Metalúrgica".
- Rol: fabricación metalúrgica bajo plano, estructuras a medida, proyectos grandes y puntuales (menor volumen, mayor tamaño/complejidad).
- Absorbe la parte de fabricación de estructuras/columnas que antes estaba en Armaduras Roca.
- No lleva "Roca" en el nombre (decisión explícita por reputación/familia).

### 4. Armaduras Roca (armadurasroca.com.ar — dominio existente, bajísimo tráfico)
- Rol: reconvertido a "ferretería técnica de obra" — productos semi-estándar de reposición rápida.
- Catálogo: estribos, mallas especiales, armadura redonda, columnas soldadas estándar, andamios (matriz propia disponible).
- Feature clave: cotizador simple online (precio de hierros, costo por estribo, etc.) para simplificar y agilizar la venta directa.
- Diferenciación clara de Tek-Met: Armaduras Roca = estándar/rápido/chico. Tek-Met = a medida/grande/bajo plano.
- Mantiene el dominio actual (no se da de baja); se reposiciona el contenido.

## Estructura de interlinking

Triángulo de referencias cruzadas entre Roca Ingeniería, Línea Magna y Tek-Met (cada una linkea a las otras dos). Armaduras Roca linkea hacia Tek-Met (y viceversa) como "hermana chica" dentro de la vertical metalúrgica, evitando que se lean como competidoras.

Pendiente (para más adelante, no bloqueante): estructura de datos `sameAs` para declarar la relación entre organizaciones ante Google.

## Próximos pasos concretos

1. **Definir y registrar el dominio de Tek-Met** (verificar disponibilidad en NIC Argentina / revendedor).
2. **Rediseñar el menú de Roca Ingeniería**: agregar desplegable "Grupo Roca" con Línea Magna y Tek-Met.
3. **Redactar los catálogos de Tek-Met**: fabricación bajo plano, estructuras soldadas, ex-línea de armaduras/columnas de Armaduras Roca.
4. **Reestructurar Armaduras Roca**: recortar contenido de fabricación de estructuras grandes, quedarse con estribos/mallas/andamios/armadura redonda, sumar cotizador simple.
5. **Reciprocar los links** en Línea Magna y en el futuro sitio de Tek-Met hacia Roca Ingeniería y entre sí.
6. **Renombrar el repositorio** de `roca-ingenieria-seo-tools` a algo que refleje el alcance de grupo (ej. `seo-corporativo-roca-mangiarotti`), y migrar el stack de generación de contenido (hoy Python + Ollama) hacia un flujo con Claude Code / Claude Cowork corriendo en el servidor propio de Agustín.
7. (Opcional, no bloqueante) Implementar `sameAs` u otro marcado de organización compartida entre los cuatro sitios.

## Notas / decisiones ya tomadas
- El nombre "Roca" se retira deliberadamente de las marcas nuevas (Tek-Met, y ya antes Línea Magna).
- Tek-Met se prefirió sobre "Fáber-Met" por sonar más técnico/moderno y vender mejor.
- El desplegable de marcas en Roca Ingeniería debe llevar directo al otro dominio (sin subpágina intermedia) para no perder conversión.
