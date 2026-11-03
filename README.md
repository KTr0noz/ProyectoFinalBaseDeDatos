# Sistema de Ventas por Internet

Proyecto #13 del curso Bases de Datos 2026-II, Universidad de los Llanos.

Registro y control de la venta de artículos por Internet: actualización y consulta de artículos,
clientes, ciudades y seguimiento de ventas, con sus pagos (PSE, Visa, Mastercard, Efecty y
transferencia bancaria). Base de datos en PostgreSQL y aplicación web en PHP sin framework.

**Autor:** Jerson Andres Gomez Molano · código 160005432

## Contenido

| Ruta | Qué es |
|---|---|
| `ventas_internet.sql` | Crea la base `proyecto`: tablas, restricciones, disparadores y datos de prueba |
| `backup-proyecto.sql` | Respaldo (`pg_dump`) de la base con sus datos |
| `Informe_BD_proyecto.pdf` | Informe técnico: modelo relacional y diccionario de datos |
| `ventas_app/` | Aplicación PHP (ver `ventas_app/LEEME.txt`) |
| `ventas_app/MASTER.md` | Sistema de diseño de la interfaz |

## Cómo ejecutarla (Linux)

Requisitos: PostgreSQL y PHP con las extensiones `pdo_pgsql` y `mbstring`
(`sudo apt install postgresql php php-pgsql php-mbstring`).

```bash
# 1. Crear la base (borra y vuelve a crear la base "proyecto")
sudo -u postgres psql < ventas_internet.sql

# 2. Crear el usuario que usa la aplicación
sudo -u postgres psql -d proyecto < ventas_app/usuario_app.sql

# 3. Iniciar la aplicación
cd ventas_app
php -S localhost:8000
```

Luego abrir <http://localhost:8000>. Los datos de conexión están en `ventas_app/config.php`.
