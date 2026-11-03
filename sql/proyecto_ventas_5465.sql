-- ============================================================================
-- UNIVERSIDAD DE LOS LLANOS — INGENIERÍA DE SISTEMAS
-- CURSO: BASES DE DATOS (2026-II) — DOCENTE: JESÚS REYES CARVAJAL
-- PROYECTO #13: SISTEMA DE VENTAS POR INTERNET
-- INTEGRANTES:
--   • Andrés Jerónimo Puerta — 160005490
--   • David Santiago Díaz    — 160005319
--   • Juan Esteban Ramos Triviño — 160005465
-- MOTOR: PostgreSQL
-- ============================================================================

-- Limpieza previa (opcional para re-ejecutar el script limpio)
DROP TABLE IF EXISTS forma_pago CASCADE;
DROP TABLE IF EXISTS detalle_venta CASCADE;
DROP TABLE IF EXISTS venta CASCADE;
DROP TABLE IF EXISTS articulo CASCADE;
DROP TABLE IF EXISTS categoria CASCADE;
DROP TABLE IF EXISTS cliente CASCADE;
DROP TABLE IF EXISTS ciudad CASCADE;

-- ============================================================================
-- 1. DEFINICIÓN DE TABLAS Y REGLAS DE INTEGRIDAD (DDL)
-- ============================================================================

-- 1.1 Tabla CIUDAD
CREATE TABLE ciudad (
    id_ciudad SERIAL NOT NULL,
    nombre VARCHAR(80) NOT NULL,
    departamento VARCHAR(80) NOT NULL,
    pais VARCHAR(60) NOT NULL DEFAULT 'Colombia',
    codigo_postal VARCHAR(15) NOT NULL,
    CONSTRAINT pk_ciudad PRIMARY KEY (id_ciudad)
);

COMMENT ON COLUMN ciudad.id_ciudad IS 'Identificador único de la ciudad (PK)';
COMMENT ON COLUMN ciudad.nombre IS 'Nombre oficial del municipio o ciudad';
COMMENT ON COLUMN ciudad.departamento IS 'Departamento o estado al que pertenece la ciudad';
COMMENT ON COLUMN ciudad.pais IS 'País de ubicación de la ciudad';
COMMENT ON COLUMN ciudad.codigo_postal IS 'Código postal de la zona geográfica';

-- 1.2 Tabla CLIENTE (Relación 1:N con CIUDAD -> "UBICADO EN")
CREATE TABLE cliente (
    id_cliente INT NOT NULL,
    nombre VARCHAR(80) NOT NULL,
    apellido VARCHAR(80) NOT NULL,
    email VARCHAR(120) NOT NULL,
    telefono VARCHAR(20) NOT NULL,
    direccion VARCHAR(150) NOT NULL,
    id_ciudad INT NOT NULL,
    CONSTRAINT pk_cliente PRIMARY KEY (id_cliente),
    CONSTRAINT uq_cliente_email UNIQUE (email),
    CONSTRAINT ck_cliente_id CHECK (id_cliente > 0),
    CONSTRAINT fk_cliente_ciudad FOREIGN KEY (id_ciudad)
        REFERENCES ciudad (id_ciudad)
        ON DELETE RESTRICT ON UPDATE CASCADE
);

COMMENT ON COLUMN cliente.id_cliente IS 'Documento de identidad o código único del cliente (PK)';
COMMENT ON COLUMN cliente.nombre IS 'Nombres del cliente comprador';
COMMENT ON COLUMN cliente.apellido IS 'Apellidos del cliente comprador';
COMMENT ON COLUMN cliente.email IS 'Correo electrónico único de contacto y cuenta del cliente';
COMMENT ON COLUMN cliente.telefono IS 'Número telefónico o celular de contacto';
COMMENT ON COLUMN cliente.direccion IS 'Dirección de residencia principal del cliente';
COMMENT ON COLUMN cliente.id_ciudad IS 'Ciudad de ubicación del cliente (FK hacia ciudad)';

-- 1.3 Tabla CATEGORIA
CREATE TABLE categoria (
    id_categoria SERIAL NOT NULL,
    nombre VARCHAR(80) NOT NULL,
    descripcion VARCHAR(255),
    CONSTRAINT pk_categoria PRIMARY KEY (id_categoria),
    CONSTRAINT uq_categoria_nombre UNIQUE (nombre)
);

