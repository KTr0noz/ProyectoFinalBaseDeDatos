-- =====================================================================
-- Usuario de PostgreSQL que usa la aplicación PHP para conectarse.
-- Ejecutar DESPUÉS de crear la base con ventas_internet.sql:
--
--   sudo -u postgres psql -d proyecto < usuario_app.sql
--
-- (Si se vuelve a crear la base, hay que ejecutar este archivo otra vez.)
-- =====================================================================

DO $$
BEGIN
    IF NOT EXISTS (SELECT 1 FROM pg_roles WHERE rolname = 'app_ventas') THEN
        CREATE ROLE app_ventas LOGIN PASSWORD 'ventas2026' NOINHERIT;
    ELSE
        ALTER ROLE app_ventas NOINHERIT;
    END IF;
END
$$;

GRANT CONNECT ON DATABASE proyecto TO app_ventas;
GRANT USAGE ON SCHEMA public TO app_ventas;

REVOKE ALL PRIVILEGES ON ALL TABLES IN SCHEMA public FROM app_ventas;
REVOKE ALL PRIVILEGES ON ALL SEQUENCES IN SCHEMA public FROM app_ventas;

GRANT SELECT ON ciudad, categoria, articulo, venta, detalle_venta, forma_pago TO app_ventas;
GRANT SELECT (id_cliente, nombre, apellido, email, telefono, direccion, id_ciudad) ON cliente TO app_ventas;

DO $$
BEGIN
    IF EXISTS (SELECT 1 FROM pg_roles WHERE rolname = 'rol_visitante') THEN
        REVOKE rol_visitante, rol_cliente, rol_admin FROM app_ventas;
        ALTER ROLE app_ventas NOINHERIT;
        GRANT rol_visitante, rol_cliente, rol_admin TO app_ventas;
    END IF;
END
$$;
