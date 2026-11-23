/* Tipos de los recursos que entrega la API (contrato con Laravel) */

export type Rol = "Administrador" | "Coordinador" | "Operario";

export type Sesion = {
  id: number;
  nombre: string;
  email: string;
  sucursal: { id: number; nombre: string } | null;
  rol: Rol;
  roles: Rol[];
  suplencia_hasta: string | null;
  permisos: string[];
  mfa_activo: boolean;
  mfa_requerido: boolean;
};

export type EstadoSolicitud =
  | "en_revision"
  | "con_observaciones"
  | "aceptada"
  | "ejecutada"
  | "no_aplica"
  | "cancelada";
export type EstadoExpediente =
  | "abierto"
  | "en_servicio"
  | "cerrado"
  | "anulado";
export type TipoProducto = "ataud" | "urna" | "urna_cremacion" | "elemento";

export type Sucursal = {
  id: number;
  nombre: string;
  direccion?: string | null;
  telefono?: string | null;
};

export type Convenio = {
  id: number;
  nombre: string;
  institucion: string;
  tipo: "descuento" | "cuota_mortuoria" | "institucional";
  descuento_pct: number;
  vigencia_desde: string;
  vigencia_hasta: string | null;
  descripcion: string | null;
  contacto: string | null;
  activo: boolean;
  vigente?: boolean;
  clientes_count?: number;
};

export type Cliente = {
  id: number;
  rut: string;
  nombre: string;
  telefono: string | null;
  email: string | null;
  direccion: string | null;
  comuna: string | null;
  convenios?: (Pick<Convenio, "id" | "nombre"> & {
    pivot?: { numero_socio: string | null };
  })[];
};

export type Difunto = {
  id: number;
  nombre: string;
  rut: string | null;
  fecha_nacimiento: string | null;
  fecha_fallecimiento: string;
};

export type Resumen = {
  total: number;
  pagado: number;
  saldo: number;
  abono_minimo?: number;
  abono_cumplido?: boolean;
  abono_minimo_pct?: number;
};

export type FilaExpediente = {
  id: number;
  codigo: string;
  estado: EstadoExpediente;
  created_at: string;
  cliente_id: number;
  cliente: string;
  cliente_rut: string;
  difunto: string;
  sucursal: string;
  total: number | null;
  pagado: number | null;
  saldo: number | null;
};

export type Actividad = {
  id: number;
  expediente_id: number;
  tipo: "retiro" | "velatorio" | "misa" | "traslado" | "sepultacion" | "otra";
  lugar: string;
  inicio: string;
  fin: string | null;
  notas: string | null;
};

export type Documento = {
  id: number;
  tipo:
    | "certificado_defuncion"
    | "carnet_difunto"
    | "carnet_contratante"
    | "contrato"
    | "otro";
  nombre_original: string;
  mime: string;
  tamano: number;
  created_at: string;
};

export type Proveedor = {
  id: number;
  nombre: string;
  rut: string | null;
  rubro: string;
  contacto: string | null;
  telefono: string | null;
  email: string | null;
  direccion: string | null;
  notas: string | null;
  activo: boolean;
  productos_count?: number;
  productos?: Producto[];
};

export type Producto = {
  id: number;
  proveedor_id: number | null;
  proveedor?: { id: number; nombre: string } | null;
  codigo: string;
  nombre: string;
  tipo: TipoProducto;
  material: string | null;
  color: string | null;
  medidas: string | null;
  largo_cm: number | null;
  ancho_cm: number | null;
  alto_cm: number | null;
  descripcion: string | null;
  precio: number;
  stock: number;
  umbral_minimo: number;
  infantil: boolean;
  publico: boolean;
  foto_url: string | null;
  critico?: boolean;
  atributos?: Record<string, unknown>;
  pivot?: { cantidad: number; precio_unitario: number };
};

/** Foto de un modelo para el carrusel: la principal es la vista superior (id nulo) */
export type FotoProducto = {
  id: number | null;
  url: string;
  vista: "superior" | "abierta" | "lateral" | "otra";
};

export type ProductoPublico = Pick<
  Producto,
  | "id"
  | "codigo"
  | "nombre"
  | "tipo"
  | "material"
  | "color"
  | "medidas"
  | "largo_cm"
  | "ancho_cm"
  | "alto_cm"
  | "descripcion"
  | "precio"
  | "infantil"
  | "foto_url"
> & { galeria?: FotoProducto[] };

export type Vehiculo = {
  id: number;
  nombre: string;
  tipo: "carroza" | "instalacion" | "apoyo";
  patente: string | null;
  marca: string | null;
  modelo: string | null;
  anio: number | null;
  origen: "propio" | "externo";
  proveedor_externo: string | null;
  activo: boolean;
  notas: string | null;
};