COMMENT ON COLUMN categoria.id_categoria IS 'Identificador único de la categoría (PK)';
COMMENT ON COLUMN categoria.nombre IS 'Nombre de la línea o categoría de artículos';
COMMENT ON COLUMN categoria.descripcion IS 'Descripción general de los artículos de la categoría';

-- 1.4 Tabla ARTICULO (Relación 1:N con CATEGORIA -> "CLASIFICA")
CREATE TABLE articulo (
    id_articulo SERIAL NOT NULL,
    nombre VARCHAR(120) NOT NULL,
    descripcion VARCHAR(255),
    precio NUMERIC(12,2) NOT NULL,
    stock INT NOT NULL DEFAULT 0,
    id_categoria INT NOT NULL,
    CONSTRAINT pk_articulo PRIMARY KEY (id_articulo),
    CONSTRAINT ck_articulo_precio CHECK (precio > 0),
    CONSTRAINT ck_articulo_stock CHECK (stock >= 0),
    CONSTRAINT fk_articulo_categoria FOREIGN KEY (id_categoria)
        REFERENCES categoria (id_categoria)
        ON DELETE RESTRICT ON UPDATE CASCADE
);

COMMENT ON COLUMN articulo.id_articulo IS 'Identificador único del artículo en catálogo (PK)';
COMMENT ON COLUMN articulo.nombre IS 'Nombre comercial del artículo';
COMMENT ON COLUMN articulo.descripcion IS 'Especificaciones y detalles del artículo';
COMMENT ON COLUMN articulo.precio IS 'Precio unitario actual de venta (mayor a cero)';
COMMENT ON COLUMN articulo.stock IS 'Cantidad de unidades disponibles en inventario (no negativo)';
COMMENT ON COLUMN articulo.id_categoria IS 'Categoría a la que pertenece el artículo (FK hacia categoria)';

-- 1.5 Tabla VENTA (Relación 1:N con CLIENTE -> "GENERA")
-- Nota: Se eliminó el atributo calculado "total" y la entidad "Sitio_Web" según corrección del profesor.
CREATE TABLE venta (
    id_venta SERIAL NOT NULL,
    fecha DATE NOT NULL DEFAULT CURRENT_DATE,
    estado VARCHAR(30) NOT NULL DEFAULT 'Pendiente',
    direccion_envio VARCHAR(150) NOT NULL,
    id_cliente INT NOT NULL,
    CONSTRAINT pk_venta PRIMARY KEY (id_venta),
    CONSTRAINT ck_venta_estado CHECK (estado IN ('Pendiente', 'Pagada', 'Enviada', 'Entregada', 'Cancelada')),
    CONSTRAINT fk_venta_cliente FOREIGN KEY (id_cliente)
        REFERENCES cliente (id_cliente)
        ON DELETE RESTRICT ON UPDATE CASCADE
);

COMMENT ON COLUMN venta.id_venta IS 'Número único de orden o factura de venta (PK)';
COMMENT ON COLUMN venta.fecha IS 'Fecha en que se registra la venta por internet';
COMMENT ON COLUMN venta.estado IS 'Estado de seguimiento de la venta (Pendiente, Pagada, Enviada, Entregada, Cancelada)';
COMMENT ON COLUMN venta.direccion_envio IS 'Dirección física de entrega del pedido';
COMMENT ON COLUMN venta.id_cliente IS 'Cliente que realiza la compra (FK hacia cliente)';

-- 1.6 Tabla DETALLE_VENTA (Nace de la relación N:M entre ARTICULO y VENTA)
-- Nota: Solo conserva el atributo propio "cantidad" junto con la PK compuesta (id_venta, id_articulo).
CREATE TABLE detalle_venta (
    id_venta INT NOT NULL,
    id_articulo INT NOT NULL,
    cantidad INT NOT NULL,
    CONSTRAINT pk_detalle_venta PRIMARY KEY (id_venta, id_articulo),
    CONSTRAINT ck_detalle_cantidad CHECK (cantidad > 0),
    CONSTRAINT fk_detalle_venta FOREIGN KEY (id_venta)
        REFERENCES venta (id_venta)
        ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_detalle_articulo FOREIGN KEY (id_articulo)
        REFERENCES articulo (id_articulo)
        ON DELETE RESTRICT ON UPDATE CASCADE
);

