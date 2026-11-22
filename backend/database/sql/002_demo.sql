-- =====================================================================
-- Datos de demostración. Requiere el catálogo base
-- (roles, medios de pago y parámetros: CatalogoBaseSeeder).
-- Las fechas son relativas al momento de la carga, de modo que el panel
-- siempre muestra servicios en curso, próximos traslados e historial.
-- Contraseña de todas las cuentas: CristoRey#2026!
-- =====================================================================

-- Hora local (día relativo + hora) convertida a UTC sin zona.
CREATE OR REPLACE FUNCTION pg_temp.loc(dias int, hora text) RETURNS timestamp AS $$
    SELECT (((date_trunc('day', now() AT TIME ZONE 'America/Santiago') + make_interval(days => dias) + hora::interval)
             AT TIME ZONE 'America/Santiago') AT TIME ZONE 'UTC')::timestamp(0)
$$ LANGUAGE sql;

-- Instante relativo a ahora (en horas), redondeado a 15 minutos, en UTC.
CREATE OR REPLACE FUNCTION pg_temp.rel(horas numeric) RETURNS timestamp AS $$
    SELECT (to_timestamp(round(extract(epoch FROM now() + horas * interval '1 hour') / 900) * 900) AT TIME ZONE 'UTC')::timestamp(0)
$$ LANGUAGE sql;

-- Dígito verificador de un RUT
CREATE OR REPLACE FUNCTION pg_temp.rut(n bigint) RETURNS text AS $$
    SELECT n::text || '-' || CASE r WHEN 11 THEN '0' WHEN 10 THEN 'K' ELSE r::text END
      FROM (SELECT 11 - (SUM(substr(reverse(n::text), k, 1)::int * (2 + (k - 1) % 6)) % 11) AS r
              FROM generate_series(1, length(n::text)) k) s
$$ LANGUAGE sql;

-- Hoy en hora local (fecha).
CREATE OR REPLACE FUNCTION pg_temp.hoy() RETURNS date AS $$
    SELECT (now() AT TIME ZONE 'America/Santiago')::date
$$ LANGUAGE sql;

-- ---------------------------------------------------------------------
-- Sucursales y usuarios
-- ---------------------------------------------------------------------
INSERT INTO sucursales (id, nombre, direccion, telefono, created_at, updated_at) VALUES
    (1, 'Sucursal Blanco', 'Calle Blanco #142, entre Lautaro y Miraflores, Temuco', '+56 9 6729 4759', now(), now()),
    (2, 'Casa Matriz', 'Manuel Montt #1991, Pobl. Santa Elena, Temuco', '+56 9 8601 7135', now(), now());

INSERT INTO usuarios (id, sucursal_id, nombre, rut, email, telefono, password, activo, created_at, updated_at) VALUES
    (1, 1, 'Renato Osses', pg_temp.rut(9876543), 'renato.osses@cristorey.test', '+56 9 8123 4567', '$argon2id$v=19$m=65536,t=4,p=1$MmdjbkhFWmltM29qSTkvbA$napHEznEVamCPNNBh63M324XMdl33Z3ou1tfWxVqZ5w', true, now(), now()),
    (2, 2, 'Carla Fuentes', pg_temp.rut(15234876), 'carla.fuentes@cristorey.test', '+56 9 7654 3210', '$argon2id$v=19$m=65536,t=4,p=1$MmdjbkhFWmltM29qSTkvbA$napHEznEVamCPNNBh63M324XMdl33Z3ou1tfWxVqZ5w', true, now(), now()),
    (3, 1, 'Pedro Soto', pg_temp.rut(16789123), 'pedro.soto@cristorey.test', '+56 9 6111 2233', '$argon2id$v=19$m=65536,t=4,p=1$MmdjbkhFWmltM29qSTkvbA$napHEznEVamCPNNBh63M324XMdl33Z3ou1tfWxVqZ5w', true, now(), now()),
    (4, 1, 'Luis Mena', pg_temp.rut(17345678), 'luis.mena@cristorey.test', '+56 9 6222 3344', '$argon2id$v=19$m=65536,t=4,p=1$MmdjbkhFWmltM29qSTkvbA$napHEznEVamCPNNBh63M324XMdl33Z3ou1tfWxVqZ5w', true, now(), now()),
    (5, 2, 'Ana Rojas', pg_temp.rut(18456789), 'ana.rojas@cristorey.test', '+56 9 6333 4455', '$argon2id$v=19$m=65536,t=4,p=1$MmdjbkhFWmltM29qSTkvbA$napHEznEVamCPNNBh63M324XMdl33Z3ou1tfWxVqZ5w', true, now(), now()),
    (6, 2, 'Jorge Díaz', pg_temp.rut(14567890), 'jorge.diaz@cristorey.test', '+56 9 6444 5566', '$argon2id$v=19$m=65536,t=4,p=1$MmdjbkhFWmltM29qSTkvbA$napHEznEVamCPNNBh63M324XMdl33Z3ou1tfWxVqZ5w', true, now(), now());

INSERT INTO rol_usuario (rol_id, usuario_id, created_at, updated_at)
SELECT r.id, u.id, now(), now()
  FROM (VALUES (1, 'Administrador'), (2, 'Coordinador'), (3, 'Operario'), (4, 'Operario'), (5, 'Operario'), (6, 'Operario')) AS x(uid, rol)
  JOIN roles r ON r.nombre = x.rol JOIN usuarios u ON u.id = x.uid;

-- ---------------------------------------------------------------------
-- Convenios
-- ---------------------------------------------------------------------
INSERT INTO convenios (id, nombre, institucion, tipo, descuento_pct, vigencia_desde, vigencia_hasta, descripcion, contacto, activo, created_at, updated_at) VALUES
    (1, 'Cuota mortuoria AFP', 'Administradoras de Fondos de Pensiones', 'cuota_mortuoria', 0, '2020-01-01', NULL,
        'La funeraria gestiona el cobro de la cuota mortuoria de la AFP del afiliado y la abona al servicio.', 'Mesa de ayuda de cada AFP', true, now(), now()),
    (2, 'Cuota mortuoria IPS', 'Instituto de Previsión Social', 'cuota_mortuoria', 0, '2020-01-01', NULL,
        'Gestión de la cuota mortuoria para pensionados del IPS; se abona al saldo del servicio.', 'Sucursal IPS Temuco', true, now(), now()),
    (3, 'Convenio DIPRECA', 'Dirección de Previsión de Carabineros', 'institucional', 10, '2023-03-01', NULL,
        'Descuento sobre el valor del servicio para imponentes y sus cargas.', 'Oficina de bienestar regional', true, now(), now()),
    (4, 'Convenio CAPREDENA', 'Caja de Previsión de la Defensa Nacional', 'institucional', 10, '2023-06-01', NULL,
        'Descuento sobre el valor del servicio para pensionados de la Defensa.', 'Oficina de bienestar regional', true, now(), now()),
    (5, 'Asociación de Funcionarios Municipales', 'Asociación de Funcionarios Municipales de Temuco', 'descuento', 12, '2024-01-01', pg_temp.hoy() + 240,
        'Descuento para socios activos y su grupo familiar directo.', 'Directiva de la asociación', true, now(), now()),
    (6, 'Colegio de Profesores Comunal', 'Colegio de Profesores, comunal Temuco', 'descuento', 8, '2022-01-01', pg_temp.hoy() - 40,
        'Convenio vencido; pendiente de renovación.', 'Directiva comunal', true, now(), now());

