-- =====================================================================
-- MIGRACIÓN: IMÁGENES DE ARTÍCULOS Y "MIS DATOS" DEL CLIENTE
-- Se ejecuta DESPUÉS de ventas_internet.sql y login_roles.sql.
-- Idempotente: puede ejecutarse varias veces sin borrar datos.
-- =====================================================================

-- ---------------------------------------------------------------------
-- 1. Imagen de cada artículo (relación 1 a 0..1 con articulo)
--    Se guarda en la base (BYTEA) y no en el disco del servidor, porque
--    en Railway los archivos subidos se pierden en cada despliegue.
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS articulo_imagen (
    id_articulo  INT4         NOT NULL,
    tipo         VARCHAR(20)  NOT NULL,
    datos        BYTEA        NOT NULL,
    actualizada  TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT pk_articulo_imagen PRIMARY KEY (id_articulo),
    CONSTRAINT fk_articulo_imagen_articulo FOREIGN KEY (id_articulo)
        REFERENCES articulo (id_articulo)
        ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT ck_articulo_imagen_tipo
        CHECK (tipo IN ('image/jpeg', 'image/png', 'image/webp')),
    CONSTRAINT ck_articulo_imagen_tamano
        CHECK (octet_length(datos) BETWEEN 1 AND 2097152)
);

GRANT SELECT ON articulo_imagen TO rol_cliente;
GRANT ALL PRIVILEGES ON articulo_imagen TO rol_admin;

-- ---------------------------------------------------------------------
-- 2. "Mis datos": el cliente corrige SOLO su propia fila.
--    rol_cliente no tiene UPDATE sobre cliente; esta función SECURITY
--    DEFINER actualiza únicamente la fila del cliente en sesión
--    (app.id_cliente, el mismo valor que usan las políticas RLS).
--    La cédula no se cambia aquí porque es la llave de sus ventas.
-- ---------------------------------------------------------------------
CREATE OR REPLACE FUNCTION fn_actualizar_mis_datos(
    p_nombre     VARCHAR,
    p_apellido   VARCHAR,
    p_email      VARCHAR,
    p_telefono   VARCHAR,
    p_direccion  VARCHAR,
    p_id_ciudad  INT4
)
RETURNS VOID
SECURITY DEFINER
SET search_path = public
AS $$
DECLARE
    v_id_cliente INT4 := NULLIF(current_setting('app.id_cliente', true), '')::INT4;
BEGIN
    IF v_id_cliente IS NULL THEN
        RAISE EXCEPTION 'No hay un cliente con sesión iniciada';
    END IF;

    UPDATE cliente
    SET nombre    = p_nombre,
        apellido  = p_apellido,
        email     = p_email,
        telefono  = p_telefono,
        direccion = p_direccion,
        id_ciudad = p_id_ciudad
    WHERE id_cliente = v_id_cliente;

    IF NOT FOUND THEN
        RAISE EXCEPTION 'El cliente % no existe', v_id_cliente;
    END IF;
END;
$$ LANGUAGE plpgsql;

REVOKE ALL ON FUNCTION fn_actualizar_mis_datos(VARCHAR, VARCHAR, VARCHAR, VARCHAR, VARCHAR, INT4) FROM PUBLIC;
GRANT EXECUTE ON FUNCTION fn_actualizar_mis_datos(VARCHAR, VARCHAR, VARCHAR, VARCHAR, VARCHAR, INT4) TO rol_cliente;

-- ---------------------------------------------------------------------
-- 3. Usuario de la aplicación (si existe): misma lectura que en login_roles.sql
-- ---------------------------------------------------------------------
DO $$
BEGIN
    IF EXISTS (SELECT 1 FROM pg_roles WHERE rolname = 'app_ventas') THEN
        GRANT SELECT ON articulo_imagen TO app_ventas;
    END IF;
END
$$;