COMMENT ON COLUMN detalle_venta.id_venta IS 'Identificador de la venta asociada (PK/FK hacia venta)';
COMMENT ON COLUMN detalle_venta.id_articulo IS 'Identificador del artículo vendido (PK/FK hacia articulo)';
COMMENT ON COLUMN detalle_venta.cantidad IS 'Cantidad de unidades compradas del artículo (mayor a cero)';

-- 1.7 Tabla FORMA_PAGO (Relación 1:N con VENTA -> "GENERA")
-- Nota: Unifica Pago y Método de Pago según corrección del profesor (PSE, Tarjeta, Efecty, etc.)
CREATE TABLE forma_pago (
    id_pago SERIAL NOT NULL,
    tipo VARCHAR(40) NOT NULL,
    monto NUMERIC(12,2) NOT NULL,
    fecha_pago DATE NOT NULL DEFAULT CURRENT_DATE,
    estado VARCHAR(30) NOT NULL DEFAULT 'Aprobado',
    id_venta INT NOT NULL,
    CONSTRAINT pk_forma_pago PRIMARY KEY (id_pago),
    CONSTRAINT ck_forma_pago_tipo CHECK (tipo IN ('PSE', 'Tarjeta Visa', 'Tarjeta Mastercard', 'Efecty', 'Transferencia Bancaria')),
    CONSTRAINT ck_forma_pago_monto CHECK (monto > 0),
    CONSTRAINT ck_forma_pago_estado CHECK (estado IN ('Pendiente', 'Aprobado', 'Rechazado')),
    CONSTRAINT fk_forma_pago_venta FOREIGN KEY (id_venta)
        REFERENCES venta (id_venta)
        ON DELETE RESTRICT ON UPDATE CASCADE
);

COMMENT ON COLUMN forma_pago.id_pago IS 'Identificador único del registro de pago (PK)';
COMMENT ON COLUMN forma_pago.tipo IS 'Medio de pago utilizado (PSE, Tarjeta Visa, Tarjeta Mastercard, Efecty, Transferencia Bancaria)';
COMMENT ON COLUMN forma_pago.monto IS 'Valor pagado en la transacción (mayor a cero)';
COMMENT ON COLUMN forma_pago.fecha_pago IS 'Fecha de procesamiento del pago';
COMMENT ON COLUMN forma_pago.estado IS 'Estado de la transacción de pago (Pendiente, Aprobado, Rechazado)';
COMMENT ON COLUMN forma_pago.id_venta IS 'Venta a la que corresponde el pago (FK hacia venta)';


-- ============================================================================
-- 1.8 DISPARADORES (TRIGGERS) Y FUNCIONES — REQUISITO TÉCNICO DEL PROYECTO
-- ============================================================================

-- Disparador 1: Validar stock disponible y descontar automáticamente del inventario
-- cada vez que se registra un artículo en DETALLE_VENTA.
CREATE OR REPLACE FUNCTION fn_descontar_stock_venta()
RETURNS TRIGGER AS $$
DECLARE
    v_stock_actual INT;
BEGIN
    SELECT stock INTO v_stock_actual
    FROM articulo
    WHERE id_articulo = NEW.id_articulo;

    IF v_stock_actual < NEW.cantidad THEN
        RAISE EXCEPTION 'Stock insuficiente para el artículo ID %. Disponible: %, Solicitado: %',
            NEW.id_articulo, v_stock_actual, NEW.cantidad;
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

-- Disparador 2: Restricción de fechas (validar que fecha_pago >= fecha de la venta)
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


-- ============================================================================
-- 2. INSERCIÓN DE DATOS DE PRUEBA (DML - INSERT)
-- ============================================================================

INSERT INTO ciudad (nombre, departamento, pais, codigo_postal) VALUES
('Villavicencio', 'Meta', 'Colombia', '500001'),
('Bogotá D.C.', 'Cundinamarca', 'Colombia', '110111'),
('Medellín', 'Antioquia', 'Colombia', '050001'),
('Cali', 'Valle del Cauca', 'Colombia', '760001'),
('Acacías', 'Meta', 'Colombia', '507001');

