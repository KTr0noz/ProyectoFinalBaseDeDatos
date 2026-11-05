-- =====================================================================
-- MIGRACIÓN: AUTENTICACIÓN, ROLES DE CLIENTE, PERMISOS Y RLS
-- Archivo idempotente (sin DROP TABLE/DATABASE): puede ejecutarse múltiples veces.
-- =====================================================================

ALTER TABLE cliente
    ADD COLUMN IF NOT EXISTS password_hash TEXT NULL,
    ADD COLUMN IF NOT EXISTS rol VARCHAR(20) NOT NULL DEFAULT 'cliente';

DO $$
BEGIN
    IF NOT EXISTS (
        SELECT 1 FROM pg_constraint WHERE conname = 'ck_cliente_rol'
    ) THEN
        ALTER TABLE cliente
            ADD CONSTRAINT ck_cliente_rol
            CHECK (rol IN ('admin', 'cliente'));
    END IF;

    IF NOT EXISTS (
        SELECT 1 FROM pg_constraint WHERE conname = 'ck_cliente_admin_con_clave'
    ) THEN
        ALTER TABLE cliente
            ADD CONSTRAINT ck_cliente_admin_con_clave
            CHECK (rol <> 'admin' OR password_hash IS NOT NULL);
    END IF;
END
$$;

-- SECURITY DEFINER permite descontar stock cuando detalle_venta es insertado por rol_cliente
-- sin otorgarle privilegios UPDATE directos sobre la tabla articulo.
CREATE OR REPLACE FUNCTION fn_descontar_stock_venta()
RETURNS TRIGGER
SECURITY DEFINER
SET search_path = public
AS $$
DECLARE
    v_stock INT4;
BEGIN
    SELECT stock INTO v_stock
    FROM articulo
    WHERE id_articulo = NEW.id_articulo
    FOR UPDATE;

    IF v_stock IS NULL THEN
        RAISE EXCEPTION 'El artículo % no existe', NEW.id_articulo;
    END IF;

    IF v_stock < NEW.cantidad THEN
        RAISE EXCEPTION 'Stock insuficiente para el artículo %: disponible %, solicitado %',
            NEW.id_articulo, v_stock, NEW.cantidad;
    END IF;

    UPDATE articulo
    SET stock = stock - NEW.cantidad
    WHERE id_articulo = NEW.id_articulo;

    RETURN NEW;
END;
$$ LANGUAGE plpgsql;

DO $$
BEGIN
    IF NOT EXISTS (SELECT 1 FROM pg_roles WHERE rolname = 'rol_visitante') THEN
        CREATE ROLE rol_visitante NOLOGIN;
    END IF;

    IF NOT EXISTS (SELECT 1 FROM pg_roles WHERE rolname = 'rol_cliente') THEN
        CREATE ROLE rol_cliente NOLOGIN;
    END IF;

    IF NOT EXISTS (SELECT 1 FROM pg_roles WHERE rolname = 'rol_admin') THEN
        CREATE ROLE rol_admin NOLOGIN;
    END IF;
END
$$;

REVOKE ALL PRIVILEGES ON ALL TABLES IN SCHEMA public FROM rol_visitante, rol_cliente, rol_admin;
REVOKE ALL PRIVILEGES ON ALL SEQUENCES IN SCHEMA public FROM rol_visitante, rol_cliente, rol_admin;

GRANT USAGE ON SCHEMA public TO rol_visitante, rol_cliente, rol_admin;

GRANT SELECT ON ciudad TO rol_visitante;
GRANT SELECT (id_cliente, email, password_hash, rol) ON cliente TO rol_visitante;
GRANT INSERT (id_cliente, nombre, apellido, email, telefono, direccion, id_ciudad, password_hash) ON cliente TO rol_visitante;

GRANT SELECT ON articulo, categoria, ciudad TO rol_cliente;
GRANT SELECT (id_cliente, nombre, apellido, email, telefono, direccion, id_ciudad) ON cliente TO rol_cliente;
GRANT SELECT, INSERT ON venta, detalle_venta, forma_pago TO rol_cliente;
GRANT USAGE, SELECT ON SEQUENCE venta_id_venta_seq, forma_pago_id_pago_seq TO rol_cliente;

GRANT ALL PRIVILEGES ON ciudad, cliente, categoria, articulo, venta, detalle_venta, forma_pago TO rol_admin;
GRANT ALL PRIVILEGES ON ALL SEQUENCES IN SCHEMA public TO rol_admin;