-- ---------------------------------------------------------------------
-- Proveedores y productos
-- ---------------------------------------------------------------------
INSERT INTO proveedores (id, nombre, rut, rubro, contacto, telefono, email, direccion, notas, activo, created_at, updated_at) VALUES
    (1, 'Ataúdes del Sur Ltda.', pg_temp.rut(76345120), 'urnas', 'Marcelo Pino', '+56 45 224 8890', 'ventas@ataudesdelsur.test', 'Av. Caupolicán 1450, Temuco', 'Despacho en 48 horas. Pedido mínimo: 3 unidades.', true, now(), now()),
    (2, 'Maderas Nativas Cautín SpA', pg_temp.rut(77210984), 'urnas', 'Fabiola Henríquez', '+56 9 9876 1122', 'contacto@maderascautin.test', 'Av. Balmaceda 1830, Temuco', 'Fabrica modelos en roble, raulí y lenga; acepta grabado personalizado.', true, now(), now()),
    (3, 'Florería Los Aromos', pg_temp.rut(76998123), 'flores', 'Patricia Muñoz', '+56 9 8765 4433', 'pedidos@losaromos.test', 'Av. Alemania 0980, Temuco', 'Coronas y cubre urnas con 6 horas de anticipación.', true, now(), now()),
    (4, 'Insumos Funerarios Araucanía', pg_temp.rut(78123456), 'insumos', 'Rodrigo Catalán', '+56 45 227 6600', 'ventas@insumosaraucania.test', 'Calle Lautaro 455, Temuco', 'Urnas de cremación, cirios y libros de condolencias.', true, now(), now());

INSERT INTO productos (id, proveedor_id, codigo, nombre, tipo, material, color, medidas, descripcion, precio, stock, umbral_minimo, infantil, publico, foto_ruta, atributos, created_at, updated_at) VALUES
    (1, 2, 'AT-101', 'Imperial Roble', 'ataud', 'Roble', 'Café oscuro', '195 × 60 × 45 cm', 'Ataúd de roble macizo con tapa tallada, manillas de bronce y forro de satén capitoné.', 1250000, 4, 2, false, true, 'at-101.webp', '{"manillas": "bronce", "forro": "satén capitoné"}', now(), now()),
    (2, 1, 'AT-102', 'Clásico Pino', 'ataud', 'Pino', 'Natural barnizado', '190 × 58 × 42 cm', 'Modelo tradicional de pino barnizado, terminación brillante y forro de tela blanca.', 480000, 9, 3, false, true, 'at-102.webp', '{"forro": "tela"}', now(), now()),
    (3, 2, 'AT-103', 'Colonial Raulí', 'ataud', 'Raulí', 'Caoba', '196 × 62 × 46 cm', 'Líneas coloniales en raulí, molduras laterales y crucifijo en la tapa.', 1590000, 2, 2, false, true, 'at-103.webp', '{"manillas": "bronce envejecido"}', now(), now()),
    (4, 2, 'AT-104', 'Ejecutivo Lenga', 'ataud', 'Lenga', 'Miel', '194 × 60 × 44 cm', 'Ataúd de lenga con tapa de doble panel y terminación satinada.', 890000, 5, 3, false, true, 'at-104.webp', '{}', now(), now()),
    (5, 1, 'AT-105', 'Sencillo Gris Perla', 'ataud', 'Aglomerado enchapado', 'Gris perla', '188 × 56 × 40 cm', 'Alternativa económica con enchape lacado y manillas metálicas.', 320000, 6, 3, false, true, 'at-105.webp', '{}', now(), now()),
    (6, 1, 'AT-106', 'Metálico Plata', 'ataud', 'Acero', 'Plateado', '198 × 64 × 48 cm', 'Ataúd metálico con sello hermético, recomendado para traslados largos.', 1980000, 1, 1, false, true, 'at-106.webp', '{"hermetico": true}', now(), now()),
    (7, 1, 'AT-201', 'Ángel Blanco (infantil)', 'ataud', 'Pino lacado', 'Blanco', '120 × 45 × 35 cm', 'Ataúd infantil lacado en blanco con detalles en relieve.', 290000, 1, 2, true, true, 'at-201.webp', '{}', now(), now()),
    (8, 1, 'AT-202', 'Celeste (infantil)', 'ataud', 'Pino lacado', 'Celeste', '110 × 40 × 32 cm', 'Ataúd infantil en tono celeste con forro acolchado.', 270000, 3, 2, true, true, 'at-202.webp', '{}', now(), now()),
    (9, 4, 'UC-301', 'Cremación Mármol', 'urna_cremacion', 'Resina marmolada', 'Blanco', '28 × 18 cm', 'Urna para cenizas de resina con acabado tipo mármol y tapa roscada.', 180000, 7, 3, false, true, 'uc-301.webp', '{"capacidad_litros": 3.5}', now(), now()),
    (10, 2, 'UC-302', 'Cremación Madera Nativa', 'urna_cremacion', 'Raulí', 'Café', '25 × 20 × 15 cm', 'Urna de raulí con placa frontal para grabado.', 150000, 5, 3, false, true, 'uc-302.webp', '{"capacidad_litros": 3.0, "grabado": true}', now(), now()),
    (11, 2, 'UR-401', 'Personalizada con grabado', 'urna', 'Roble', 'Nogal', '195 × 60 × 45 cm', 'Urna de roble con grabado láser a pedido (nombre, fechas o imagen).', 1450000, 2, 2, false, true, 'ur-401.webp', '{"personalizable": true, "grabado": "láser"}', now(), now()),
    (12, 3, 'EL-501', 'Corona de flores naturales', 'elemento', 'Flores naturales', 'Blanco y verde', '80 cm', 'Corona de lirios, rosas y follaje de temporada.', 85000, 12, 4, false, false, NULL, '{}', now(), now()),
    (13, 3, 'EL-502', 'Cubre urna de rosas', 'elemento', 'Flores naturales', 'Blanco', '150 cm', 'Manto floral de rosas blancas para la tapa.', 120000, 6, 3, false, false, NULL, '{}', now(), now()),
    (14, 4, 'EL-503', 'Libro de condolencias', 'elemento', 'Cuero sintético', 'Negro', '30 × 22 cm', 'Libro de firmas con cubierta acolchada.', 25000, 20, 5, false, false, NULL, '{}', now(), now()),
    (15, 4, 'EL-504', 'Set de cirios y candelabros', 'elemento', 'Metal y cera', 'Dorado', '4 piezas', 'Juego de cuatro candelabros con cirios para la sala.', 45000, 8, 2, false, false, NULL, '{}', now(), now());

-- ---------------------------------------------------------------------
-- Flota
-- ---------------------------------------------------------------------
INSERT INTO vehiculos (id, nombre, tipo, patente, marca, modelo, anio, origen, proveedor_externo, activo, notas, created_at, updated_at) VALUES
    (1, 'Carroza principal', 'carroza', 'KXTR21', 'Mercedes-Benz', 'Clase E', 2019, 'propio', NULL, true, 'Revisión técnica al día.', now(), now()),
    (2, 'Furgón de instalación 1', 'instalacion', 'JHPL54', 'Peugeot', 'Partner', 2018, 'propio', NULL, true, 'Traslada el equipamiento de la sala velatoria.', now(), now()),
    (3, 'Furgón de instalación 2', 'instalacion', 'LBCD87', 'Hyundai', 'H-1', 2021, 'propio', NULL, true, NULL, now(), now()),
    (4, 'Carroza arrendada', 'carroza', NULL, NULL, NULL, NULL, 'externo', 'Servicios Fúnebres de Perquenco (arriendo)', true, 'Se arrienda cuando la carroza principal está ocupada.', now(), now()),
    (5, 'Camioneta de apoyo', 'apoyo', 'GHTY12', 'Toyota', 'Hilux', 2017, 'propio', NULL, true, 'Retiros y traslado de coronas.', now(), now());