INSERT INTO cliente (id_cliente, nombre, apellido, email, telefono, direccion, id_ciudad) VALUES
(1001, 'Juan Esteban', 'Ramos Triviño', 'juan.ramos@unillanos.edu.co', '3105551234', 'Calle 15 # 32-10 Barzal', 1),
(1002, 'Andrés Jerónimo', 'Puerta', 'andres.puerta@unillanos.edu.co', '3114445678', 'Carrera 40 # 20-15 Buque', 1),
(1003, 'David Santiago', 'Díaz', 'david.diaz@unillanos.edu.co', '3123339876', 'Av. Circunvalar # 12-08', 2),
(1004, 'Laura Valentina', 'Gómez', 'laura.gomez@gmail.com', '3152223344', 'Calle 10 # 45-22 Poblado', 3),
(1005, 'Carlos Eduardo', 'Mendoza', 'carlos.mendoza@hotmail.com', '3201112233', 'Carrera 23 # 14-05 Centro', 5);

INSERT INTO categoria (nombre, descripcion) VALUES
('Computadores y Portátiles', 'Equipos de cómputo de escritorio y portátiles'),
('Periféricos y Accesorios', 'Teclados, ratones, monitores y diademas'),
('Componentes de Hardware', 'Procesadores, memorias RAM, discos SSD y tarjetas gráficas'),
('Redes y Conectividad', 'Routers, switches y adaptadores inalámbricos');

INSERT INTO articulo (nombre, descripcion, precio, stock, id_categoria) VALUES
('Portátil ASUS VivoBook 15', 'Intel Core i5, 16GB RAM, 512GB SSD', 2450000.00, 15, 1),
('Monitor LG UltraGear 24"', 'Panel IPS 144Hz Full HD 1ms', 780000.00, 20, 2),
('Teclado Mecánico Redragon Kumara', 'Switch rojo, retroiluminación RGB', 185000.00, 35, 2),
('Mouse Logitech G203 Lightsync', 'Sensor óptico 8000 DPI USB', 110000.00, 50, 2),
('Disco Sólido SSD Kingston 1TB', 'NVMe M.2 PCIe 4.0 lectura 3500MB/s', 290000.00, 40, 3),
('Router TP-Link Archer AX23', 'Wi-Fi 6 Doble Banda Gigabit', 260000.00, 25, 4);

INSERT INTO venta (fecha, estado, direccion_envio, id_cliente) VALUES
('2026-10-15', 'Entregada', 'Calle 15 # 32-10 Barzal, Villavicencio', 1001),
('2026-10-18', 'Enviada', 'Carrera 40 # 20-15 Buque, Villavicencio', 1002),
('2026-10-20', 'Pagada', 'Av. Circunvalar # 12-08, Bogotá D.C.', 1003),
('2026-10-21', 'Pendiente', 'Calle 10 # 45-22 Poblado, Medellín', 1004);

INSERT INTO detalle_venta (id_venta, id_articulo, cantidad) VALUES
(1, 1, 1), -- Venta 1: 1 Portátil ASUS (2,450,000)
(1, 4, 2), -- Venta 1: 2 Mouse Logitech (220,000) -> Total Venta 1 = 2,670,000
(2, 2, 1), -- Venta 2: 1 Monitor LG (780,000)
(2, 3, 1), -- Venta 2: 1 Teclado Mecánico (185,000) -> Total Venta 2 = 965,000
(3, 5, 2), -- Venta 3: 2 SSD Kingston (580,000)
(4, 6, 1); -- Venta 4: 1 Router TP-Link (260,000)

INSERT INTO forma_pago (tipo, monto, fecha_pago, estado, id_venta) VALUES
('PSE', 2670000.00, '2026-10-15', 'Aprobado', 1),
('Tarjeta Visa', 965000.00, '2026-10-18', 'Aprobado', 2),
('Efecty', 580000.00, '2026-10-20', 'Aprobado', 3),
('PSE', 260000.00, '2026-10-21', 'Pendiente', 4);


-- ============================================================================
-- 3. ACTUALIZACIÓN Y CONTROL DEL SISTEMA (DML - UPDATE)
-- ============================================================================

-- 3.1 Actualizar el stock de un artículo y su precio
UPDATE articulo
SET stock = stock - 1
WHERE id_articulo = 1;

