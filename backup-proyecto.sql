--
-- PostgreSQL database dump
--

\restrict 9Zce5eDemwb2NhHmZFKJq8uhNqRdannYpvqA8fTuOlzwZhdt8DR2pLFIZe3fUGl

-- Dumped from database version 18.6 (Ubuntu 18.6-0ubuntu0.26.04.1)
-- Dumped by pg_dump version 18.6 (Ubuntu 18.6-0ubuntu0.26.04.1)

SET statement_timeout = 0;
SET lock_timeout = 0;
SET idle_in_transaction_session_timeout = 0;
SET transaction_timeout = 0;
SET client_encoding = 'UTF8';
SET standard_conforming_strings = on;
SELECT pg_catalog.set_config('search_path', '', false);
SET check_function_bodies = false;
SET xmloption = content;
SET client_min_messages = warning;
SET row_security = off;

--
-- Name: fn_descontar_stock_venta(); Type: FUNCTION; Schema: public; Owner: postgres
--

CREATE FUNCTION public.fn_descontar_stock_venta() RETURNS trigger
    LANGUAGE plpgsql
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
$$;


ALTER FUNCTION public.fn_descontar_stock_venta() OWNER TO postgres;

--
-- Name: fn_validar_fecha_pago(); Type: FUNCTION; Schema: public; Owner: postgres
--

CREATE FUNCTION public.fn_validar_fecha_pago() RETURNS trigger
    LANGUAGE plpgsql
    AS $$
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
$$;


ALTER FUNCTION public.fn_validar_fecha_pago() OWNER TO postgres;

SET default_tablespace = '';

SET default_table_access_method = heap;

--
-- Name: articulo; Type: TABLE; Schema: public; Owner: postgres
--

CREATE TABLE public.articulo (
    id_articulo integer NOT NULL,
    nombre character varying(120) NOT NULL,
    descripcion character varying(255),
    precio numeric(12,2) NOT NULL,
    stock integer DEFAULT 0 NOT NULL,
    id_categoria integer NOT NULL,
    CONSTRAINT ck_articulo_precio CHECK ((precio > (0)::numeric)),
    CONSTRAINT ck_articulo_stock CHECK ((stock >= 0))
);


ALTER TABLE public.articulo OWNER TO postgres;

--
-- Name: articulo_id_articulo_seq; Type: SEQUENCE; Schema: public; Owner: postgres
--

CREATE SEQUENCE public.articulo_id_articulo_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.articulo_id_articulo_seq OWNER TO postgres;

--
-- Name: articulo_id_articulo_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: postgres
--

ALTER SEQUENCE public.articulo_id_articulo_seq OWNED BY public.articulo.id_articulo;


--
-- Name: categoria; Type: TABLE; Schema: public; Owner: postgres
--

CREATE TABLE public.categoria (
    id_categoria integer NOT NULL,
    nombre character varying(80) NOT NULL,
    descripcion character varying(255)
);


ALTER TABLE public.categoria OWNER TO postgres;

--
-- Name: categoria_id_categoria_seq; Type: SEQUENCE; Schema: public; Owner: postgres
--

CREATE SEQUENCE public.categoria_id_categoria_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.categoria_id_categoria_seq OWNER TO postgres;

--
-- Name: categoria_id_categoria_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: postgres
--

ALTER SEQUENCE public.categoria_id_categoria_seq OWNED BY public.categoria.id_categoria;


--
-- Name: ciudad; Type: TABLE; Schema: public; Owner: postgres
--

CREATE TABLE public.ciudad (
    id_ciudad integer NOT NULL,
    nombre character varying(80) NOT NULL,
    departamento character varying(80) NOT NULL,
    pais character varying(60) DEFAULT 'Colombia'::character varying NOT NULL,
    codigo_postal character varying(15) NOT NULL
);


ALTER TABLE public.ciudad OWNER TO postgres;

--
-- Name: ciudad_id_ciudad_seq; Type: SEQUENCE; Schema: public; Owner: postgres
--

CREATE SEQUENCE public.ciudad_id_ciudad_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.ciudad_id_ciudad_seq OWNER TO postgres;

--
-- Name: ciudad_id_ciudad_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: postgres
--