-- ---------------------------------------------------------------------
-- Historial de 12 meses de servicios ejecutados
-- ---------------------------------------------------------------------
CREATE TEMP TABLE demo_hist AS
WITH meses AS (
    SELECT m, (date_trunc('month', pg_temp.hoy()) - make_interval(months => 12 - m))::date AS inicio
      FROM generate_series(0, 12) m
), cupos AS (
    SELECT me.m, me.inicio,
           CASE extract(month FROM me.inicio)::int
               WHEN 1 THEN 8 WHEN 2 THEN 7 WHEN 3 THEN 9 WHEN 4 THEN 10 WHEN 5 THEN 12 WHEN 6 THEN 14
               WHEN 7 THEN 15 WHEN 8 THEN 14 WHEN 9 THEN 11 WHEN 10 THEN 10 WHEN 11 THEN 9 ELSE 9 END AS cantidad
      FROM meses me
), slots AS (
    SELECT c.m, c.inicio, k, c.cantidad,
           (1 + floor((k - 0.5) * extract(day FROM (c.inicio + interval '1 month' - interval '1 day')) / c.cantidad))::int AS dia
      FROM cupos c, LATERAL generate_series(1, c.cantidad) k
)
SELECT row_number() OVER (ORDER BY inicio, dia)::int AS i, inicio + (dia - 1) AS fecha
  FROM slots
 WHERE inicio + (dia - 1) <= pg_temp.hoy() - 4;

ALTER TABLE demo_hist ADD COLUMN traslado timestamp(0), ADD COLUMN sucursal int, ADD COLUMN convenio int, ADD COLUMN pct numeric,
    ADD COLUMN valor bigint, ADD COLUMN descuento bigint, ADD COLUMN ataud int, ADD COLUMN infantil boolean, ADD COLUMN total bigint,
    ADD COLUMN abono bigint, ADD COLUMN en_cuotas boolean, ADD COLUMN cuota_atrasada boolean;

UPDATE demo_hist SET
    traslado = (((fecha + make_interval(hours => 10 + (i * 3) % 7, mins => (i % 2) * 30)) AT TIME ZONE 'America/Santiago') AT TIME ZONE 'UTC')::timestamp(0),
    sucursal = CASE WHEN i % 5 IN (1, 3) THEN 2 ELSE 1 END,
    convenio = CASE WHEN i % 11 = 0 THEN 5 WHEN i % 9 = 0 THEN 3 WHEN i % 13 = 0 THEN 4 WHEN i % 6 = 0 THEN 1 WHEN i % 17 = 0 THEN 2 END,
    valor = (ARRAY[690000, 790000, 890000, 990000, 1190000, 1390000])[1 + (i * 3) % 6],
    infantil = (i % 37 = 0),
    en_cuotas = (i % 8 = 3),
    cuota_atrasada = (i % 16 = 3);

UPDATE demo_hist SET
    pct = COALESCE((SELECT descuento_pct FROM convenios c WHERE c.id = demo_hist.convenio), 0),
    ataud = CASE
        WHEN infantil THEN 7 + (i % 2)
        WHEN i % 23 = 0 THEN 11
        WHEN i % 29 = 0 THEN 6
        ELSE (ARRAY[2, 2, 2, 4, 4, 1, 5, 3, 9, 10])[1 + i % 10] END;
UPDATE demo_hist SET descuento = round(valor * pct / 100 / 1000) * 1000;

-- Nombres
CREATE TEMP TABLE demo_nombres AS SELECT
    ARRAY['María', 'José', 'Juan', 'Rosa', 'Luis', 'Carmen', 'Pedro', 'Ana', 'Manuel', 'Elena', 'Jorge', 'Gloria', 'Héctor', 'Patricia',
          'Sergio', 'Teresa', 'Ricardo', 'Silvia', 'Raúl', 'Marta', 'Hugo', 'Olga', 'Víctor', 'Irma', 'Nelson', 'Berta', 'Osvaldo', 'Luisa'] AS n,
    ARRAY['González', 'Muñoz', 'Rojas', 'Díaz', 'Pérez', 'Soto', 'Contreras', 'Silva', 'Martínez', 'Sepúlveda', 'Morales', 'Rodríguez',
          'López', 'Fuentes', 'Hernández', 'Torres', 'Araya', 'Flores', 'Espinoza', 'Valenzuela', 'Castillo', 'Tapia', 'Reyes', 'Huenchullán',
          'Painemal', 'Catrileo', 'Millapán', 'Ñancupil', 'Quilaqueo', 'Curiche', 'Sandoval', 'Jara', 'Vergara', 'Riquelme'] AS a,
    ARRAY['Los Copihues', 'Av. Pedro de Valdivia', 'Las Araucarias', 'Los Aromos', 'Manuel Montt', 'Caupolicán', 'Lautaro', 'Balmaceda',
          'Aníbal Pinto', 'Las Encinas', 'Pasaje Los Notros', 'Villa Los Ríos'] AS calles,
    ARRAY['Temuco', 'Temuco', 'Temuco', 'Temuco', 'Temuco', 'Temuco', 'Temuco', 'Temuco', 'Temuco', 'Temuco'] AS comunas;

INSERT INTO clientes (id, rut, nombre, telefono, email, direccion, comuna, created_at, created_by, updated_at)
SELECT h.i,
       pg_temp.rut(6000000 + (h.i * 104729) % 18000000),
       n[1 + (h.i * 7) % array_length(n, 1)] || ' ' || a[1 + (h.i * 11) % array_length(a, 1)] || ' ' || a[1 + (h.i * 5 + 3) % array_length(a, 1)],
       '+56 9 ' || lpad(((h.i * 7919) % 10000)::text, 4, '0') || ' ' || lpad(((h.i * 104723) % 10000)::text, 4, '0'),
       CASE WHEN h.i % 3 <> 0 THEN 'contacto' || h.i || '@correo.test' END,
       calles[1 + h.i % array_length(calles, 1)] || ' ' || (100 + (h.i * 37) % 1900),
       comunas[1 + h.i % array_length(comunas, 1)],
       h.traslado - interval '2 days', CASE WHEN h.sucursal = 2 THEN 2 ELSE 1 END, h.traslado - interval '2 days'
  FROM demo_hist h, demo_nombres;

INSERT INTO cliente_convenio (cliente_id, convenio_id, numero_socio, created_at, updated_at)
SELECT i, convenio, CASE WHEN convenio IN (3, 4, 5) THEN 'S-' || (10000 + i * 37) END, traslado - interval '2 days', traslado - interval '2 days'
  FROM demo_hist WHERE convenio IS NOT NULL;

INSERT INTO difuntos (id, nombre, rut, fecha_nacimiento, fecha_fallecimiento, created_at, updated_at)
SELECT h.i,
       n[1 + (h.i * 13 + 5) % array_length(n, 1)] || ' ' || n[1 + (h.i * 17 + 2) % array_length(n, 1)] || ' '
           || a[1 + (h.i * 11) % array_length(a, 1)] || ' ' || a[1 + (h.i * 3 + 8) % array_length(a, 1)],
       pg_temp.rut(3000000 + (h.i * 7919) % 10000000),
       (h.traslado::date - make_interval(years => CASE WHEN h.infantil THEN 4 ELSE 58 + (h.i * 7) % 38 END, days => (h.i * 19) % 300))::date,
       h.traslado::date - 2,
       h.traslado - interval '2 days', h.traslado - interval '2 days'
  FROM demo_hist h, demo_nombres;

INSERT INTO expedientes (id, cliente_id, sucursal_id, difunto_id, convenio_id, estado, created_at, created_by, updated_at)
SELECT i, i, sucursal, i, convenio, 'cerrado', traslado - interval '2 days', CASE WHEN sucursal = 2 THEN 2 ELSE 1 END, traslado
  FROM demo_hist ORDER BY i;