-- 3.2 Actualizar el seguimiento de una venta (de 'Pagada' a 'Enviada')
UPDATE venta
SET estado = 'Enviada'
WHERE id_venta = 3;


-- ============================================================================
-- 4. CONSULTAS DEL PROYECTO (SEGUIMIENTO, CÁLCULO DE TOTALES Y REPORTES)
-- ============================================================================

-- 4.1 Consulta de factura/detalle con cálculo dinámico de SUBTOTAL por línea
-- (Como se eliminaron "precio_unitario" y "subtotal" de detalle_venta, se calculan con JOIN)
SELECT
    v.id_venta,
    v.fecha,
    c.nombre || ' ' || c.apellido AS cliente,
    a.nombre AS articulo,
    dv.cantidad,
    a.precio AS precio_unitario,
    (dv.cantidad * a.precio) AS subtotal_calculado
FROM detalle_venta dv
INNER JOIN venta v ON dv.id_venta = v.id_venta
INNER JOIN cliente c ON v.id_cliente = c.id_cliente
INNER JOIN articulo a ON dv.id_articulo = a.id_articulo
ORDER BY v.id_venta, a.nombre;

-- 4.2 Consulta de seguimiento de ventas con cálculo dinámico del TOTAL DE LA VENTA y su FORMA DE PAGO
SELECT
    v.id_venta,
    v.fecha,
    c.nombre || ' ' || c.apellido AS cliente,
    ciu.nombre AS ciudad_cliente,
    v.direccion_envio,
    v.estado AS estado_venta,
    SUM(dv.cantidad * a.precio) AS total_venta_calculado,
    fp.tipo AS forma_pago,
    fp.estado AS estado_pago
FROM venta v
INNER JOIN cliente c ON v.id_cliente = c.id_cliente
INNER JOIN ciudad ciu ON c.id_ciudad = ciu.id_ciudad
INNER JOIN detalle_venta dv ON v.id_venta = dv.id_venta
INNER JOIN articulo a ON dv.id_articulo = a.id_articulo
LEFT JOIN forma_pago fp ON v.id_venta = fp.id_venta
GROUP BY v.id_venta, v.fecha, c.nombre, c.apellido, ciu.nombre, v.direccion_envio, v.estado, fp.tipo, fp.estado
ORDER BY v.id_venta;

-- 4.3 Total recaudado según forma de pago (PSE, Tarjeta Visa, Efecty, etc.)
SELECT
    tipo AS forma_pago,
    COUNT(id_pago) AS numero_transacciones,
    SUM(monto) AS total_recaudado
FROM forma_pago
WHERE estado = 'Aprobado'
GROUP BY tipo
ORDER BY total_recaudado DESC;

-- 4.4 Subconsulta: Artículos cuyo precio es superior al promedio del catálogo
SELECT
    id_articulo,
    nombre,
    precio,
    stock
FROM articulo
WHERE precio > (SELECT AVG(precio) FROM articulo);


-- ============================================================================
-- 5. GENERACIÓN AUTOMÁTICA DEL DICCIONARIO DE DATOS EN POSTGRESQL
-- (Consulta oficial de clase - Prof. Jesús Reyes Carvajal)
-- ============================================================================
SELECT
    t1.TABLE_NAME AS tabla_nombre,
    t1.COLUMN_NAME AS columna_nombre,
    t1.COLUMN_DEFAULT AS columna_defecto,
    t1.IS_NULLABLE AS columna_nulo,
    t1.DATA_TYPE AS columna_tipo_dato,
    COALESCE(t1.NUMERIC_PRECISION, t1.CHARACTER_MAXIMUM_LENGTH) AS columna_longitud,
    PG_CATALOG.COL_DESCRIPTION(t2.OID, t1.DTD_IDENTIFIER::int) AS columna_descripcion,
    t1.DOMAIN_NAME AS columna_dominio
FROM
    INFORMATION_SCHEMA.COLUMNS t1
    INNER JOIN PG_CLASS t2 ON (t2.RELNAME = t1.TABLE_NAME)
WHERE
    t1.TABLE_SCHEMA = 'public'
ORDER BY
    t1.TABLE_NAME,
    t1.ORDINAL_POSITION;
