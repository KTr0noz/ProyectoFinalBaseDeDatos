-- =====================================================================
-- Usuario de PostgreSQL que usa la aplicación PHP para conectarse.
-- Ejecutar DESPUÉS de crear la base con ventas_internet.sql:
--
--   sudo -u postgres psql -d proyecto < usuario_app.sql
--
-- (Si se vuelve a crear la base, hay que ejecutar este archivo otra vez.)
-- =====================================================================

-- Crea el usuario solo si todavía no existe
DO $$
BEGIN
    IF NOT EXISTS (SELECT 1 FROM pg_roles WHERE rolname = 'app_ventas') THEN
        CREATE ROLE app_ventas LOGIN PASSWORD 'ventas2026';
    END IF;
END
$$;

-- Permisos mínimos: conectarse y leer/escribir datos (no puede crear ni borrar tablas)
GRANT CONNECT ON DATABASE proyecto TO app_ventas;
GRANT USAGE ON SCHEMA public TO app_ventas;
GRANT SELECT, INSERT, UPDATE, DELETE ON ALL TABLES IN SCHEMA public TO app_ventas;
GRANT USAGE, SELECT ON ALL SEQUENCES IN SCHEMA public TO app_ventas;