INSERT INTO solicitudes (id, expediente_id, sucursal_id, estado, valor_servicio, descuento, velatorio_inicio, lugar_velatorio, traslado_at,
                         lugar_sepultura, sepultura_detalle, mensaje, evaluada_por, evaluada_at, created_at, created_by, updated_at)
SELECT i, i, sucursal, 'ejecutada', valor, descuento, traslado - interval '20 hours',
       (ARRAY['Sala Velatoria Cristo Rey, Temuco', 'Domicilio particular', 'Capilla San Francisco, Temuco'])[1 + (i + sucursal) % 3],
       traslado,
       CASE WHEN ataud IN (9, 10) THEN 'Crematorio de Temuco' ELSE (ARRAY['Cementerio General de Temuco', 'Cementerio Parque Temuco'])[1 + (i * 7) % 2] END,
       CASE WHEN ataud IN (9, 10) THEN NULL ELSE 'Patio ' || (1 + i % 9) || ', sepultura ' || (10 + (i * 13) % 240) END,
       CASE WHEN i % 4 = 0 THEN 'Con cariño de sus hijos y nietos.' END,
       1, traslado - interval '30 hours', traslado - interval '40 hours', CASE WHEN sucursal = 2 THEN 2 ELSE 1 END, traslado + interval '6 hours'
  FROM demo_hist;

INSERT INTO producto_solicitud (producto_id, solicitud_id, cantidad, precio_unitario, created_at, updated_at)
SELECT p.id, h.i, 1, p.precio, h.traslado - interval '40 hours', h.traslado - interval '40 hours'
  FROM demo_hist h JOIN productos p ON p.id = h.ataud
UNION ALL
SELECT 12, i, 1 + (i % 3 = 0)::int, 85000, traslado - interval '40 hours', traslado - interval '40 hours' FROM demo_hist WHERE i % 2 = 0
UNION ALL
SELECT 13, i, 1, 120000, traslado - interval '40 hours', traslado - interval '40 hours' FROM demo_hist WHERE i % 5 = 0
UNION ALL
SELECT 14, i, 1, 25000, traslado - interval '40 hours', traslado - interval '40 hours' FROM demo_hist WHERE i % 3 = 0;

UPDATE demo_hist h SET total = h.valor - h.descuento + (SELECT SUM(cantidad * precio_unitario) FROM producto_solicitud ps WHERE ps.solicitud_id = h.i);
UPDATE demo_hist SET abono = ceil(total * 0.5 / 1000) * 1000;

INSERT INTO asignaciones (id, solicitud_id, vehiculo_id, externo, inicio, fin, activa, created_at, updated_at)
SELECT i, i, 1, false, traslado - interval '60 minutes', traslado + interval '3 hours', true, traslado - interval '40 hours', traslado - interval '30 hours'
  FROM demo_hist;

INSERT INTO asignacion_usuario (asignacion_id, usuario_id, funcion, inicio, fin, activa, created_at, updated_at)
SELECT i, CASE WHEN i % 2 = 0 THEN 3 ELSE 5 END, 'conductor', traslado - interval '60 minutes', traslado + interval '3 hours', true, traslado - interval '40 hours', traslado - interval '30 hours' FROM demo_hist
UNION ALL
SELECT i, CASE WHEN i % 2 = 0 THEN 4 ELSE 6 END, 'apoyo', traslado - interval '60 minutes', traslado + interval '3 hours', true, traslado - interval '40 hours', traslado - interval '30 hours' FROM demo_hist;

INSERT INTO actividades (expediente_id, tipo, lugar, inicio, fin, created_at, updated_at)
SELECT s.expediente_id, 'velatorio', s.lugar_velatorio, s.velatorio_inicio, s.traslado_at, s.created_at, s.created_at FROM solicitudes s JOIN demo_hist h ON h.i = s.id
UNION ALL
SELECT s.expediente_id, 'sepultacion', s.lugar_sepultura, s.traslado_at + interval '1 hour', s.traslado_at + interval '2 hours', s.created_at, s.created_at FROM solicitudes s JOIN demo_hist h ON h.i = s.id;

-- Cuotas pactadas (≈ 1 de cada 8 servicios): 3 cuotas mensuales por el saldo.
INSERT INTO cuotas (expediente_id, numero, monto, vencimiento, created_at, created_by, updated_at)
SELECT h.i, n.k,
       CASE WHEN n.k < 3 THEN floor((h.total - h.abono) / 3.0) ELSE (h.total - h.abono) - 2 * floor((h.total - h.abono) / 3.0) END,
       h.traslado::date + 30 * n.k, h.traslado, 1, h.traslado
  FROM demo_hist h CROSS JOIN generate_series(1, 3) AS n(k) WHERE h.en_cuotas;

-- Pagos: abono previo al servicio y saldo o cuotas.
CREATE TEMP TABLE demo_pagos (expediente_id int, cuota_id int, medio text, monto bigint, fecha timestamp(0), creado_por int);

INSERT INTO demo_pagos
SELECT i, NULL::int, CASE WHEN convenio IN (1, 2) THEN 'Cuota mortuoria' ELSE (ARRAY['Transferencia', 'Transferencia', 'Efectivo', 'Tarjeta de débito', 'Tarjeta de crédito'])[1 + i % 5] END,
       abono, traslado - interval '28 hours', CASE WHEN sucursal = 2 THEN 2 ELSE 1 END
  FROM demo_hist
UNION ALL
SELECT i, NULL::int, (ARRAY['Transferencia', 'Efectivo', 'Tarjeta de débito', 'Transferencia', 'Cheque'])[1 + (i * 3) % 5],
       total - abono, traslado + interval '2 days', 1
  FROM demo_hist WHERE NOT en_cuotas
UNION ALL
SELECT cu.expediente_id, cu.id, 'Transferencia', cu.monto, ((cu.vencimiento - 2 + time '11:00') AT TIME ZONE 'America/Santiago') AT TIME ZONE 'UTC', 1
  FROM cuotas cu JOIN demo_hist h ON h.i = cu.expediente_id
 WHERE cu.vencimiento < pg_temp.hoy()
   AND NOT (h.cuota_atrasada AND cu.numero = (SELECT max(c2.numero) FROM cuotas c2 WHERE c2.expediente_id = cu.expediente_id AND c2.vencimiento < pg_temp.hoy()));

INSERT INTO pagos (expediente_id, cuota_id, medio_pago_id, tipo, monto, referencia, fecha, created_at, created_by, updated_at)
SELECT d.expediente_id, d.cuota_id, m.id, 'abono', d.monto,
       CASE WHEN m.requiere_referencia THEN 'OP-' || (100000 + d.expediente_id * 97 + COALESCE(d.cuota_id, 0)) END,
       d.fecha, d.fecha, d.creado_por, d.fecha
  FROM demo_pagos d JOIN medios_pago m ON m.nombre = d.medio
 ORDER BY d.fecha;

-- Un pago registrado por error y su anulación compensatoria
INSERT INTO pagos (expediente_id, medio_pago_id, tipo, monto, fecha, created_at, created_by, updated_at)
SELECT max(i), (SELECT id FROM medios_pago WHERE nombre = 'Efectivo'), 'abono', 150000,
       (SELECT traslado FROM demo_hist WHERE i = (SELECT max(i) FROM demo_hist)) + interval '50 hours',
       (SELECT traslado FROM demo_hist WHERE i = (SELECT max(i) FROM demo_hist)) + interval '50 hours', 2,
       (SELECT traslado FROM demo_hist WHERE i = (SELECT max(i) FROM demo_hist)) + interval '50 hours'
  FROM demo_hist;
