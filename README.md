# Sistema de Ventas por Internet (Proyecto #13 · Cód. 5465)

Proyecto #13 del curso **Bases de Datos 2026-II**, **Universidad de los Llanos** (Ingeniería de Sistemas).

Registro y control transaccional de la venta de artículos por Internet: administración del catálogo de artículos y categorías, registro de clientes por ciudades, seguimiento del ciclo de vida de las ventas y gestión de formas de pago (`PSE`, `Tarjeta Visa`, `Tarjeta Mastercard`, `Efecty` y `Transferencia Bancaria`).  
Base de datos relacional normalizada en **Tercera Forma Normal (3FN)** sobre **PostgreSQL** (desplegada en **Railway**) y aplicación web nativa en **PHP** servida con **Nginx + Docker**.

### Autores
- **Juan Esteban Ramos Triviño** — Código `160005465`
- **Andrés Jerónimo Puerta** — Código `160005490`
- **David Santiago Díaz** — Código `160005319`

---

## Estructura del Repositorio

| Ruta | Descripción |
| :--- | :--- |
| `ventas_internet.sql` | Script DDL/DML de PostgreSQL: crea las 7 tablas, restricciones `CHECK`, llaves foráneas y disparadores (`triggers`) |
| `login_roles.sql` | Migración idempotente: agrega `password_hash` y `rol` a `cliente`, crea roles de BD (`rol_visitante`, `rol_cliente`, `rol_admin`) y define `fn_descontar_stock_venta()` como `SECURITY DEFINER` |
| `scripts/crear_admin.php` | Script CLI idempotente (fuera del document root) que crea o actualiza la cuenta de administrador leyendo variables de entorno |
| `backup-proyecto.sql` | Respaldo (`pg_dump`) de la base de datos |
| `Informe_BD_proyecto.pdf` | Informe técnico: Modelo Entidad-Relación, Modelo Relacional (3FN) y Diccionario de Datos |
| `Dockerfile` | Imagen Docker con PHP-FPM (Alpine), Nginx y extensiones `pdo_pgsql` / `pgsql` |
| `nginx.conf.template` | Configuración de proxy Nginx con soporte para `$PORT` dinámico y CORS |
| `entrypoint.sh` | Script de arranque de PHP-FPM y Nginx |
| `ventas_app/` | Aplicación web en PHP sin frameworks |
| `ventas_app/config.php` | Configuración central de conexión a PostgreSQL (`DATABASE_URL` / Railway) |
| `ventas_app/includes/` | Conexión PDO (`db.php`), autenticación y roles (`auth.php`), validaciones/reglas de negocio (`funciones.php`) y plantillas (`encabezado.php`, `encabezado_tienda.php`, `pie.php`) |
| `ventas_app/login.php` | Inicio de sesión por correo y contraseña |
| `ventas_app/registro.php` | Registro público de clientes compradores (`rol = 'cliente'`) |
| `ventas_app/salir.php` | Cierre de sesión seguro vía POST + CSRF |
| `ventas_app/tienda.php` | Catálogo de artículos disponibles para clientes (`stock > 0`) |
| `ventas_app/articulo.php` | Ficha individual de artículo para agregar al carrito |
| `ventas_app/carrito.php` | Carrito de compras en sesión y confirmación transaccional del pedido |
| `ventas_app/mis_compras.php` | Historial y detalle de compras del cliente autenticado |
| `ventas_app/index.php` | Inicio (Admin): Bandeja «Por hacer», embudo de estados, indicadores y últimas ventas |
| `ventas_app/ventas.php` | Listado general de órdenes de venta con totales calculados (3FN) y creación de pedidos |
| `ventas_app/venta.php` | Ficha detallada de una venta: líneas de artículos, pagos y seguimiento |
| `ventas_app/pagos.php` | Gestión y validación de pagos (`forma_pago`) |
| `ventas_app/articulos.php` | Gestión del catálogo de artículos e inventario (`articulo`) |
| `ventas_app/categorias.php` | Gestión de categorías comerciales (`categoria`) |
| `ventas_app/clientes.php` | Gestión de clientes compradores (`cliente`) |
| `ventas_app/ciudades.php` | Gestión de ciudades (`ciudad`) |
| `ventas_app/api.php` | Endpoint JSON de estado y conteo de registros en tiempo real |

---

## Migración de Roles y Creación del Administrador

1. **Aplicar la migración SQL idempotente (`login_roles.sql`)**:

```bash
psql "$DATABASE_URL" -f login_roles.sql
```

2. **Configurar las variables de entorno** (en `.env` local o en el panel de variables de Railway):

```ini
ADMIN_EMAIL=correo_admin@dominio.com
ADMIN_PASSWORD=tu_clave_segura
ADMIN_CEDULA=numero_documento
ADMIN_NOMBRE=Nombre
ADMIN_APELLIDO=Apellido
ADMIN_CIUDAD=Villavicencio
```

3. **Ejecutar el script CLI para crear o actualizar el administrador**:

```bash
php scripts/crear_admin.php
```

---

## Ejecución con Docker

```powershell
# 1. Construir la imagen
docker build -t php-postgres-railway .

# 2. Iniciar el contenedor en el puerto 8080
docker run -d --name mi-php-railway -p 8080:8080 php-postgres-railway
```

- **Aplicación Web:** <http://localhost:8080/>
- **Estado JSON (API):** <http://localhost:8080/api.php>
