-- =====================================================================
-- PROYECTO #13: SISTEMA DE VENTAS POR INTERNET (Cód. 5465)
-- Script de creación de la base de datos en PostgreSQL
-- Basado en el Informe Técnico (Modelo Relacional + Diccionario de Datos)
--
-- Uso desde la terminal:
--   psql -U postgres -f ventas_internet.sql
-- =====================================================================

-- ---------------------------------------------------------------------
-- 0. Crear la base de datos y conectarse a ella
-- ---------------------------------------------------------------------
DROP DATABASE IF EXISTS proyecto;
CREATE DATABASE proyecto;
\c proyecto

-- ---------------------------------------------------------------------
-- 1. Tabla CIUDAD
-- ---------------------------------------------------------------------
CREATE TABLE ciudad (
    id_ciudad      SERIAL       NOT NULL,
    nombre         VARCHAR(80)  NOT NULL,
    departamento   VARCHAR(80)  NOT NULL,
    pais           VARCHAR(60)  NOT NULL DEFAULT 'Colombia',
    codigo_postal  VARCHAR(15)  NOT NULL,
    CONSTRAINT pk_ciudad PRIMARY KEY (id_ciudad)
);

-- ---------------------------------------------------------------------
-- 2. Tabla CLIENTE
-- ---------------------------------------------------------------------
CREATE TABLE cliente (
    id_cliente  INT4          NOT NULL,
    nombre      VARCHAR(80)   NOT NULL,
    apellido    VARCHAR(80)   NOT NULL,
    email       VARCHAR(120)  NOT NULL,
    telefono    VARCHAR(20)   NOT NULL,
    direccion   VARCHAR(150)  NOT NULL,
    id_ciudad   INT4          NOT NULL,
    CONSTRAINT pk_cliente       PRIMARY KEY (id_cliente),
    CONSTRAINT ck_cliente_id    CHECK (id_cliente > 0),
    CONSTRAINT uq_cliente_email UNIQUE (email),
    CONSTRAINT fk_cliente_ciudad FOREIGN KEY (id_ciudad)
        REFERENCES ciudad (id_ciudad)
        ON DELETE RESTRICT ON UPDATE CASCADE
);

-- ---------------------------------------------------------------------
-- 3. Tabla CATEGORIA
-- ---------------------------------------------------------------------
CREATE TABLE categoria (
    id_categoria  SERIAL        NOT NULL,
    nombre        VARCHAR(80)   NOT NULL,
    descripcion   VARCHAR(255)  NULL,
    CONSTRAINT pk_categoria        PRIMARY KEY (id_categoria),
    CONSTRAINT uq_categoria_nombre UNIQUE (nombre)
);

-- ---------------------------------------------------------------------
-- 4. Tabla ARTICULO
-- ---------------------------------------------------------------------
CREATE TABLE articulo (
    id_articulo   SERIAL         NOT NULL,
    nombre        VARCHAR(120)   NOT NULL,
    descripcion   VARCHAR(255)   NULL,
    precio        NUMERIC(12,2)  NOT NULL,
    stock         INT4           NOT NULL DEFAULT 0,
    id_categoria  INT4           NOT NULL,
    CONSTRAINT pk_articulo        PRIMARY KEY (id_articulo),
    CONSTRAINT ck_articulo_precio CHECK (precio > 0),
    CONSTRAINT ck_articulo_stock  CHECK (stock >= 0),
    CONSTRAINT fk_articulo_categoria FOREIGN KEY (id_categoria)
        REFERENCES categoria (id_categoria)
        ON DELETE RESTRICT ON UPDATE CASCADE
);

-- ---------------------------------------------------------------------
-- 5. Tabla VENTA
--    (el total NO se almacena: se calcula con consultas, por 3FN)
-- ---------------------------------------------------------------------
CREATE TABLE venta (
    id_venta         SERIAL        NOT NULL,
    fecha            DATE          NOT NULL DEFAULT CURRENT_DATE,
    estado           VARCHAR(30)   NOT NULL DEFAULT 'Pendiente',
    direccion_envio  VARCHAR(150)  NOT NULL,
    id_cliente       INT4          NOT NULL,
    CONSTRAINT pk_venta        PRIMARY KEY (id_venta),
    CONSTRAINT ck_venta_estado CHECK (estado IN
        ('Pendiente', 'Pagada', 'Enviada', 'Entregada', 'Cancelada')),
    CONSTRAINT fk_venta_cliente FOREIGN KEY (id_cliente)
        REFERENCES cliente (id_cliente)
        ON DELETE RESTRICT ON UPDATE CASCADE
);

-- ---------------------------------------------------------------------
-- 6. Tabla DETALLE_VENTA (tabla puente de la relación N:M)
-- ---------------------------------------------------------------------
CREATE TABLE detalle_venta (
    id_venta     INT4  NOT NULL,
    id_articulo  INT4  NOT NULL,
    cantidad     INT4  NOT NULL,
    CONSTRAINT pk_detalle_venta     PRIMARY KEY (id_venta, id_articulo),
    CONSTRAINT ck_detalle_cantidad  CHECK (cantidad > 0),
    CONSTRAINT fk_detalle_venta FOREIGN KEY (id_venta)
        REFERENCES venta (id_venta)
        ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_detalle_articulo FOREIGN KEY (id_articulo)
        REFERENCES articulo (id_articulo)
        ON DELETE RESTRICT ON UPDATE CASCADE
);