export type MiembroEquipo = {
  id: number;
  nombre: string;
  pivot?: { funcion: "conductor" | "apoyo" };
};

export type Asignacion = {
  id: number;
  solicitud_id: number;
  vehiculo_id: number;
  externo: boolean;
  inicio: string;
  fin: string;
  activa: boolean;
  vehiculo?: Pick<
    Vehiculo,
    "id" | "nombre" | "patente" | "origen" | "proveedor_externo"
  >;
  equipo?: MiembroEquipo[];
};

export type Solicitud = {
  id: number;
  expediente_id: number;
  sucursal_id: number;
  estado: EstadoSolicitud;
  valor_servicio: number;
  descuento: number;
  velatorio_inicio: string;
  lugar_velatorio: string;
  traslado_at: string;
  lugar_sepultura: string;
  sepultura_detalle: string | null;
  mensaje: string | null;
  comentario: string | null;
  evaluada_por: number | null;
  evaluada_at: string | null;
  motivo_cancelacion: string | null;
  created_at: string;
  productos: Producto[];
  asignacion: Asignacion | null;
  sucursal?: Sucursal;
  evaluador?: { id: number; nombre: string } | null;
  expediente?: Expediente;
  resumen?: Resumen;
};

export type FilaSolicitud = {
  id: number;
  estado: EstadoSolicitud;
  velatorio_inicio: string;
  traslado_at: string;
  lugar_velatorio: string;
  lugar_sepultura: string;
  comentario: string | null;
  created_at: string;
  expediente_id: number;
  codigo: string;
  cliente: string;
  difunto: string;
  sucursal: string;
  total: number;
  pagado: number;
  saldo: number;
  vehiculo: string | null;
  vehiculo_origen: "propio" | "externo" | null;
  abono_minimo: number;
  abono_cumplido: boolean;
};

export type Expediente = {
  id: number;
  codigo: string;
  estado: EstadoExpediente;
  observaciones: string | null;
  created_at: string;
  cliente: Cliente;
  difunto: Difunto;
  sucursal: Sucursal;
  convenio: Pick<Convenio, "id" | "nombre" | "descuento_pct"> | null;
  actividades: Actividad[];
  documentos: Documento[];
  solicitudes: Solicitud[];
  resumen: Resumen;
};

export type MedioPago = {
  id: number;
  nombre: string;
  requiere_referencia: boolean;
  activo: boolean;
};

export type Pago = {
  id: number;
  expediente_id: number;
  cuota_id: number | null;
  medio_pago_id: number;
  tipo: "abono" | "anulacion";
  monto: number;
  referencia: string | null;
  anula_pago_id: number | null;
  motivo_anulacion: string | null;
  fecha: string;
  anulado?: boolean;
  medio_pago?: { id: number; nombre: string };
  comprobante?: { id: number; pago_id: number; folio: string } | null;
  registrado_por?: { id: number; nombre: string } | null;
};

export type FilaPago = {
  id: number;
  tipo: "abono" | "anulacion";
  monto: number;
  fecha: string;
  referencia: string | null;
  motivo_anulacion: string | null;
  expediente_id: number;
  codigo: string;
  cliente: string;
  medio: string;
  comprobante_id: number | null;
  folio: string | null;
  usuario: string | null;
};

export type Cuota = {
  id: number;
  expediente_id: number;
  numero: number;
  monto: number;
  vencimiento: string;
  pagado: number;
  estado: "pagada" | "pendiente" | "atrasada";
};

export type FilaCobranza = Cuota & {
  codigo: string;
  cliente: string;
  telefono: string | null;
};

export type Finanzas = { resumen: Resumen; pagos: Pago[]; cuotas: Cuota[] };

export type Gasto = {
  id: number;
  sucursal_id: number;
  proveedor_id: number | null;
  categoria: string;
  descripcion: string;
  monto: number;
  fecha: string;
  sucursal?: { id: number; nombre: string };
  proveedor?: { id: number; nombre: string } | null;
};

export type MovimientoStock = {
  id: number;
  producto_id: number;
  solicitud_id: number | null;
  tipo: "ingreso" | "salida" | "reposicion" | "ajuste" | "merma";
  cantidad: number;
  stock_resultante: number;
  motivo: string;
  documento: string | null;
  created_at: string;
  codigo: string;
  producto: string;
  usuario: string | null;
  proveedor: string | null;
};

export type Ausencia = {
  id: number;
  usuario_id: number;
  tipo: "vacaciones" | "licencia" | "permiso" | "otra";
  desde: string;
  hasta: string;
  motivo: string | null;
  usuario?: { id: number; nombre: string };
};

