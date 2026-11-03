import random
from datetime import date, timedelta
import psycopg2

DATABASE_URL = "postgresql://postgres:bHSXhbjGUCeYNCyjIvdyRcXNckEJTRPZ@ballast.proxy.rlwy.net:49477/railway"

DDL_SQL = """
-- Limpieza previa
DROP TABLE IF EXISTS forma_pago CASCADE;
DROP TABLE IF EXISTS detalle_venta CASCADE;
DROP TABLE IF EXISTS venta CASCADE;
DROP TABLE IF EXISTS articulo CASCADE;
DROP TABLE IF EXISTS categoria CASCADE;
DROP TABLE IF EXISTS cliente CASCADE;
DROP TABLE IF EXISTS ciudad CASCADE;

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

-- 1.2 Tabla CLIENTE
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

-- 1.4 Tabla ARTICULO
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

-- 1.5 Tabla VENTA
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

-- 1.6 Tabla DETALLE_VENTA
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

-- 1.7 Tabla FORMA_PAGO
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

-- 1.8 DISPARADORES (TRIGGERS) Y FUNCIONES
CREATE OR REPLACE FUNCTION fn_descontar_stock_venta()
RETURNS TRIGGER AS $$
DECLARE
    v_stock_actual INT;
BEGIN
    SELECT stock INTO v_stock_actual
    FROM articulo
    WHERE id_articulo = NEW.id_articulo
    FOR UPDATE;

    IF v_stock_actual IS NULL THEN
        RAISE EXCEPTION 'El artículo ID % no existe', NEW.id_articulo;
    END IF;

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
"""