-- ---------------------------------------------------------------------
-- 7. Tabla FORMA_PAGO
-- ---------------------------------------------------------------------
CREATE TABLE forma_pago (
    id_pago     SERIAL         NOT NULL,
    tipo        VARCHAR(40)    NOT NULL,
    monto       NUMERIC(12,2)  NOT NULL,
    fecha_pago  DATE           NOT NULL DEFAULT CURRENT_DATE,
    estado      VARCHAR(30)    NOT NULL DEFAULT 'Aprobado',
    id_venta    INT4           NOT NULL,
    CONSTRAINT pk_forma_pago        PRIMARY KEY (id_pago),
    CONSTRAINT ck_forma_pago_tipo   CHECK (tipo IN
        ('PSE', 'Tarjeta Visa', 'Tarjeta Mastercard', 'Efecty', 'Transferencia Bancaria')),
    CONSTRAINT ck_forma_pago_monto  CHECK (monto > 0),
    CONSTRAINT ck_forma_pago_estado CHECK (estado IN
        ('Pendiente', 'Aprobado', 'Rechazado')),
    CONSTRAINT fk_forma_pago_venta FOREIGN KEY (id_venta)
        REFERENCES venta (id_venta)
        ON DELETE RESTRICT ON UPDATE CASCADE
);

-- =====================================================================
-- TRIGGERS (Sección 5.1 del informe)
-- =====================================================================

-- B. Control y descuento automático de inventario
CREATE OR REPLACE FUNCTION fn_descontar_stock_venta()
RETURNS TRIGGER AS $$
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

CREATE TRIGGER trg_descontar_stock_venta
BEFORE INSERT ON detalle_venta
FOR EACH ROW
EXECUTE FUNCTION fn_descontar_stock_venta();

-- C. Validación de fechas: fecha_pago >= fecha de la venta
CREATE OR REPLACE FUNCTION fn_validar_fecha_pago()
RETURNS TRIGGER AS $$
DECLARE
    v_fecha_venta DATE;
BEGIN
    SELECT fecha INTO v_fecha_venta
    FROM venta
    WHERE id_venta = NEW.id_venta;

    IF NEW.fecha_pago < v_fecha_venta THEN
        RAISE EXCEPTION 'La fecha de pago (%) no puede ser anterior a la fecha de la venta (%)',
            NEW.fecha_pago, v_fecha_venta;
    END IF;

    RETURN NEW;
END;
$$ LANGUAGE plpgsql;

CREATE TRIGGER trg_validar_fecha_pago
BEFORE INSERT OR UPDATE ON forma_pago
FOR EACH ROW
EXECUTE FUNCTION fn_validar_fecha_pago();

-- =====================================================================
-- DATOS DE PRUEBA
-- =====================================================================
INSERT INTO ciudad (nombre, departamento, codigo_postal) VALUES
    ('Villavicencio', 'Meta',            '500001'),
    ('Bogotá',        'Cundinamarca',    '110111'),
    ('Medellín',      'Antioquia',       '050001');

INSERT INTO cliente (id_cliente, nombre, apellido, email, telefono, direccion, id_ciudad) VALUES
    (1012345678, 'Laura',  'Pérez',  'laura.perez@mail.com',  '3101234567', 'Cra 33 # 15-20',  1),
    (1023456789, 'Carlos', 'Gómez',  'carlos.gomez@mail.com', '3209876543', 'Calle 80 # 10-45', 2),
    (1034567890, 'Ana',    'Torres', 'ana.torres@mail.com',   '3005558899', 'Av 70 # 45-12',   3);

INSERT INTO categoria (nombre, descripcion) VALUES
    ('Computadores', 'Portátiles y equipos de escritorio'),
    ('Celulares',    'Smartphones y accesorios'),
    ('Accesorios',   'Mouse, teclados, audífonos y similares');

INSERT INTO articulo (nombre, descripcion, precio, stock, id_categoria) VALUES
    ('Portátil Lenovo IdeaPad 3', '15.6", 8 GB RAM, 512 GB SSD', 2350000.00, 10, 1),
    ('Samsung Galaxy A55',        '128 GB, 8 GB RAM',            1650000.00, 15, 2),
    ('Mouse Logitech M170',       'Inalámbrico',                   45000.00, 50, 3),
    ('Audífonos JBL Tune 520BT',  'Bluetooth',                    199000.00, 20, 3);

INSERT INTO venta (fecha, estado, direccion_envio, id_cliente) VALUES
    ('2026-10-01', 'Pagada',    'Cra 33 # 15-20',   1012345678),
    ('2026-10-05', 'Enviada',   'Calle 80 # 10-45', 1023456789),
    ('2026-10-10', 'Pendiente', 'Av 70 # 45-12',    1034567890);

-- Al insertar el detalle, el trigger descuenta el stock automáticamente
INSERT INTO detalle_venta (id_venta, id_articulo, cantidad) VALUES
    (1, 1, 1),
    (1, 3, 2),
    (2, 2, 1),
    (2, 4, 1),
    (3, 3, 1);

INSERT INTO forma_pago (tipo, monto, fecha_pago, estado, id_venta) VALUES
    ('PSE',                2440000.00, '2026-10-01', 'Aprobado',  1),
    ('Tarjeta Visa',       1849000.00, '2026-10-06', 'Aprobado',  2),
    ('Efecty',               45000.00, '2026-10-10', 'Pendiente', 3);

-- =====================================================================
-- CONSULTA DE VERIFICACIÓN: total de cada venta (calculado, no almacenado)
-- =====================================================================
SELECT v.id_venta,
       c.nombre || ' ' || c.apellido AS cliente,
       v.fecha,
       v.estado,
       SUM(dv.cantidad * a.precio)   AS total
FROM venta v
JOIN cliente c        ON c.id_cliente  = v.id_cliente
JOIN detalle_venta dv ON dv.id_venta   = v.id_venta
JOIN articulo a       ON a.id_articulo = dv.id_articulo
GROUP BY v.id_venta, c.nombre, c.apellido, v.fecha, v.estado
ORDER BY v.id_venta;