INSERT INTO pagos (expediente_id, medio_pago_id, tipo, monto, anula_pago_id, motivo_anulacion, fecha, created_at, created_by, updated_at)
SELECT p.expediente_id, p.medio_pago_id, 'anulacion', -p.monto, p.id, 'El abono se digitó dos veces; el pago real es el registrado por transferencia.',
       p.fecha + interval '3 hours', p.fecha + interval '3 hours', 1, p.fecha + interval '3 hours'
  FROM pagos p WHERE p.id = (SELECT max(id) FROM pagos);

-- ---------------------------------------------------------------------
-- Operación actual (fechas relativas a ahora)
-- ---------------------------------------------------------------------
INSERT INTO clientes (id, rut, nombre, telefono, email, direccion, comuna, created_at, created_by, updated_at) VALUES
    (1001, pg_temp.rut(13456789), 'Marcela Catrileo Painemal', '+56 9 8456 1290', 'marcela.catrileo@correo.test', 'Las Araucarias 845', 'Temuco', pg_temp.rel(-30), 1, pg_temp.rel(-30)),
    (1002, pg_temp.rut(12987654), 'Hernán Quilaqueo Soto', '+56 9 7345 8812', NULL, 'Pasaje Los Notros 112', 'Temuco', pg_temp.rel(-8), 2, pg_temp.rel(-8)),
    (1003, pg_temp.rut(10234567), 'Gladys Sandoval Jara', '+56 9 6234 7781', 'gsandoval@correo.test', 'Manuel Montt 1290', 'Temuco', pg_temp.rel(-36), 1, pg_temp.rel(-36)),
    (1004, pg_temp.rut(16345987), 'Cristián Vergara Millapán', '+56 9 9123 4456', 'cvergara@correo.test', 'Balmaceda 455', 'Temuco', pg_temp.rel(-5), 2, pg_temp.rel(-5)),
    (1005, pg_temp.rut(11876543), 'Isabel Riquelme Tapia', '+56 9 8876 2234', 'isabel.riquelme@correo.test', 'Los Aromos 330', 'Temuco', pg_temp.rel(-20), 1, pg_temp.rel(-20)),
    (1006, pg_temp.rut(17654321), 'Felipe Curiche Araya', '+56 9 7654 9981', NULL, 'Villa Los Ríos, casa 28', 'Temuco', pg_temp.rel(-4), 2, pg_temp.rel(-4)),
    (1007, pg_temp.rut(14321987), 'Paola Huenchullán Reyes', '+56 9 6543 1122', 'paola.h@correo.test', 'Caupolicán 1780', 'Temuco', pg_temp.rel(-28), 1, pg_temp.rel(-28)),
    (1008, pg_temp.rut(15987321), 'Mauricio Flores Ñancupil', '+56 9 9988 7766', NULL, 'Lautaro 980', 'Temuco', pg_temp.rel(-50), 2, pg_temp.rel(-50)),
    (1009, pg_temp.rut(9876123), 'Ramón Espinoza Castillo', '+56 9 8899 0011', NULL, 'Aníbal Pinto 220', 'Temuco', pg_temp.rel(-150), 1, pg_temp.rel(-150));

INSERT INTO cliente_convenio (cliente_id, convenio_id, numero_socio, created_at, updated_at) VALUES
    (1001, 1, NULL, now(), now()), (1003, 3, 'S-48211', now(), now()), (1007, 5, 'S-10933', now(), now());

INSERT INTO difuntos (id, nombre, rut, fecha_nacimiento, fecha_fallecimiento, created_at, updated_at) VALUES
    (1001, 'Rosa Elena Catrileo Painemal', pg_temp.rut(4123456), pg_temp.hoy() - 31000, pg_temp.hoy() - 1, now(), now()),
    (1002, 'Armando Quilaqueo Curín', pg_temp.rut(5234567), pg_temp.hoy() - 28500, pg_temp.hoy(), now(), now()),
    (1003, 'Luis Alberto Sandoval Pérez', pg_temp.rut(3987654), pg_temp.hoy() - 32400, pg_temp.hoy() - 2, now(), now()),
    (1004, 'Teresa del Carmen Vergara Silva', pg_temp.rut(6123987), pg_temp.hoy() - 29800, pg_temp.hoy(), now(), now()),
    (1005, 'Osvaldo Riquelme Torres', pg_temp.rut(4765432), pg_temp.hoy() - 30500, pg_temp.hoy() - 1, now(), now()),
    (1006, 'Berta Curiche Lagos', pg_temp.rut(5876123), pg_temp.hoy() - 33100, pg_temp.hoy(), now(), now()),
    (1007, 'Nelson Huenchullán Morales', pg_temp.rut(6543219), pg_temp.hoy() - 27400, pg_temp.hoy() - 1, now(), now()),
    (1008, 'Olga Flores Ñancupil', pg_temp.rut(4321765), pg_temp.hoy() - 30900, pg_temp.hoy() - 2, now(), now()),
    (1009, 'Víctor Espinoza Rivas', pg_temp.rut(3456123), pg_temp.hoy() - 29100, pg_temp.hoy() - 7, now(), now());

INSERT INTO expedientes (id, cliente_id, sucursal_id, difunto_id, convenio_id, estado, observaciones, created_at, created_by, updated_at) VALUES
    (1001, 1001, 1, 1001, 1, 'en_servicio', 'La familia solicita misa en la capilla antes del traslado.', pg_temp.rel(-30), 1, pg_temp.rel(-20)),
    (1002, 1002, 2, 1002, NULL, 'en_servicio', NULL, pg_temp.rel(-8), 2, pg_temp.rel(-6)),
    (1003, 1003, 1, 1003, 3, 'en_servicio', 'Traslado con carroza arrendada: la principal está ocupada.', pg_temp.rel(-36), 1, pg_temp.rel(-30)),
    (1004, 1004, 2, 1004, NULL, 'abierto', NULL, pg_temp.rel(-5), 2, pg_temp.rel(-5)),
    (1005, 1005, 1, 1005, NULL, 'abierto', NULL, pg_temp.rel(-20), 1, pg_temp.rel(-20)),
    (1006, 1006, 2, 1006, NULL, 'abierto', NULL, pg_temp.rel(-4), 2, pg_temp.rel(-4)),
    (1007, 1007, 1, 1007, 5, 'en_servicio', NULL, pg_temp.rel(-28), 1, pg_temp.rel(-26)),
    (1008, 1008, 2, 1008, NULL, 'anulado', 'La familia contrató el servicio en otra ciudad.', pg_temp.rel(-50), 2, pg_temp.rel(-24)),
    (1009, 1009, 1, 1009, NULL, 'anulado', NULL, pg_temp.rel(-150), 1, pg_temp.rel(-140));

