# Proyecto #13: Sistema de Ventas por Internet (Código 5465)

Aplicación web de comercio electrónico (E-Commerce) en **PHP nativo (última versión)** con proxy **Nginx** y base de datos relacional **PostgreSQL**, containerizada en **Docker** y lista para desplegar en **Railway**.

**Universidad de los Llanos — Ingeniería de Sistemas**  
**Asignatura:** Bases de Datos (2026-II) — **Docente:** Ing. Jesús Reyes Carvajal

### Autores
- **Juan Esteban Ramos Triviño** — `160005465`
- **Andrés Jerónimo Puerta** — `160005490`
- **David Santiago Díaz** — `160005319`

---

## Estructura del Repositorio

```text
├── css/                        # Hojas de estilo de la aplicación web
├── docs/                       # Documentación académica del proyecto
│   ├── Diagramas/              # Modelos MER y Relacional (3FN)
│   ├── Enunciado/              # Enunciado oficial del curso
│   └── Informe/                # Informe técnico final (PDF y DOCX)
├── includes/                   # Conexión PDO, validaciones y plantillas PHP
├── scripts/
│   └── seed_railway.py         # Script para inicializar las 7 tablas, triggers y datos reales en Railway
├── sql/
│   └── proyecto_ventas_5465.sql # Script DDL, Triggers, DML y Diccionario de Datos en PostgreSQL
├── api.php                     # Endpoint JSON con estado de conexión y métricas en vivo
├── articulos.php               # Gestión del catálogo de artículos y stock
├── categorias.php              # Gestión de categorías comerciales
├── ciudades.php                # Gestión de ciudades y códigos postales
├── clientes.php                # Gestión de clientes compradores
├── config.php                  # Configuración de conexión a PostgreSQL (soporta DATABASE_URL)
├── db.php                      # Conector PDO auxiliar para endpoints JSON
├── Dockerfile                  # Imagen PHP-FPM (Alpine) + Nginx + extensiones PostgreSQL (pdo_pgsql, pgsql)
├── entrypoint.sh               # Script de arranque para PHP-FPM y Nginx con puerto dinámico ($PORT)
├── index.php                   # Dashboard principal de la tienda
├── nginx.conf.template         # Configuración de proxy inverso Nginx con soporte CORS
├── pagos.php                   # Gestión y validación de formas de pago
├── venta.php                   # Detalle de orden de venta, facturación e inventario
└── ventas.php                  # Listado general y creación de órdenes de venta
```

---

## Modelo de Base de Datos (3FN en PostgreSQL)

El esquema relacional consta de **7 tablas** normalizadas en Tercera Forma Normal (3FN):

1. **`ciudad`** (`id_ciudad`, `nombre`, `departamento`, `pais`, `codigo_postal`)
2. **`cliente`** (`id_cliente`, `nombre`, `apellido`, `email`, `telefono`, `direccion`, `id_ciudad`)
3. **`categoria`** (`id_categoria`, `nombre`, `descripcion`)
4. **`articulo`** (`id_articulo`, `nombre`, `descripcion`, `precio`, `stock`, `id_categoria`)
5. **`venta`** (`id_venta`, `fecha`, `estado`, `direccion_envio`, `id_cliente`)
6. **`detalle_venta`** (`id_venta`, `id_articulo`, `cantidad`)
7. **`forma_pago`** (`id_pago`, `tipo`, `monto`, `fecha_pago`, `estado`, `id_venta`)

### Disparadores (Triggers) implementados
- **`trg_descontar_stock_venta`**: Verifica existencias antes de insertar en `detalle_venta` y descuenta automáticamente el stock del artículo.
- **`trg_validar_fecha_pago`**: Garantiza que `fecha_pago >= venta.fecha` al registrar o actualizar pagos en `forma_pago`.

---

## Ejecución con Docker

```powershell
# 1. Construir la imagen Docker
docker build -t ecommerce-5465 .

# 2. Iniciar el contenedor en el puerto 8080
docker run -d --name ecommerce-5465 -p 8080:8080 ecommerce-5465
```

- **Aplicación Web E-Commerce:** `http://localhost:8080/`
- **Endpoint JSON (API / Estado):** `http://localhost:8080/api.php`

---

## Despliegue en Railway

1. Sube este repositorio a **GitHub**.
2. En **Railway**, selecciona **New Project → Deploy from GitHub repo**.
3. Railway detectará automáticamente el `Dockerfile`, inyectará la variable `$PORT` en Nginx y conectará la aplicación al servicio PostgreSQL mediante `DATABASE_URL`.