-- Row Level Security (RLS) en venta, detalle_venta y forma_pago
ALTER TABLE venta ENABLE ROW LEVEL SECURITY;
ALTER TABLE detalle_venta ENABLE ROW LEVEL SECURITY;
ALTER TABLE forma_pago ENABLE ROW LEVEL SECURITY;

DROP POLICY IF EXISTS pol_venta_admin ON venta;
CREATE POLICY pol_venta_admin ON venta
    FOR ALL TO rol_admin
    USING (true)
    WITH CHECK (true);

DROP POLICY IF EXISTS pol_detalle_venta_admin ON detalle_venta;
CREATE POLICY pol_detalle_venta_admin ON detalle_venta
    FOR ALL TO rol_admin
    USING (true)
    WITH CHECK (true);

DROP POLICY IF EXISTS pol_forma_pago_admin ON forma_pago;
CREATE POLICY pol_forma_pago_admin ON forma_pago
    FOR ALL TO rol_admin
    USING (true)
    WITH CHECK (true);

DROP POLICY IF EXISTS pol_venta_cliente ON venta;
CREATE POLICY pol_venta_cliente ON venta
    FOR ALL TO rol_cliente
    USING (id_cliente = NULLIF(current_setting('app.id_cliente', true), '')::INT4)
    WITH CHECK (id_cliente = NULLIF(current_setting('app.id_cliente', true), '')::INT4);

DROP POLICY IF EXISTS pol_detalle_venta_cliente ON detalle_venta;
CREATE POLICY pol_detalle_venta_cliente ON detalle_venta
    FOR ALL TO rol_cliente
    USING (
        EXISTS (
            SELECT 1 FROM venta v
            WHERE v.id_venta = detalle_venta.id_venta
              AND v.id_cliente = NULLIF(current_setting('app.id_cliente', true), '')::INT4
        )
    )
    WITH CHECK (
        EXISTS (
            SELECT 1 FROM venta v
            WHERE v.id_venta = detalle_venta.id_venta
              AND v.id_cliente = NULLIF(current_setting('app.id_cliente', true), '')::INT4
        )
    );

DROP POLICY IF EXISTS pol_forma_pago_cliente ON forma_pago;
CREATE POLICY pol_forma_pago_cliente ON forma_pago
    FOR ALL TO rol_cliente
    USING (
        EXISTS (
            SELECT 1 FROM venta v
            WHERE v.id_venta = forma_pago.id_venta
              AND v.id_cliente = NULLIF(current_setting('app.id_cliente', true), '')::INT4
        )
    )
    WITH CHECK (
        EXISTS (
            SELECT 1 FROM venta v
            WHERE v.id_venta = forma_pago.id_venta
              AND v.id_cliente = NULLIF(current_setting('app.id_cliente', true), '')::INT4
        )
    );

DO $$
BEGIN
    EXECUTE format('GRANT rol_visitante, rol_cliente, rol_admin TO %I', current_user);

    IF EXISTS (SELECT 1 FROM pg_roles WHERE rolname = 'app_ventas') THEN
        REVOKE rol_visitante, rol_cliente, rol_admin FROM app_ventas;
        ALTER ROLE app_ventas NOINHERIT;
        REVOKE ALL PRIVILEGES ON ALL TABLES IN SCHEMA public FROM app_ventas;
        REVOKE ALL PRIVILEGES ON ALL SEQUENCES IN SCHEMA public FROM app_ventas;
        GRANT USAGE ON SCHEMA public TO app_ventas;
        GRANT SELECT ON ciudad, categoria, articulo, venta, detalle_venta, forma_pago TO app_ventas;
        GRANT SELECT (id_cliente, nombre, apellido, email, telefono, direccion, id_ciudad) ON cliente TO app_ventas;
        GRANT rol_visitante, rol_cliente, rol_admin TO app_ventas;

        DROP POLICY IF EXISTS pol_venta_lectura_app ON venta;
        CREATE POLICY pol_venta_lectura_app ON venta FOR SELECT TO app_ventas USING (true);

        DROP POLICY IF EXISTS pol_detalle_lectura_app ON detalle_venta;
        CREATE POLICY pol_detalle_lectura_app ON detalle_venta FOR SELECT TO app_ventas USING (true);

        DROP POLICY IF EXISTS pol_pago_lectura_app ON forma_pago;
        CREATE POLICY pol_pago_lectura_app ON forma_pago FOR SELECT TO app_ventas USING (true);
    END IF;
END
$$;