INSERT INTO solicitudes (id, expediente_id, sucursal_id, estado, valor_servicio, descuento, velatorio_inicio, lugar_velatorio, traslado_at,
                         lugar_sepultura, sepultura_detalle, mensaje, comentario, evaluada_por, evaluada_at, motivo_cancelacion, created_at, created_by, updated_at) VALUES
    -- En velatorio ahora; traslado en unas horas.
    (1001, 1001, 1, 'aceptada', 990000, 0, pg_temp.rel(-20), 'Sala Velatoria Cristo Rey, Temuco', pg_temp.rel(2.5),
        'Cementerio General de Temuco', 'Patio 7, sepultura familiar 23', 'Siempre en nuestros corazones. Tus hijos y nietos.', NULL, 1, pg_temp.rel(-24), NULL, pg_temp.rel(-30), 1, pg_temp.rel(-24)),
    -- Velatorio recién iniciado; traslado mañana.
    (1002, 1002, 2, 'aceptada', 790000, 0, pg_temp.loc(0, '09:00'), 'Domicilio particular, Temuco', pg_temp.loc(1, '11:00'),
        'Cementerio General de Temuco', 'Nicho 412, pabellón B', NULL, NULL, 1, pg_temp.rel(-6), NULL, pg_temp.rel(-8), 2, pg_temp.rel(-6)),
    -- En traslado ahora mismo con la carroza arrendada.
    (1003, 1003, 1, 'aceptada', 1190000, 119000, pg_temp.rel(-26), 'Capilla San Francisco, Temuco', pg_temp.rel(-0.5),
        'Cementerio Parque Temuco', 'Sector Jardín, sepultura 88', NULL, NULL, 1, pg_temp.rel(-30), NULL, pg_temp.rel(-36), 1, pg_temp.rel(-30)),
    -- En revisión: el abono aún no alcanza el mínimo.
    (1004, 1004, 2, 'en_revision', 890000, 0, pg_temp.loc(2, '18:00'), 'Sala Velatoria Cristo Rey, Temuco', pg_temp.loc(3, '11:00'),
        'Cementerio General de Temuco', NULL, NULL, NULL, NULL, NULL, NULL, pg_temp.rel(-5), 2, pg_temp.rel(-5)),
    -- En revisión con abono suficiente: lista para aceptar.
    (1005, 1005, 1, 'en_revision', 1390000, 0, pg_temp.loc(1, '17:00'), 'Sala Velatoria Cristo Rey, Temuco', pg_temp.loc(2, '16:00'),
        'Cementerio General de Temuco', 'Patio 2, mausoleo familia Riquelme', 'Descansa en paz, papá.', NULL, NULL, NULL, NULL, pg_temp.rel(-20), 1, pg_temp.rel(-20)),
    -- Con observaciones del administrador.
    (1006, 1006, 2, 'con_observaciones', 690000, 0, pg_temp.loc(3, '17:00'), 'Domicilio particular, Temuco', pg_temp.loc(4, '15:00'),
        'Cementerio Parque Temuco', 'Nicho 77', NULL, 'El nicho indicado no está disponible según el cementerio. Confirmar otro con la familia.', 1, pg_temp.rel(-2), NULL, pg_temp.rel(-4), 2, pg_temp.rel(-2)),
    -- Aceptada para pasado mañana.
    (1007, 1007, 1, 'aceptada', 1190000, 143000, pg_temp.loc(1, '19:00'), 'Sala Velatoria Cristo Rey, Temuco', pg_temp.loc(2, '10:00'),
        'Cementerio General de Temuco', 'Patio 5, sepultura 140', NULL, NULL, 1, pg_temp.rel(-26), NULL, pg_temp.rel(-28), 1, pg_temp.rel(-26)),
    -- Cancelada por la familia.
    (1008, 1008, 2, 'cancelada', 790000, 0, pg_temp.loc(0, '20:00'), 'Domicilio particular, Temuco', pg_temp.loc(1, '15:00'),
        'Cementerio General de Temuco', NULL, NULL, NULL, 1, pg_temp.rel(-40), 'La familia optó por realizar el servicio en Santiago.', pg_temp.rel(-50), 2, pg_temp.rel(-24)),
    -- No aplica.
    (1009, 1009, 1, 'no_aplica', 890000, 0, pg_temp.rel(-140), 'Domicilio particular (fuera del área de cobertura)', pg_temp.rel(-120),
        'Cementerio fuera del área de cobertura', NULL, NULL, 'El traslado solicitado queda fuera del área de cobertura de la funeraria.', 1, pg_temp.rel(-146), NULL, pg_temp.rel(-150), 1, pg_temp.rel(-146));

INSERT INTO producto_solicitud (producto_id, solicitud_id, cantidad, precio_unitario, created_at, updated_at) VALUES
    (1, 1001, 1, 1250000, now(), now()), (12, 1001, 2, 85000, now(), now()), (14, 1001, 1, 25000, now(), now()),
    (2, 1002, 1, 480000, now(), now()), (12, 1002, 1, 85000, now(), now()),
    (3, 1003, 1, 1590000, now(), now()), (13, 1003, 1, 120000, now(), now()),
    (4, 1004, 1, 890000, now(), now()),
    (11, 1005, 1, 1450000, now(), now()), (13, 1005, 1, 120000, now(), now()), (15, 1005, 1, 45000, now(), now()),
    (5, 1006, 1, 320000, now(), now()),
    (1, 1007, 1, 1250000, now(), now()), (12, 1007, 1, 85000, now(), now()),
    (2, 1008, 1, 480000, now(), now()),
    (4, 1009, 1, 890000, now(), now());

INSERT INTO asignaciones (id, solicitud_id, vehiculo_id, externo, inicio, fin, activa, created_at, updated_at) VALUES
    (1001, 1001, 1, false, pg_temp.rel(1.5), pg_temp.rel(5.5), true, now(), now()),
    (1002, 1002, 1, false, pg_temp.loc(1, '10:00'), pg_temp.loc(1, '14:00'), true, now(), now()),
    (1003, 1003, 4, true, pg_temp.rel(-1.5), pg_temp.rel(2.5), true, now(), now()),
    (1004, 1004, 1, false, pg_temp.loc(3, '10:00'), pg_temp.loc(3, '14:00'), false, now(), now()),
    (1005, 1005, 1, false, pg_temp.loc(2, '15:00'), pg_temp.loc(2, '19:00'), false, now(), now()),
    (1006, 1006, 1, false, pg_temp.loc(4, '14:00'), pg_temp.loc(4, '18:00'), false, now(), now()),
    (1007, 1007, 1, false, pg_temp.loc(2, '09:00'), pg_temp.loc(2, '13:00'), true, now(), now()),
    (1008, 1008, 1, false, pg_temp.loc(1, '14:00'), pg_temp.loc(1, '18:00'), false, now(), now()),
    (1009, 1009, 1, false, pg_temp.rel(-121), pg_temp.rel(-117), false, now(), now());

INSERT INTO asignacion_usuario (asignacion_id, usuario_id, funcion, inicio, fin, activa, created_at, updated_at)
SELECT a.id, x.usuario_id, x.funcion, a.inicio, a.fin, a.activa, now(), now()
  FROM (VALUES (1001, 3, 'conductor'), (1001, 4, 'apoyo'), (1002, 4, 'conductor'), (1002, 3, 'apoyo'), (1003, 5, 'apoyo'),
               (1004, 3, 'conductor'), (1004, 5, 'apoyo'), (1005, 4, 'conductor'), (1005, 5, 'apoyo'), (1006, 3, 'conductor'),
               (1007, 3, 'conductor'), (1007, 5, 'apoyo'), (1008, 4, 'conductor'), (1009, 3, 'conductor')) AS x(asignacion_id, usuario_id, funcion)
  JOIN asignaciones a ON a.id = x.asignacion_id;

INSERT INTO actividades (expediente_id, tipo, lugar, inicio, fin, notas, created_at, updated_at) VALUES
    (1001, 'retiro', 'Hospital Regional de Temuco', pg_temp.rel(-26), pg_temp.rel(-25), 'Retiro con camioneta de apoyo.', now(), now()),
    (1001, 'velatorio', 'Sala Velatoria Cristo Rey, Temuco', pg_temp.rel(-20), pg_temp.rel(2.5), NULL, now(), now()),
    (1001, 'misa', 'Capilla de la sala velatoria', pg_temp.rel(1), pg_temp.rel(2), 'Responso a cargo del párroco.', now(), now()),
    (1001, 'sepultacion', 'Cementerio General de Temuco', pg_temp.rel(3.5), pg_temp.rel(4.5), NULL, now(), now()),
    (1002, 'velatorio', 'Domicilio particular, Temuco', pg_temp.loc(0, '09:00'), pg_temp.loc(1, '11:00'), 'Instalación con el furgón 2.', now(), now()),
    (1002, 'misa', 'Parroquia San Francisco, Temuco', pg_temp.loc(1, '09:30'), pg_temp.loc(1, '10:30'), NULL, now(), now()),
    (1002, 'sepultacion', 'Cementerio General de Temuco', pg_temp.loc(1, '12:00'), pg_temp.loc(1, '13:00'), NULL, now(), now()),
    (1003, 'velatorio', 'Capilla San Francisco, Temuco', pg_temp.rel(-26), pg_temp.rel(-0.5), NULL, now(), now()),
    (1003, 'sepultacion', 'Cementerio Parque Temuco', pg_temp.rel(0.5), pg_temp.rel(1.5), NULL, now(), now()),
    (1007, 'velatorio', 'Sala Velatoria Cristo Rey, Temuco', pg_temp.loc(1, '19:00'), pg_temp.loc(2, '10:00'), NULL, now(), now()),
    (1007, 'sepultacion', 'Cementerio General de Temuco', pg_temp.loc(2, '11:00'), pg_temp.loc(2, '12:00'), NULL, now(), now());