def main():
    random.seed(5465)
    conn = psycopg2.connect(DATABASE_URL)
    cur = conn.cursor()

    print("1. Creando tablas, restricciones, comentarios y triggers...")
    cur.execute(DDL_SQL)

    # 1. Ciudades reales de Colombia
    ciudades = [
        ("Villavicencio", "Meta", "Colombia", "500001"),
        ("Bogotá D.C.", "Cundinamarca", "Colombia", "110111"),
        ("Medellín", "Antioquia", "Colombia", "050001"),
        ("Cali", "Valle del Cauca", "Colombia", "760001"),
        ("Acacías", "Meta", "Colombia", "507001"),
        ("Barranquilla", "Atlántico", "Colombia", "080001"),
        ("Bucaramanga", "Santander", "Colombia", "680001"),
        ("Pereira", "Risaralda", "Colombia", "660001"),
        ("Cartagena", "Bolívar", "Colombia", "130001"),
        ("Manizales", "Caldas", "Colombia", "170001"),
        ("Ibagué", "Tolima", "Colombia", "730001"),
        ("Restrepo", "Meta", "Colombia", "501001"),
    ]
    ciudad_ids = []
    for nombre, depto, pais, cp in ciudades:
        cur.execute(
            "INSERT INTO ciudad (nombre, departamento, pais, codigo_postal) VALUES (%s, %s, %s, %s) RETURNING id_ciudad",
            (nombre, depto, pais, cp),
        )
        ciudad_ids.append((cur.fetchone()[0], nombre))

    # 2. Clientes reales (incluyendo a los autores del proyecto 5465 y clientes adicionales generados)
    clientes_base = [
        (160005465, "Juan Esteban", "Ramos Triviño", "juan.ramos@unillanos.edu.co", "3105551234", "Calle 15 # 32-10 Barzal", 1),
        (160005490, "Andrés Jerónimo", "Puerta", "andres.puerta@unillanos.edu.co", "3114445678", "Carrera 40 # 20-15 Buque", 1),
        (160005319, "David Santiago", "Díaz", "david.diaz@unillanos.edu.co", "3123339876", "Av. Circunvalar # 12-08", 2),
        (1121945102, "Laura Valentina", "Gómez Rojas", "laura.gomez@gmail.com", "3152223344", "Calle 10 # 45-22 El Poblado", 3),
        (1098765431, "Carlos Eduardo", "Mendoza Silva", "carlos.mendoza@hotmail.com", "3201112233", "Carrera 23 # 14-05 Centro", 5),
        (1018492817, "Camila Andrea", "Vargas Pineda", "camila.vargas@outlook.com", "3004128899", "Cra 15 # 85-24 Chapinero", 2),
        (1032481920, "Sebastián", "Ospina Restrepo", "s.ospina@gmail.com", "3017654321", "Circular 4 # 70-18 Laureles", 3),
        (1144092831, "Valentina", "Hurtado Caicedo", "valen.hurtado@gmail.com", "3168901234", "Av. Pasoancho # 66-14", 4),
        (1045718293, "Alejandro", "Charry Molina", "alejo.charry@yahoo.com", "3045617283", "Cra 53 # 79-40 Alto Prado", 6),
        (1095827364, "Natalia Sofía", "Rueda Mantilla", "natalia.rueda@gmail.com", "3173459876", "Calle 48 # 33-19 Cabecera", 7),
        (1088319245, "Mateo", "Giraldo Arango", "mateo.giraldo@gmail.com", "3182348765", "Av. 30 de Agosto # 42-11", 8),
        (1121887654, "Daniela", "Pachón Rey", "daniela.pachon@unillanos.edu.co", "3136789012", "Calle 37B # 29-45 La Grama", 1),
        (1049632187, "Felipe Andrés", "Castillo Mora", "felipe.castillo@gmail.com", "3219871234", "Bocagrande Cra 2 # 8-50", 9),
        (1053819204, "Mariana", "Jaramillo Mejía", "mariana.jara@gmail.com", "3145672345", "Av. Santander # 55-20", 10),
        (1110543289, "Santiago", "Trujillo Lozano", "santi.trujillo@hotmail.com", "3198765432", "Cra 5 # 60-12 Prados del Norte", 11),
        (1122654321, "Juliana", "Baquero Ortiz", "juliana.baquero@gmail.com", "3112233445", "Calle 8 # 11-30 Centro", 12),
    ]
    for cid, nom, ape, email, tel, direc, id_ciu in clientes_base:
        cur.execute(
            """INSERT INTO cliente (id_cliente, nombre, apellido, email, telefono, direccion, id_ciudad)
               VALUES (%s, %s, %s, %s, %s, %s, %s)""",
            (cid, nom, ape, email, tel, direc, id_ciu),
        )

    # 3. Categorías del E-Commerce
    categorias = [
        ("Computadores y Portátiles", "Equipos de cómputo de escritorio, portátiles empresariales y laptops gamer"),
        ("Periféricos y Accesorios", "Teclados mecánicos, ratones ópticos, monitores, cámaras web y diademas"),
        ("Componentes de Hardware", "Procesadores, memorias RAM, discos SSD NVMe, fuentes y tarjetas gráficas"),
        ("Redes y Conectividad", "Routers Wi-Fi 6, switches Gigabit, sistemas Mesh y adaptadores inalámbricos"),
        ("Smartphones y Tablets", "Teléfonos inteligentes, tabletas digitales y accesorios móviles"),
        ("Audio y Sonido", "Parlantes Bluetooth, micrófonos de estudio y audífonos inalámbricos"),
    ]
    cat_ids = []
    for nom, desc in categorias:
        cur.execute(
            "INSERT INTO categoria (nombre, descripcion) VALUES (%s, %s) RETURNING id_categoria",
            (nom, desc),
        )
        cat_ids.append(cur.fetchone()[0])

    # 4. Artículos del Catálogo (con stock amplio inicial para que el trigger trg_descontar_stock_venta descuente al vender)
    articulos = [
        ("Portátil ASUS VivoBook 15", "Intel Core i5 1235U, 16GB RAM, 512GB SSD, Pantalla 15.6 FHD", 2450000.00, 45, 1),
        ("Portátil Lenovo IdeaPad Gaming 3", "AMD Ryzen 5 6600H, RTX 3050 4GB, 16GB DDR5, 512GB SSD", 3590000.00, 30, 1),
        ("MacBook Air 13 Chip M3", "CPU 8 núcleos, GPU 8 núcleos, 16GB RAM unificada, 256GB SSD", 5290000.00, 22, 1),
        ("Monitor LG UltraGear 24\"", "Panel IPS 144Hz Full HD 1ms FreeSync Premium", 780000.00, 50, 2),
        ("Monitor Samsung Odyssey G5 27\"", "Panel VA Curvo QHD 2K 165Hz 1ms HDR10", 1150000.00, 35, 2),
        ("Teclado Mecánico Redragon Kumara K552", "Switch Outemu Rojo lineal, retroiluminación RGB, formato TKL", 185000.00, 65, 2),
        ("Mouse Logitech G203 Lightsync", "Sensor óptico 8000 DPI, 6 botones programables, RGB USB", 110000.00, 80, 2),
        ("Mouse Inalámbrico Logitech MX Master 3S", "Sensor 8000 DPI en cristal, rueda MagSpeed, Bluetooth y USB-C", 460000.00, 35, 2),
        ("Disco Sólido SSD Kingston NV2 1TB", "NVMe M.2 PCIe 4.0 lectura hasta 3500MB/s", 290000.00, 75, 3),
        ("Memoria RAM Corsair Vengeance 16GB", "DDR4 3200MHz CL16 disipador aluminio negro", 175000.00, 60, 3),
        ("Tarjeta Gráfica NVIDIA RTX 4060 8GB", "ASUS Dual OC Edition GDDR6 DLSS 3", 1620000.00, 25, 3),
        ("Procesador AMD Ryzen 7 5700X", "8 núcleos, 16 hilos, hasta 4.6GHz Socket AM4", 890000.00, 30, 3),
        ("Router TP-Link Archer AX23", "Wi-Fi 6 AX1800 Doble Banda Gigabit OFDMA", 260000.00, 45, 4),
        ("Sistema Wi-Fi Mesh TP-Link Deco X20 (Pack x2)", "Cobertura hasta 370 m2, Wi-Fi 6 AX1800 Doble Banda", 640000.00, 28, 4),
        ("Samsung Galaxy A55 5G 256GB", "Pantalla Super AMOLED 120Hz, 8GB RAM, Cámara 50MP OIS", 1650000.00, 40, 5),
        ("Tablet Xiaomi Pad 6 256GB", "Pantalla 11\" 2.8K 144Hz, Snapdragon 870, 8GB RAM", 1580000.00, 25, 5),
        ("Audífonos Sony WH-CH720N", "Cancelación activa de ruido ANC, Bluetooth 5.2, 35h batería", 480000.00, 38, 6),
        ("Audífonos JBL Tune 520BT", "Sonido JBL Pure Bass, Bluetooth 5.3, hasta 57h de batería", 199000.00, 55, 6),
    ]
    articulo_info = {}
    for nom, desc, precio, stock, id_cat in articulos:
        cur.execute(
            """INSERT INTO articulo (nombre, descripcion, precio, stock, id_categoria)
               VALUES (%s, %s, %s, %s, %s) RETURNING id_articulo""",
            (nom, desc, precio, stock, id_cat),
        )
        aid = cur.fetchone()[0]
        articulo_info[aid] = {"nombre": nom, "precio": precio}

    # 5. Generar Ventas, Detalle de Venta y Formas de Pago coherentes con las reglas de negocio
    estados_venta = ["Entregada", "Enviada", "Pagada", "Pendiente", "Cancelada"]
    medios_pago = ["PSE", "Tarjeta Visa", "Tarjeta Mastercard", "Efecty", "Transferencia Bancaria"]

    ciudad_map = {cid: cnom for cid, cnom in ciudad_ids}

    # Creamos 25 órdenes de venta realistas distribuidas en los últimos 45 días
    fecha_base = date(2026, 9, 15)
    articulo_ids = list(articulo_info.keys())

    for i in range(1, 26):
        cliente_row = clientes_base[(i - 1) % len(clientes_base)]
        id_cliente = cliente_row[0]
        dir_cliente = cliente_row[5]
        nom_ciudad = ciudad_map[cliente_row[6]]
        direccion_envio = f"{dir_cliente}, {nom_ciudad}"

        fecha_venta = fecha_base + timedelta(days=random.randint(0, 45))

        # Distribuir estados de forma realista
        if i <= 10:
            estado_v = "Entregada"
        elif i <= 16:
            estado_v = "Enviada"
        elif i <= 20:
            estado_v = "Pagada"
        elif i <= 23:
            estado_v = "Pendiente"
        else:
            estado_v = "Cancelada"

        cur.execute(
            """INSERT INTO venta (fecha, estado, direccion_envio, id_cliente)
               VALUES (%s, %s, %s, %s) RETURNING id_venta""",
            (fecha_venta, estado_v, direccion_envio, id_cliente),
        )
        id_venta = cur.fetchone()[0]

        # Seleccionar entre 1 y 4 artículos distintos para la venta
        num_items = random.randint(1, 4)
        elegidos = random.sample(articulo_ids, num_items)
        total_venta = 0.0

        for id_art in elegidos:
            cantidad = random.randint(1, 3)
            cur.execute(
                """INSERT INTO detalle_venta (id_venta, id_articulo, cantidad)
                   VALUES (%s, %s, %s)""",
                (id_venta, id_art, cantidad),
            )
            total_venta += articulo_info[id_art]["precio"] * cantidad

        # Registrar forma_pago acorde al estado de la venta y respetando fecha_pago >= fecha_venta
        medio = random.choice(medios_pago)
        fecha_pago = fecha_venta + timedelta(days=random.randint(0, 2))

        if estado_v in ("Pagada", "Enviada", "Entregada"):
            cur.execute(
                """INSERT INTO forma_pago (tipo, monto, fecha_pago, estado, id_venta)
                   VALUES (%s, %s, %s, %s, %s)""",
                (medio, total_venta, fecha_pago, "Aprobado", id_venta),
            )
        elif estado_v == "Pendiente":
            # Algunas pendientes tienen pago en validación ('Pendiente')
            cur.execute(
                """INSERT INTO forma_pago (tipo, monto, fecha_pago, estado, id_venta)
                   VALUES (%s, %s, %s, %s, %s)""",
                (medio, total_venta, fecha_pago, "Pendiente", id_venta),
            )
        elif estado_v == "Cancelada":
            # Pago rechazado en orden cancelada
            cur.execute(
                """INSERT INTO forma_pago (tipo, monto, fecha_pago, estado, id_venta)
                   VALUES (%s, %s, %s, %s, %s)""",
                (medio, total_venta, fecha_pago, "Rechazado", id_venta),
            )

    # Dejar un par de artículos con stock bajo (<= 5) para que la vista de alertas de stock bajo del proyecto luzca genial
    cur.execute("UPDATE articulo SET stock = 3 WHERE id_articulo = 3;")
    cur.execute("UPDATE articulo SET stock = 4 WHERE id_articulo = 11;")

    conn.commit()

    # Verificar conteos finales
    tablas = ["ciudad", "cliente", "categoria", "articulo", "venta", "detalle_venta", "forma_pago"]
    print("\nResumen de registros insertados en Railway PostgreSQL:")
    for t in tablas:
        cur.execute(f"SELECT COUNT(*) FROM {t};")
        print(f"  - {t}: {cur.fetchone()[0]} registros")

    cur.close()
    conn.close()
    print("\n¡Base de datos inicializada y poblada con éxito!")

if __name__ == "__main__":
    main()