export type Usuario = {
  id: number;
  nombre: string;
  email: string;
  rut: string;
  telefono: string | null;
  activo: boolean;
  sucursal: string | null;
  sucursal_id: number;
  rol: Rol;
  suplencia_hasta: string | null;
  mfa_activo: boolean;
  ultimo_acceso_at: string | null;
};

export type Parametro = {
  clave: string;
  valor: number;
  descripcion: string | null;
};

export type Aviso = {
  id: number;
  tipo: "info" | "exito" | "alerta" | "critico";
  titulo: string;
  mensaje: string;
  enlace: string | null;
  leido_at: string | null;
  created_at: string;
};

export type Auditoria = {
  id: number;
  usuario_id: number | null;
  usuario: string | null;
  accion: string;
  entidad: string;
  entidad_id: string | null;
  antes: Record<string, unknown> | null;
  despues: Record<string, unknown> | null;
  motivo: string | null;
  ip: string | null;
  fecha_utc: string;
};

export type ItemItinerario = {
  id: number;
  codigo: string;
  difunto: string;
  sucursal: string;
  velatorio_inicio: string;
  lugar_velatorio: string;
  traslado_at: string;
  presentacion_at: string;
  lugar_sepultura: string;
  sepultura_detalle: string | null;
  estado: EstadoSolicitud;
  vehiculo: Pick<Vehiculo, "id" | "nombre" | "patente" | "origen">;
  equipo: { id: number; nombre: string; funcion: "conductor" | "apoyo" }[];
};

export type EventoCalendario = {
  id: string;
  tipo: "velatorio" | "traslado" | "retiro" | "misa" | "otra";
  titulo: string;
  inicio: string;
  fin: string | null;
  lugar: string;
  estado: EstadoSolicitud;
  codigo: string;
  expediente_id: number;
  solicitud_id?: number;
  difunto?: string;
  vehiculo?: string | null;
};

export type Disponibilidad = {
  inicio: string;
  fin: string;
  vehiculos: (Pick<
    Vehiculo,
    "id" | "nombre" | "tipo" | "patente" | "origen" | "proveedor_externo"
  > & { ocupado: boolean })[];
  operarios: {
    id: number;
    nombre: string;
    ocupado: boolean;
    ausente: boolean;
  }[];
};

export type ServicioEnCurso = {
  id: number;
  expediente_id: number;
  codigo: string;
  difunto: string;
  velatorio_inicio: string;
  lugar_velatorio: string;
  traslado_at: string;
  lugar_sepultura: string;
  fin: string;
  vehiculo: string | null;
  etapa: "velatorio" | "traslado";
  ubicacion: string;
};

export type ServicioDelDia = {
  id: number;
  codigo: string;
  difunto: string;
  velatorio_inicio: string;
  traslado_at: string;
  presentacion: string;
  fin: string;
  lugar_velatorio: string;
  lugar_sepultura: string;
  vehiculo: string | null;
};

export type Panel = {
  kpis: {
    servicios_mes: number;
    servicios_mes_anterior: number;
    en_curso: number;
    en_revision: number;
    cuotas_atrasadas: number;
    stock_critico: number;
    ingresos_mes?: number;
    gastos_mes?: number;
    resultado_mes?: number;
    saldo_por_cobrar?: number;
  };
  servicios_por_mes: { mes: string; total: number }[];
  finanzas_por_mes: { mes: string; ingresos: number; gastos: number }[] | null;
  urnas_mas_vendidas: {
    id: number;
    codigo: string;
    nombre: string;
    cantidad: number;
    monto: number;
  }[];
  solicitudes_por_estado: Partial<Record<EstadoSolicitud, number>>;
  en_curso: ServicioEnCurso[];
  servicios_hoy: ServicioDelDia[];
  proximos_traslados: {
    id: number;
    codigo: string;
    difunto: string;
    traslado_at: string;
    presentacion: string;
    lugar_sepultura: string;
    vehiculo: string | null;
    origen: "propio" | "externo" | null;
    equipo: string[];
  }[];
  stock_critico: Pick<
    Producto,
    "id" | "codigo" | "nombre" | "stock" | "umbral_minimo" | "infantil"
  >[];
};

export type ReporteMensual = {
  mes: string;
  servicios: {
    codigo: string;
    difunto: string;
    cliente: string;
    sucursal: string;
    traslado_at: string;
    lugar_sepultura: string;
    estado: EstadoSolicitud;
    total: number;
    pagado: number;
    saldo: number;
  }[];
  totales: {
    servicios: number;
    ingresos: number;
    gastos: number;
    resultado: number;
  };
  ingresos_por_medio: { medio: string; total: number; cantidad: number }[];
  gastos_por_categoria: {
    categoria: string;
    total: number;
    cantidad: number;
  }[];
  urnas_vendidas: {
    id: number;
    codigo: string;
    nombre: string;
    cantidad: number;
    monto: number;
  }[];
};
