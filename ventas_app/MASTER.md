# MASTER.md · Sistema de diseño de Ventas por Internet

Fuente única de los valores visuales de la aplicación. Todo color, tamaño, espacio, radio y
duración de `css/estilos.css` sale de los tokens `:root` descritos aquí.

## Tesis validadas

**Visual.** Interfaz clara de trastienda: superficies blancas, fondo gris frío y tinta casi negra.
El color se reserva para significar (los cinco estados de la venta, los tres del pago y el saldo
pendiente en rojo); por eso los botones principales son de tinta y no de un azul de marca.
IBM Plex Sans, guardada dentro de la app (`css/fuentes`) para que funcione sin internet; sus
dígitos tienen todos el mismo ancho, así que pesos y cédulas se alinean en columna. Títulos en 600,
montos grandes frente a texto de 15px. Densidad media-alta sobre una base de 4px. Componentes
planos con bordes de 1px, radios pequeños (4px controles, 8px bloques, píldora solo en estados),
sin sombras. Rediseño completo de la capa visual.

**Estructura (variante A + bandeja de la C).** Riel lateral de tinta con el menú agrupado por
tarea (Operación, Catálogo, Directorio). Tablas directo sobre la página, sin tarjeta alrededor.
Inicio: bandeja «Por hacer» con las tareas que salen de los estados reales, embudo de estados que
muestra dónde está el dinero, una línea de cifras y las últimas ventas. Se conservan las
direcciones de cada página, los nombres del menú, los campos de los formularios, el pie y la
lógica PHP.

**Interacción.** Rápido y seco: 120ms en hover y foco, 200ms en la entrada de avisos, curva
`cubic-bezier(0.2, 0, 0, 1)`. Botones y filas solo cambian de tono, nada escala. Los avisos entran
una vez con fundido y 4px de desplazamiento. Nada se anima con el scroll. Prohibido: rebote,
escalado en hover, parallax, animaciones infinitas, revelados al hacer scroll. Con movimiento
reducido: sin desplazamiento ni transiciones.

**Patrones permitidos:** etiquetas de columna en mayúsculas con espaciado; un punto de color dentro
de cada etiqueta de estado (informa un estado real); el riel de tinta; separadores de 1px entre
filas de tabla (hacen falta para seguir una fila a través de las columnas).

## Color

| Token | Valor | Uso | Contraste |
|---|---|---|---|
| `--fondo` | `#F3F4F2` | áreas rellenas, hover de filas | tinta encima 16,1:1 |
| `--superficie` | `#FFFFFF` | página | tinta encima 17,8:1 |
| `--tinta` | `#16181C` | texto, botón principal, riel | |
| `--tinta-hover` | `#2B2F36` | hover del botón principal | blanco encima 13,6:1 |
| `--tinta-activa` | `#000000` | botón principal presionado | |
| `--suave` | `#5B6068` | texto secundario | 6,3:1 sobre blanco, 5,7:1 sobre fondo |
| `--linea` | `#E2E4E0` | separadores | |
| `--linea-fuerte` | `#8A8F96` | borde de controles | 3,3:1 sobre blanco |
| `--nav` | `#B8BCC4` | menú inactivo sobre tinta | 9,3:1 |
| `--nav-grupo` | `#8A8F96` | títulos de grupo sobre tinta | 5,6:1 |
| `--nav-hover` | `#24272D` | fondo del ítem en hover | |
| `--peligro` | `#B42318` | eliminar, saldo pendiente | 6,6:1 sobre blanco |
| `--peligro-fondo` | `#FBE9E7` | hover de eliminar | |
| `--peligro-activo` | `#F6D5D1` | eliminar presionado | |

Estados (texto sobre fondo):

| Estado | Texto | Fondo | Contraste |
|---|---|---|---|
| Pendiente (venta y pago) | `#4A4F57` | `#EEEFEC` | 7,1:1 |
| Pagada | `#1F4FA8` | `#E5EEFB` | 6,5:1 |
| Enviada | `#5B34A8` | `#F0EAFB` | 7,1:1 |
| Entregada / Aprobado / aviso ok | `#1C6B3A` | `#E3F2E8` | 5,6:1 |
| Cancelada / Rechazado / aviso error | `#A3271B` | `#FBE9E7` | 6,3:1 |
| Aviso info | `#1F4FA8` | `#E5EEFB` | 6,5:1 |

## Tipografía

Familia: `"IBM Plex Sans", "Segoe UI", Roboto, Ubuntu, system-ui, sans-serif`
(archivos `css/fuentes/ibm-plex-sans-latin-{400,500,600}-normal.woff2`, licencia OFL).

| Rol | Tamaño / peso |
|---|---|
| Monto grande | 28px / 600 |
| Título de página (h1) | 22px / 600 |
| Sección (h2) | 17px / 600 |
| Cuerpo | 15px / 400, interlineado 1.5 |
| Control, tabla | 14px |
| Etiqueta de campo | 13px / 600 |
| Columna, grupo | 12px / 600, mayúsculas, espaciado 0.05em |

## Espacio, radios, sombras

Escala: 4, 8, 12, 16, 24, 32, 48px (`--e1` … `--e12`).
Radios: `--r-sm` 4px (controles), `--r-md` 8px (bloques, avisos), `--r-pill` 999px (estados).
Sombras: ninguna.
Riel: 216px de ancho. Contenido: máximo 1120px.

## Movimiento

| Token | Valor |
|---|---|
| `--t-rapido` | 120ms (hover, foco, filas) |
| `--t-aviso` | 200ms (entrada de avisos) |
| `--curva` | `cubic-bezier(0.2, 0, 0, 1)` |
| Desplazamiento de aviso | 4px |

## Componentes base

- Botón principal: fondo tinta, texto blanco. Hover tinta-hover, presionado negro, foco anillo de
  2px de tinta separado 2px, deshabilitado línea con texto suave.
- Botón secundario: fondo blanco, borde linea-fuerte. Hover fondo, presionado línea.
- Botón peligro: fondo blanco, texto y borde peligro. Hover peligro-fondo.
- Campo: borde linea-fuerte, radio 4px. Hover borde suave, foco anillo de tinta, deshabilitado
  fondo.
- Estado: píldora con punto de color al inicio.
- En pantallas táctiles: botones, enlaces del menú y campos de al menos 44px de alto.