INSERT INTO pagos (expediente_id, medio_pago_id, tipo, monto, referencia, fecha, created_at, created_by, updated_at)
SELECT x.expediente_id, m.id, 'abono', x.monto, x.referencia, x.fecha, x.fecha, p.por, x.fecha
  FROM (VALUES (1001, 'Transferencia', 1000000, 'OP-772311', pg_temp.rel(-29)),
               (1001, 'Cuota mortuoria', 450000, 'AFP-2291', pg_temp.rel(-22)),
               (1002, 'Efectivo', 700000, NULL, pg_temp.rel(-7)),
               (1003, 'Transferencia', 1600000, 'OP-772450', pg_temp.rel(-35)),
               (1003, 'Tarjeta de débito', 500000, 'TBK-55102', pg_temp.rel(-31)),
               (1004, 'Transferencia', 300000, 'OP-772598', pg_temp.rel(-4)),
               (1005, 'Transferencia', 1800000, 'OP-772603', pg_temp.rel(-18)),
               (1007, 'Tarjeta de crédito', 1300000, 'TBK-55190', pg_temp.rel(-27)),
               (1008, 'Efectivo', 200000, NULL, pg_temp.rel(-49))) AS x(expediente_id, medio, monto, referencia, fecha)
  JOIN medios_pago m ON m.nombre = x.medio
  CROSS JOIN LATERAL (SELECT CASE WHEN x.expediente_id IN (1002, 1004, 1006, 1008) THEN 2 ELSE 1 END AS por) p;

-- Devolución del abono del servicio cancelado (anulación compensatoria).
INSERT INTO pagos (expediente_id, medio_pago_id, tipo, monto, anula_pago_id, motivo_anulacion, fecha, created_at, created_by, updated_at)
SELECT p.expediente_id, p.medio_pago_id, 'anulacion', -p.monto, p.id, 'Devolución del abono: la familia canceló el servicio.',
       pg_temp.rel(-23), pg_temp.rel(-23), 1, pg_temp.rel(-23)
  FROM pagos p WHERE p.expediente_id = 1008;

INSERT INTO comprobantes (pago_id, emitido_at, created_at, created_by, updated_at)
SELECT id, fecha, fecha, created_by, fecha FROM pagos ORDER BY fecha, id;

-- ---------------------------------------------------------------------
-- Movimientos de inventario coherentes con el stock actual
-- ---------------------------------------------------------------------
CREATE TEMP TABLE demo_mov (producto_id int, solicitud_id int, proveedor_id int, tipo text, cantidad int, motivo text, documento text, fecha timestamp(0), por int);

INSERT INTO demo_mov
SELECT ps.producto_id, ps.solicitud_id, NULL::int, 'salida', -ps.cantidad, 'Reserva por solicitud aceptada', NULL, COALESCE(s.evaluada_at, s.created_at), 1
  FROM producto_solicitud ps JOIN solicitudes s ON s.id = ps.solicitud_id WHERE s.estado IN ('aceptada', 'ejecutada');

INSERT INTO demo_mov
SELECT p.id, NULL, p.proveedor_id, 'ingreso', x.cantidad, 'Ingreso de mercadería', 'Factura ' || x.doc, x.fecha, x.por
  FROM (VALUES (2, 4, '18842', pg_temp.rel(-240), 3), (4, 3, '7731', pg_temp.rel(-200), 1), (9, 4, '2290', pg_temp.rel(-96), 5),
               (12, 10, '9921', pg_temp.rel(-72), 3), (1, 2, '7790', pg_temp.rel(-400), 1)) AS x(producto_id, cantidad, doc, fecha, por)
  JOIN productos p ON p.id = x.producto_id;

INSERT INTO demo_mov
SELECT 14, NULL, NULL, 'ajuste', -1, 'Libro dañado durante el traslado; se da de baja.', NULL, pg_temp.rel(-120), 1;

-- Inventario inicial: lo necesario para terminar con el stock actual de cada producto.
INSERT INTO demo_mov
SELECT p.id, NULL, p.proveedor_id, 'ingreso', p.stock - COALESCE(SUM(m.cantidad), 0), 'Inventario inicial', 'Inventario ' || to_char(now() - interval '13 months', 'YYYY'),
       (now() AT TIME ZONE 'UTC' - interval '13 months')::timestamp(0), 1
  FROM productos p LEFT JOIN demo_mov m ON m.producto_id = p.id GROUP BY p.id, p.proveedor_id, p.stock;

INSERT INTO movimientos_stock (producto_id, solicitud_id, proveedor_id, tipo, cantidad, stock_resultante, motivo, documento, created_at, created_by, updated_at)
SELECT producto_id, solicitud_id, proveedor_id, tipo, cantidad,
       SUM(cantidad) OVER (PARTITION BY producto_id ORDER BY fecha, tipo DESC, solicitud_id NULLS FIRST ROWS UNBOUNDED PRECEDING),
       motivo, documento, fecha, por, fecha
  FROM demo_mov ORDER BY fecha;

-- ---------------------------------------------------------------------
-- Gastos mensuales (12 meses + mes en curso hasta ayer)
-- ---------------------------------------------------------------------
INSERT INTO gastos (sucursal_id, proveedor_id, categoria, descripcion, monto, fecha, created_at, created_by, updated_at)
SELECT g.sucursal, g.proveedor, g.categoria, g.descripcion, g.monto, g.fecha, g.fecha, 1, g.fecha
  FROM (
    SELECT m, (date_trunc('month', pg_temp.hoy()) - make_interval(months => 12 - m))::date AS inicio FROM generate_series(0, 12) m
  ) me
  CROSS JOIN LATERAL (VALUES
      (1, NULL::int, 'Remuneraciones', 'Remuneraciones del personal', 5200000 + (me.m % 3) * 60000, me.inicio + 27),
      (1, 1, 'Insumos', 'Compra de urnas y ataúdes para reposición', 3600000 + (me.m * 410000) % 1900000, me.inicio + 9),
      (1, 3, 'Flores y arreglos', 'Coronas y arreglos florales del mes', 380000 + (me.m * 53000) % 260000, me.inicio + 14),
      (1, NULL, 'Combustible', 'Combustible carroza y furgones', 310000 + (me.m * 27000) % 120000, me.inicio + 20),
      (2, NULL, 'Combustible', 'Combustible camioneta de apoyo', 140000 + (me.m * 19000) % 60000, me.inicio + 21),
      (1, NULL, 'Servicios básicos', 'Luz, agua y gas de las salas velatorias', 260000 + (me.m * 11000) % 70000, me.inicio + 5),
      (2, NULL, 'Arriendo de carroza', 'Arriendo de carroza externa', CASE WHEN me.m % 3 = 0 THEN 180000 ELSE 0 END, me.inicio + 17),
      (1, NULL, 'Mantención', 'Mantención preventiva de la carroza', CASE WHEN me.m % 4 = 1 THEN 420000 ELSE 0 END, me.inicio + 11)
  ) AS g(sucursal, proveedor, categoria, descripcion, monto, fecha)
 WHERE g.monto > 0 AND g.fecha < pg_temp.hoy();