ALTER SEQUENCE public.ciudad_id_ciudad_seq OWNED BY public.ciudad.id_ciudad;


--
-- Name: cliente; Type: TABLE; Schema: public; Owner: postgres
--

CREATE TABLE public.cliente (
    id_cliente integer NOT NULL,
    nombre character varying(80) NOT NULL,
    apellido character varying(80) NOT NULL,
    email character varying(120) NOT NULL,
    telefono character varying(20) NOT NULL,
    direccion character varying(150) NOT NULL,
    id_ciudad integer NOT NULL,
    CONSTRAINT ck_cliente_id CHECK ((id_cliente > 0))
);


ALTER TABLE public.cliente OWNER TO postgres;

--
-- Name: detalle_venta; Type: TABLE; Schema: public; Owner: postgres
--

CREATE TABLE public.detalle_venta (
    id_venta integer NOT NULL,
    id_articulo integer NOT NULL,
    cantidad integer NOT NULL,
    CONSTRAINT ck_detalle_cantidad CHECK ((cantidad > 0))
);


ALTER TABLE public.detalle_venta OWNER TO postgres;

--
-- Name: forma_pago; Type: TABLE; Schema: public; Owner: postgres
--

CREATE TABLE public.forma_pago (
    id_pago integer NOT NULL,
    tipo character varying(40) NOT NULL,
    monto numeric(12,2) NOT NULL,
    fecha_pago date DEFAULT CURRENT_DATE NOT NULL,
    estado character varying(30) DEFAULT 'Aprobado'::character varying NOT NULL,
    id_venta integer NOT NULL,
    CONSTRAINT ck_forma_pago_estado CHECK (((estado)::text = ANY ((ARRAY['Pendiente'::character varying, 'Aprobado'::character varying, 'Rechazado'::character varying])::text[]))),
    CONSTRAINT ck_forma_pago_monto CHECK ((monto > (0)::numeric)),
    CONSTRAINT ck_forma_pago_tipo CHECK (((tipo)::text = ANY ((ARRAY['PSE'::character varying, 'Tarjeta Visa'::character varying, 'Tarjeta Mastercard'::character varying, 'Efecty'::character varying, 'Transferencia Bancaria'::character varying])::text[])))
);


ALTER TABLE public.forma_pago OWNER TO postgres;

--
-- Name: forma_pago_id_pago_seq; Type: SEQUENCE; Schema: public; Owner: postgres
--

CREATE SEQUENCE public.forma_pago_id_pago_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.forma_pago_id_pago_seq OWNER TO postgres;

--
-- Name: forma_pago_id_pago_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: postgres
--

ALTER SEQUENCE public.forma_pago_id_pago_seq OWNED BY public.forma_pago.id_pago;


--
-- Name: venta; Type: TABLE; Schema: public; Owner: postgres
--

CREATE TABLE public.venta (
    id_venta integer NOT NULL,
    fecha date DEFAULT CURRENT_DATE NOT NULL,
    estado character varying(30) DEFAULT 'Pendiente'::character varying NOT NULL,
    direccion_envio character varying(150) NOT NULL,
    id_cliente integer NOT NULL,
    CONSTRAINT ck_venta_estado CHECK (((estado)::text = ANY ((ARRAY['Pendiente'::character varying, 'Pagada'::character varying, 'Enviada'::character varying, 'Entregada'::character varying, 'Cancelada'::character varying])::text[])))
);


ALTER TABLE public.venta OWNER TO postgres;

--
-- Name: venta_id_venta_seq; Type: SEQUENCE; Schema: public; Owner: postgres
--

CREATE SEQUENCE public.venta_id_venta_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.venta_id_venta_seq OWNER TO postgres;

--
-- Name: venta_id_venta_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: postgres
--

ALTER SEQUENCE public.venta_id_venta_seq OWNED BY public.venta.id_venta;


--
-- Name: articulo id_articulo; Type: DEFAULT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.articulo ALTER COLUMN id_articulo SET DEFAULT nextval('public.articulo_id_articulo_seq'::regclass);


--
-- Name: categoria id_categoria; Type: DEFAULT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.categoria ALTER COLUMN id_categoria SET DEFAULT nextval('public.categoria_id_categoria_seq'::regclass);


--
-- Name: ciudad id_ciudad; Type: DEFAULT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.ciudad ALTER COLUMN id_ciudad SET DEFAULT nextval('public.ciudad_id_ciudad_seq'::regclass);


--
-- Name: forma_pago id_pago; Type: DEFAULT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.forma_pago ALTER COLUMN id_pago SET DEFAULT nextval('public.forma_pago_id_pago_seq'::regclass);


--
-- Name: venta id_venta; Type: DEFAULT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.venta ALTER COLUMN id_venta SET DEFAULT nextval('public.venta_id_venta_seq'::regclass);


--
-- Data for Name: articulo; Type: TABLE DATA; Schema: public; Owner: postgres
--

COPY public.articulo (id_articulo, nombre, descripcion, precio, stock, id_categoria) FROM stdin;
1	Portátil Lenovo IdeaPad 3	15.6", 8 GB RAM, 512 GB SSD	2350000.00	9	1
2	Samsung Galaxy A55	128 GB, 8 GB RAM	1650000.00	14	2
3	Mouse Logitech M170	Inalámbrico	45000.00	47	3
4	Audífonos JBL Tune 520BT	Bluetooth	199000.00	16	3
5	Pencil	Lápiz digital	500000.00	20	3
6	DYSON	Multiestilizador de pelo	5000000.00	19	3
\.


--
-- Data for Name: categoria; Type: TABLE DATA; Schema: public; Owner: postgres
--

COPY public.categoria (id_categoria, nombre, descripcion) FROM stdin;
1	Computadores	Portátiles y equipos de escritorio
2	Celulares	Smartphones y accesorios
3	Accesorios	Mouse, teclados, audífonos y similares
4	Cocina	Artículos utilizados en la cocina
\.


--
-- Data for Name: ciudad; Type: TABLE DATA; Schema: public; Owner: postgres
--

COPY public.ciudad (id_ciudad, nombre, departamento, pais, codigo_postal) FROM stdin;
1	Villavicencio	Meta	Colombia	500001
2	Bogotá	Cundinamarca	Colombia	110111
3	Medellín	Antioquia	Colombia	050001
\.


--
-- Data for Name: cliente; Type: TABLE DATA; Schema: public; Owner: postgres
--

COPY public.cliente (id_cliente, nombre, apellido, email, telefono, direccion, id_ciudad) FROM stdin;
1012345678	Laura	Pérez	laura.perez@mail.com	3101234567	Cra 33 # 15-20	1
1023456789	Carlos	Gómez	carlos.gomez@mail.com	3209876543	Calle 80 # 10-45	2
1034567890	Ana	Torres	ana.torres@mail.com	3005558899	Av 70 # 45-12	3
1013266164	Andres Jeronimo	Puerta Lopez	puertalopezandresjeronimo@gmail.com	3218540434	calle 20 #34 A-12	1
1123805015	Heidy Nicole	Velez Martinez	velezmartinezheidynicol@gmail.com	3108765077	Calle 39c #27A - 46	1
\.


--
-- Data for Name: detalle_venta; Type: TABLE DATA; Schema: public; Owner: postgres
--

COPY public.detalle_venta (id_venta, id_articulo, cantidad) FROM stdin;
1	1	1
1	3	2
2	2	1
2	4	1
3	3	1
4	5	4
4	4	3
5	5	6
6	6	1
\.


--
-- Data for Name: forma_pago; Type: TABLE DATA; Schema: public; Owner: postgres
--

COPY public.forma_pago (id_pago, tipo, monto, fecha_pago, estado, id_venta) FROM stdin;
1	PSE	2440000.00	2026-10-01	Aprobado	1
2	Tarjeta Visa	1849000.00	2026-10-06	Aprobado	2
5	Tarjeta Visa	2597000.00	2026-10-31	Aprobado	4
3	Efecty	45000.00	2026-10-10	Aprobado	3
6	Transferencia Bancaria	3000000.00	2026-10-31	Aprobado	5
7	PSE	5000000.00	2026-11-01	Aprobado	6
\.


--
-- Data for Name: venta; Type: TABLE DATA; Schema: public; Owner: postgres
--