-- ---------------------------------------------------------------------
-- Ausencias, avisos y bitácora
-- ---------------------------------------------------------------------
INSERT INTO ausencias (usuario_id, tipo, desde, hasta, motivo, created_at, created_by, updated_at) VALUES
    (6, 'vacaciones', pg_temp.hoy() - 2, pg_temp.hoy() + 5, 'Feriado legal', now(), 1, now()),
    (5, 'permiso', pg_temp.hoy() - 40, pg_temp.hoy() - 39, 'Trámite personal', now(), 1, now()),
    (4, 'licencia', pg_temp.hoy() + 12, pg_temp.hoy() + 16, 'Licencia médica programada', now(), 1, now());

INSERT INTO avisos (usuario_id, tipo, titulo, mensaje, enlace, leido_at, created_at, updated_at) VALUES
    (1, 'info', 'Solicitud en revisión', 'La solicitud de Osvaldo Riquelme Torres espera su evaluación. El abono mínimo está cumplido.', '/solicitudes/1005', NULL, pg_temp.rel(-20), pg_temp.rel(-20)),
    (1, 'info', 'Solicitud en revisión', 'Nueva solicitud para Teresa del Carmen Vergara Silva. El abono aún no alcanza el mínimo.', '/solicitudes/1004', NULL, pg_temp.rel(-5), pg_temp.rel(-5)),
    (1, 'alerta', 'Stock crítico', 'Ángel Blanco (infantil) quedó con 1 unidad (mínimo 2).', '/inventario?criticos=1', NULL, pg_temp.rel(-30), pg_temp.rel(-30)),
    (1, 'alerta', 'Stock crítico', 'Colonial Raulí quedó con 2 unidades (mínimo 2).', '/inventario?criticos=1', pg_temp.rel(-29), pg_temp.rel(-30), pg_temp.rel(-29)),
    (1, 'critico', 'Cuota atrasada', 'Hay cuotas vencidas sin pagar. Revise la cobranza.', '/cobranza', NULL, pg_temp.rel(-14), pg_temp.rel(-14)),
    (2, 'exito', 'Solicitud aceptada', 'La solicitud de Armando Quilaqueo Curín fue aceptada. Vehículo y equipo confirmados.', '/solicitudes/1002', pg_temp.rel(-5), pg_temp.rel(-6), pg_temp.rel(-5)),
    (2, 'alerta', 'Solicitud con observaciones', 'Berta Curiche Lagos: el nicho indicado no está disponible. Confirmar otro con la familia.', '/solicitudes/1006', NULL, pg_temp.rel(-2), pg_temp.rel(-2)),
    (3, 'info', 'Nuevo servicio asignado', 'Conductor del traslado de Rosa Elena Catrileo Painemal. Presentarse en la sala de Temuco.', '/itinerario', NULL, pg_temp.rel(-24), pg_temp.rel(-24)),
    (4, 'info', 'Nuevo servicio asignado', 'Apoyo en el traslado de Rosa Elena Catrileo Painemal.', '/itinerario', NULL, pg_temp.rel(-24), pg_temp.rel(-24)),
    (5, 'info', 'Nuevo servicio asignado', 'Apoyo en el traslado de Nelson Huenchullán Morales.', '/itinerario', NULL, pg_temp.rel(-26), pg_temp.rel(-26));

INSERT INTO auditorias (usuario_id, accion, entidad, entidad_id, antes, despues, motivo, ip, user_agent, fecha_utc) VALUES
    (1, 'aceptar', 'solicitudes', '1001', '{"estado": "en_revision"}', '{"estado": "aceptada"}', NULL, '190.20.14.8', 'Mozilla/5.0', pg_temp.rel(-24)),
    (1, 'aceptar', 'solicitudes', '1003', '{"estado": "en_revision"}', '{"estado": "aceptada"}', NULL, '190.20.14.8', 'Mozilla/5.0', pg_temp.rel(-30)),
    (1, 'aceptar', 'solicitudes', '1007', '{"estado": "en_revision"}', '{"estado": "aceptada"}', NULL, '190.20.14.8', 'Mozilla/5.0', pg_temp.rel(-26)),
    (1, 'aceptar', 'solicitudes', '1002', '{"estado": "en_revision"}', '{"estado": "aceptada"}', NULL, '190.20.14.8', 'Mozilla/5.0', pg_temp.rel(-6)),
    (1, 'observar', 'solicitudes', '1006', '{"estado": "en_revision"}', '{"estado": "con_observaciones"}', 'El nicho indicado no está disponible según el cementerio.', '190.20.14.8', 'Mozilla/5.0', pg_temp.rel(-2)),
    (2, 'cancelar', 'solicitudes', '1008', NULL, '{"estado": "cancelada", "liberados": false}', 'La familia optó por realizar el servicio en Santiago.', '181.43.2.19', 'Mozilla/5.0', pg_temp.rel(-24)),
    (1, 'anular', 'pagos', (SELECT min(id)::text FROM pagos WHERE tipo = 'anulacion'), NULL, '{"monto": -150000}', 'El abono se digitó dos veces.', '190.20.14.8', 'Mozilla/5.0', (SELECT fecha FROM pagos WHERE tipo = 'anulacion' ORDER BY id LIMIT 1)),
    (1, 'anular', 'pagos', (SELECT max(id)::text FROM pagos WHERE tipo = 'anulacion'), NULL, '{"monto": -200000}', 'Devolución del abono: la familia canceló el servicio.', '190.20.14.8', 'Mozilla/5.0', pg_temp.rel(-23)),
    (1, 'ajustar_stock', 'productos', '14', '{"stock": 21}', '{"stock": 20}', 'Libro dañado durante el traslado; se da de baja.', '190.20.14.8', 'Mozilla/5.0', pg_temp.rel(-120)),
    (1, 'inicio_sesion', 'usuarios', '1', NULL, NULL, NULL, '190.20.14.8', 'Mozilla/5.0', pg_temp.rel(-1)),
    (2, 'inicio_sesion', 'usuarios', '2', NULL, NULL, NULL, '181.43.2.19', 'Mozilla/5.0', pg_temp.rel(-8)),
    (NULL, 'inicio_sesion_fallido', 'usuarios', NULL, NULL, '{"email": "renato.osses@cristorey.test"}', NULL, '45.160.3.77', 'curl/8.4', pg_temp.rel(-12));

-- ---------------------------------------------------------------------
-- Estado de los expedientes históricos y secuencias
-- ---------------------------------------------------------------------
UPDATE expedientes e SET estado = 'en_servicio'
  FROM vista_saldos_expediente v WHERE v.expediente_id = e.id AND e.id < 1000 AND v.saldo > 0;

DO $$
DECLARE t text;
BEGIN
    FOREACH t IN ARRAY ARRAY['sucursales', 'usuarios', 'roles', 'clientes', 'convenios', 'difuntos', 'expedientes', 'actividades', 'proveedores',
                             'productos', 'solicitudes', 'movimientos_stock', 'vehiculos', 'asignaciones', 'ausencias', 'medios_pago', 'cuotas',
                             'pagos', 'comprobantes', 'gastos', 'archivos', 'auditorias', 'notificaciones', 'avisos'] LOOP
        EXECUTE format('SELECT setval(pg_get_serial_sequence(%L, ''id''), GREATEST((SELECT COALESCE(max(id), 0) FROM %I), 1))', t, t);
    END LOOP;
END $$;

DROP TABLE demo_hist, demo_nombres, demo_pagos, demo_mov;