COPY public.venta (id_venta, fecha, estado, direccion_envio, id_cliente) FROM stdin;
4	2026-10-31	Entregada	calle 20 #34 A-12	1013266164
3	2026-10-10	Entregada	Av 70 # 45-12	1034567890
2	2026-10-05	Entregada	Calle 80 # 10-45	1023456789
1	2026-10-01	Entregada	Cra 33 # 15-20	1012345678
5	2026-10-31	Entregada	Calle 80 # 10-45	1023456789
6	2026-11-01	Entregada	Calle 39c #27A - 46	1123805015
\.


--
-- Name: articulo_id_articulo_seq; Type: SEQUENCE SET; Schema: public; Owner: postgres
--

SELECT pg_catalog.setval('public.articulo_id_articulo_seq', 6, true);


--
-- Name: categoria_id_categoria_seq; Type: SEQUENCE SET; Schema: public; Owner: postgres
--

SELECT pg_catalog.setval('public.categoria_id_categoria_seq', 4, true);


--
-- Name: ciudad_id_ciudad_seq; Type: SEQUENCE SET; Schema: public; Owner: postgres
--

SELECT pg_catalog.setval('public.ciudad_id_ciudad_seq', 4, true);


--
-- Name: forma_pago_id_pago_seq; Type: SEQUENCE SET; Schema: public; Owner: postgres
--

SELECT pg_catalog.setval('public.forma_pago_id_pago_seq', 7, true);


--
-- Name: venta_id_venta_seq; Type: SEQUENCE SET; Schema: public; Owner: postgres
--

SELECT pg_catalog.setval('public.venta_id_venta_seq', 6, true);


--
-- Name: articulo pk_articulo; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.articulo
    ADD CONSTRAINT pk_articulo PRIMARY KEY (id_articulo);


--
-- Name: categoria pk_categoria; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.categoria
    ADD CONSTRAINT pk_categoria PRIMARY KEY (id_categoria);


--
-- Name: ciudad pk_ciudad; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.ciudad
    ADD CONSTRAINT pk_ciudad PRIMARY KEY (id_ciudad);


--
-- Name: cliente pk_cliente; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.cliente
    ADD CONSTRAINT pk_cliente PRIMARY KEY (id_cliente);


--
-- Name: detalle_venta pk_detalle_venta; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.detalle_venta
    ADD CONSTRAINT pk_detalle_venta PRIMARY KEY (id_venta, id_articulo);


--
-- Name: forma_pago pk_forma_pago; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.forma_pago
    ADD CONSTRAINT pk_forma_pago PRIMARY KEY (id_pago);


--
-- Name: venta pk_venta; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.venta
    ADD CONSTRAINT pk_venta PRIMARY KEY (id_venta);


--
-- Name: categoria uq_categoria_nombre; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.categoria
    ADD CONSTRAINT uq_categoria_nombre UNIQUE (nombre);


--
-- Name: cliente uq_cliente_email; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.cliente
    ADD CONSTRAINT uq_cliente_email UNIQUE (email);


--
-- Name: detalle_venta trg_descontar_stock_venta; Type: TRIGGER; Schema: public; Owner: postgres
--

CREATE TRIGGER trg_descontar_stock_venta BEFORE INSERT ON public.detalle_venta FOR EACH ROW EXECUTE FUNCTION public.fn_descontar_stock_venta();


--
-- Name: forma_pago trg_validar_fecha_pago; Type: TRIGGER; Schema: public; Owner: postgres
--

CREATE TRIGGER trg_validar_fecha_pago BEFORE INSERT OR UPDATE ON public.forma_pago FOR EACH ROW EXECUTE FUNCTION public.fn_validar_fecha_pago();


--
-- Name: articulo fk_articulo_categoria; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.articulo
    ADD CONSTRAINT fk_articulo_categoria FOREIGN KEY (id_categoria) REFERENCES public.categoria(id_categoria) ON UPDATE CASCADE ON DELETE RESTRICT;


--
-- Name: cliente fk_cliente_ciudad; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.cliente
    ADD CONSTRAINT fk_cliente_ciudad FOREIGN KEY (id_ciudad) REFERENCES public.ciudad(id_ciudad) ON UPDATE CASCADE ON DELETE RESTRICT;


--
-- Name: detalle_venta fk_detalle_articulo; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.detalle_venta
    ADD CONSTRAINT fk_detalle_articulo FOREIGN KEY (id_articulo) REFERENCES public.articulo(id_articulo) ON UPDATE CASCADE ON DELETE RESTRICT;


--
-- Name: detalle_venta fk_detalle_venta; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.detalle_venta
    ADD CONSTRAINT fk_detalle_venta FOREIGN KEY (id_venta) REFERENCES public.venta(id_venta) ON UPDATE CASCADE ON DELETE CASCADE;


--
-- Name: forma_pago fk_forma_pago_venta; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.forma_pago
    ADD CONSTRAINT fk_forma_pago_venta FOREIGN KEY (id_venta) REFERENCES public.venta(id_venta) ON UPDATE CASCADE ON DELETE RESTRICT;


--
-- Name: venta fk_venta_cliente; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.venta
    ADD CONSTRAINT fk_venta_cliente FOREIGN KEY (id_cliente) REFERENCES public.cliente(id_cliente) ON UPDATE CASCADE ON DELETE RESTRICT;


--
-- Name: SCHEMA public; Type: ACL; Schema: -; Owner: pg_database_owner
--

GRANT USAGE ON SCHEMA public TO app_ventas;


--
-- Name: TABLE articulo; Type: ACL; Schema: public; Owner: postgres
--

GRANT SELECT,INSERT,DELETE,UPDATE ON TABLE public.articulo TO app_ventas;


--
-- Name: SEQUENCE articulo_id_articulo_seq; Type: ACL; Schema: public; Owner: postgres
--

GRANT SELECT,USAGE ON SEQUENCE public.articulo_id_articulo_seq TO app_ventas;


--
-- Name: TABLE categoria; Type: ACL; Schema: public; Owner: postgres
--

GRANT SELECT,INSERT,DELETE,UPDATE ON TABLE public.categoria TO app_ventas;


--
-- Name: SEQUENCE categoria_id_categoria_seq; Type: ACL; Schema: public; Owner: postgres
--

GRANT SELECT,USAGE ON SEQUENCE public.categoria_id_categoria_seq TO app_ventas;


--
-- Name: TABLE ciudad; Type: ACL; Schema: public; Owner: postgres
--

GRANT SELECT,INSERT,DELETE,UPDATE ON TABLE public.ciudad TO app_ventas;


--
-- Name: SEQUENCE ciudad_id_ciudad_seq; Type: ACL; Schema: public; Owner: postgres
--

GRANT SELECT,USAGE ON SEQUENCE public.ciudad_id_ciudad_seq TO app_ventas;


--
-- Name: TABLE cliente; Type: ACL; Schema: public; Owner: postgres
--

GRANT SELECT,INSERT,DELETE,UPDATE ON TABLE public.cliente TO app_ventas;


--
-- Name: TABLE detalle_venta; Type: ACL; Schema: public; Owner: postgres
--

GRANT SELECT,INSERT,DELETE,UPDATE ON TABLE public.detalle_venta TO app_ventas;


--
-- Name: TABLE forma_pago; Type: ACL; Schema: public; Owner: postgres
--

GRANT SELECT,INSERT,DELETE,UPDATE ON TABLE public.forma_pago TO app_ventas;


--
-- Name: SEQUENCE forma_pago_id_pago_seq; Type: ACL; Schema: public; Owner: postgres
--

GRANT SELECT,USAGE ON SEQUENCE public.forma_pago_id_pago_seq TO app_ventas;


--
-- Name: TABLE venta; Type: ACL; Schema: public; Owner: postgres
--

GRANT SELECT,INSERT,DELETE,UPDATE ON TABLE public.venta TO app_ventas;


--
-- Name: SEQUENCE venta_id_venta_seq; Type: ACL; Schema: public; Owner: postgres
--

GRANT SELECT,USAGE ON SEQUENCE public.venta_id_venta_seq TO app_ventas;


--
-- PostgreSQL database dump complete
--

\unrestrict 9Zce5eDemwb2NhHmZFKJq8uhNqRdannYpvqA8fTuOlzwZhdt8DR2pLFIZe3fUGl

