import AddRoundedIcon from "@mui/icons-material/AddRounded";
import AttachFileRoundedIcon from "@mui/icons-material/AttachFileRounded";
import DeleteOutlineRoundedIcon from "@mui/icons-material/DeleteOutlineRounded";
import EditRoundedIcon from "@mui/icons-material/EditRounded";
import OpenInNewRoundedIcon from "@mui/icons-material/OpenInNewRounded";
import {
  Alert,
  Box,
  Button,
  IconButton,
  MenuItem,
  Skeleton,
  Stack,
  Tab,
  Tabs,
  TextField,
  Tooltip,
  Typography,
} from "@mui/material";
import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { useRef, useState } from "react";
import {
  Link as RouterLink,
  useNavigate,
  useParams,
  useSearchParams,
} from "react-router-dom";
import { z } from "zod";
import { api, leerError } from "../../api/cliente";
import type { Actividad, Expediente } from "../../api/tipos";
import { colores } from "../../app/tema";
import { useSesion } from "../../auth/Sesion";
import { Confirmar } from "../../components/Confirmar";
import { Encabezado } from "../../components/Encabezado";
import {
  EstadoExpedienteChip,
  EstadoSolicitudChip,
  ESTADOS_EXPEDIENTE,
  TIPOS_ACTIVIDAD,
  TIPOS_DOCUMENTO,
} from "../../components/Estados";
import { FormularioDialogo } from "../../components/Formulario";
import { useAvisar } from "../../components/Notificador";
import { Dato, Seccion } from "../../components/Seccion";
import { Tabla } from "../../components/Tabla";
import {
  aInputLocal,
  aLocal,
  capitalizar,
  desdeInputLocal,
  fecha,
  fechaHora,
  fechaLarga,
  hora,
  pesos,
} from "../../lib/formato";
import { formatearRut } from "../../lib/rut";
import { PagosExpediente } from "../finanzas/PagosExpediente";

const esquemaActividad = z
  .object({
    tipo: z.enum([
      "retiro",
      "velatorio",
      "misa",
      "traslado",
      "sepultacion",
      "otra",
    ]),
    lugar: z.string().trim().min(3, "Indique el lugar").max(200),
    inicio: z.string().min(1, "Indique el inicio"),
    fin: z.string(),
    notas: z.string().max(1000),
  })
  .refine((d) => !d.fin || d.fin > d.inicio, {
    path: ["fin"],
    message: "Debe ser posterior al inicio",
  });

const esquemaEstado = z.object({
  estado: z.enum(["abierto", "en_servicio", "cerrado", "anulado"]),
  observaciones: z.string().max(2000),
});

export default function DetalleExpediente() {
  const { id } = useParams();
  const [params, setParams] = useSearchParams();
  const pestana = params.get("pestana") ?? "resumen";
  const { puede } = useSesion();
  const qc = useQueryClient();
  const avisar = useAvisar();
  const navegar = useNavigate();
  const [actividad, setActividad] = useState<Actividad | "nueva" | null>(null);
  const [eliminarAct, setEliminarAct] = useState<Actividad | null>(null);
  const [editarEstado, setEditarEstado] = useState(false);
  const [tipoDoc, setTipoDoc] = useState("certificado_defuncion");
  const archivo = useRef<HTMLInputElement>(null);

  const {
    data: e,
    isLoading,
    error,
  } = useQuery({
    queryKey: ["expediente", id],
    queryFn: async () => (await api.get<Expediente>(`/expedientes/${id}`)).data,
  });

  const refrescar = () =>
    qc.invalidateQueries({ queryKey: ["expediente", id] });

  const subir = useMutation({
    mutationFn: async (f: File) => {
      const datos = new FormData();
      datos.append("tipo", tipoDoc);
      datos.append("archivo", f);
      return api.post(`/expedientes/${id}/documentos`, datos);
    },
    onSuccess: () => {
      refrescar();
      avisar("Documento adjuntado");
    },
    onError: (err) => avisar(leerError(err).mensaje, "error"),
  });

  const verDocumento = async (docId: number) => {
    try {
      const r = await api.get<{ url: string }>(`/documentos/${docId}/url`);
      window.open(r.data.url, "_blank", "noopener");
    } catch (err) {
      avisar(leerError(err).mensaje, "error");
    }
  };

  const borrarActividad = useMutation({
    mutationFn: async (a: Actividad) => api.delete(`/actividades/${a.id}`),
    onSuccess: () => {
      setEliminarAct(null);
      refrescar();
      avisar("Actividad eliminada");
    },
  });

  if (error) return <Alert severity="error">{leerError(error).mensaje}</Alert>;
  if (isLoading || !e) return <Skeleton height={400} />;

  const actividades = [...e.actividades].sort((a, b) =>
    a.inicio.localeCompare(b.inicio),
  );
  const vigente = e.solicitudes.find(
    (s) => !["cancelada", "no_aplica"].includes(s.estado),
  );

  return (
    <>
      <Encabezado
        volver={{ a: "/expedientes", texto: "Expedientes" }}
        titulo={e.difunto.nombre}
        descripcion={
          <>
            Expediente {e.codigo}, sucursal {e.sucursal.nombre}. Abierto el{" "}
            {fecha(e.created_at)}.
          </>
        }
        extra={
          <Stack
            direction="row"
            spacing={1}
            sx={{ mt: 1.5, alignItems: "center" }}
          >
            <EstadoExpedienteChip estado={e.estado} />
          </Stack>
        }
        acciones={
          <>
            {puede("expedientes.editar") && (
              <Button
                variant="outlined"
                startIcon={<EditRoundedIcon />}
                onClick={() => setEditarEstado(true)}
              >
                Estado
              </Button>
            )}
            {!vigente &&
              puede("solicitudes.crear") &&
              !["cerrado", "anulado"].includes(e.estado) && (
                <Button
                  variant="contained"
                  startIcon={<AddRoundedIcon />}
                  component={RouterLink}
                  to={`/solicitudes/nueva?expediente=${e.id}`}
                >
                  Registrar solicitud
                </Button>
              )}
          </>
        }
      />

      <Tabs
        value={pestana}
        onChange={(_, v) => setParams({ pestana: v }, { replace: true })}
        sx={{ mb: 3, borderBottom: 1, borderColor: "divider" }}
        variant="scrollable"
      >
        <Tab value="resumen" label="Resumen" />
        <Tab
          value="solicitudes"
          label={`Solicitudes (${e.solicitudes.length})`}
        />
        {puede("pagos.ver") && <Tab value="pagos" label="Pagos y cuotas" />}
        <Tab value="documentos" label={`Documentos (${e.documentos.length})`} />
      </Tabs>

      {pestana === "resumen" && (
        <Box
          sx={{
            display: "grid",
            gap: 3,
            gridTemplateColumns: { xs: "1fr", lg: "1fr 1fr" },
            alignItems: "start",
          }}
        >
          <Stack spacing={3}>
            <Seccion titulo="Familia">
              <Box
                sx={{
                  display: "grid",
                  gap: 2.5,
                  gridTemplateColumns: { xs: "1fr", sm: "1fr 1fr" },
                }}
              >
                <Dato etiqueta="Contratante">
                  <RouterLink
                    to={`/clientes/${e.cliente.id}`}
                    style={{ color: colores.vino }}
                  >
                    {e.cliente.nombre}
                  </RouterLink>
                </Dato>
                <Dato etiqueta="RUT del contratante">
                  {formatearRut(e.cliente.rut)}
                </Dato>
                <Dato etiqueta="Teléfono">{e.cliente.telefono}</Dato>
                <Dato etiqueta="Correo">{e.cliente.email}</Dato>
                <Dato etiqueta="Fallecido">{e.difunto.nombre}</Dato>
                <Dato etiqueta="RUT del fallecido">
                  {e.difunto.rut ? formatearRut(e.difunto.rut) : "No informado"}
                </Dato>
                <Dato etiqueta="Fecha de fallecimiento">
                  {fecha(e.difunto.fecha_fallecimiento)}
                </Dato>
                <Dato etiqueta="Convenio">
                  {e.convenio
                    ? `${e.convenio.nombre}${e.convenio.descuento_pct > 0 ? ` (${e.convenio.descuento_pct} %)` : ""}`
                    : "Sin convenio"}
                </Dato>
              </Box>
              {e.observaciones && (
                <Alert severity="info" sx={{ mt: 2.5 }}>
                  {e.observaciones}
                </Alert>
              )}
            </Seccion>
            {vigente && (
              <Seccion
                titulo="Servicio contratado"
                acciones={
                  <Button
                    size="small"
                    component={RouterLink}
                    to={`/solicitudes/${vigente.id}`}
                  >
                    Ver solicitud
                  </Button>
                }
              >
                <Stack spacing={1.5}>
                  <EstadoSolicitudChip estado={vigente.estado} />
                  <Typography variant="body2">
                    Velatorio desde el {fechaLarga(vigente.velatorio_inicio)} en{" "}
                    {vigente.lugar_velatorio}.
                  </Typography>
                  <Typography variant="body2">
                    Traslado el {fechaLarga(vigente.traslado_at)} hacia{" "}
                    {vigente.lugar_sepultura}
                    {vigente.sepultura_detalle
                      ? ` (${vigente.sepultura_detalle})`
                      : ""}
                    .
                  </Typography>
                  <Typography variant="body2" color="text.secondary">
                    Total {pesos(e.resumen.total)}, pagado{" "}
                    {pesos(e.resumen.pagado)}, saldo {pesos(e.resumen.saldo)}.
                  </Typography>
                </Stack>
              </Seccion>
            )}
          </Stack>

          <Seccion
            titulo="Actividades"
            descripcion="Retiro, velatorio, misa, traslado y sepultación en orden."
            acciones={
              puede("expedientes.editar") && (
                <Button
                  size="small"
                  startIcon={<AddRoundedIcon />}
                  onClick={() => setActividad("nueva")}
                >
                  Agregar
                </Button>
              )
            }
          >
            {actividades.length === 0 && (
              <Typography color="text.secondary">
                Sin actividades registradas.
              </Typography>
            )}
            <Box
              component="ol"
              sx={{
                listStyle: "none",
                p: 0,
                m: 0,
                position: "relative",
                "&::before": {
                  content: '""',
                  position: "absolute",
                  left: 7,
                  top: 8,
                  bottom: 8,
                  width: 2,
                  bgcolor: colores.linea,
                },
              }}
            >
              {actividades.map((a) => (
                <Box
                  component="li"
                  key={a.id}
                  sx={{
                    position: "relative",
                    pl: 4,
                    pb: 2.5,
                    "&:last-child": { pb: 0 },
                  }}
                >
                  <Box
                    sx={{
                      position: "absolute",
                      left: 0,
                      top: 4,
                      width: 16,
                      height: 16,
                      borderRadius: "50%",
                      bgcolor: aLocal(a.inicio)!.isBefore(aLocal(new Date())!)
                        ? colores.vino
                        : "#fff",
                      border: `2px solid ${colores.vino}`,
                    }}
                  />
                  <Stack
                    direction="row"
                    sx={{ justifyContent: "space-between", gap: 1 }}
                  >
                    <Box>
                      <Typography variant="body2" sx={{ fontWeight: 600 }}>
                        {TIPOS_ACTIVIDAD[a.tipo]}
                      </Typography>
                      <Typography variant="body2">
                        {capitalizar(fechaLarga(a.inicio))}
                        {a.fin ? ` a ${hora(a.fin)}` : ""}
                      </Typography>
                      <Typography variant="body2" color="text.secondary">
                        {a.lugar}
                      </Typography>
                      {a.notas && (
                        <Typography variant="caption" color="text.secondary">
                          {a.notas}
                        </Typography>
                      )}
                    </Box>
                    {puede("expedientes.editar") && (
                      <Stack direction="row">
                        <IconButton
                          size="small"
                          onClick={() => setActividad(a)}
                          aria-label="Editar actividad"
                        >
                          <EditRoundedIcon fontSize="small" />
                        </IconButton>
                        <IconButton
                          size="small"
                          onClick={() => setEliminarAct(a)}
                          aria-label="Eliminar actividad"
                        >
                          <DeleteOutlineRoundedIcon fontSize="small" />
                        </IconButton>
                      </Stack>
                    )}
                  </Stack>
                </Box>
              ))}
            </Box>
          </Seccion>
        </Box>
      )}

      {pestana === "solicitudes" && (
        <Seccion sinRelleno>
          <Tabla
            filas={e.solicitudes}
            clave={(s) => s.id}
            alClic={(s) => navegar(`/solicitudes/${s.id}`)}
            vacio="Aún no hay solicitudes para este expediente."
            columnas={[
              {
                id: "reg",
                titulo: "Registrada",
                render: (s) => fechaHora(s.created_at),
              },
              {
                id: "tras",
                titulo: "Traslado",
                render: (s) => fechaHora(s.traslado_at),
              },
              {
                id: "lug",
                titulo: "Sepultura",
                render: (s) => s.lugar_sepultura,
              },
              {
                id: "veh",
                titulo: "Vehículo",
                render: (s) => s.asignacion?.vehiculo?.nombre ?? "—",
              },
              {
                id: "est",
                titulo: "Estado",
                render: (s) => <EstadoSolicitudChip estado={s.estado} />,
              },
            ]}
          />
        </Seccion>
      )}

      {pestana === "pagos" && (
        <PagosExpediente expedienteId={e.id} codigo={e.codigo} />
      )}

      {pestana === "documentos" && (
        <Seccion
          titulo="Documentos"
          descripcion="Se guardan en un almacenamiento privado; cada visualización usa un enlace temporal de 5 minutos y queda en la bitácora."
          sinRelleno
        >
          {puede("expedientes.editar") && (
            <Stack
              direction={{ xs: "column", sm: "row" }}
              spacing={1.5}
              sx={{ px: 2.5, pb: 2, alignItems: { sm: "center" } }}
            >
              <TextField
                select
                label="Tipo de documento"
                value={tipoDoc}
                onChange={(ev) => setTipoDoc(ev.target.value)}
                sx={{ maxWidth: 280 }}
              >
                {Object.entries(TIPOS_DOCUMENTO).map(([k, v]) => (
                  <MenuItem key={k} value={k}>
                    {v}
                  </MenuItem>
                ))}
              </TextField>
              <input
                ref={archivo}
                type="file"
                hidden
                accept="application/pdf,image/jpeg,image/png,image/webp"
                onChange={(ev) => {
                  const f = ev.target.files?.[0];
                  if (f) subir.mutate(f);
                  ev.target.value = "";
                }}
              />
              <Button
                variant="contained"
                startIcon={<AttachFileRoundedIcon />}
                disabled={subir.isPending}
                onClick={() => archivo.current?.click()}
              >
                {subir.isPending ? "Subiendo…" : "Adjuntar archivo"}
              </Button>
              <Typography variant="caption" color="text.secondary">
                PDF, JPG, PNG o WEBP de hasta 8 MB.
              </Typography>
            </Stack>
          )}
          <Tabla
            filas={e.documentos}
            clave={(d) => d.id}
            vacio="No hay documentos adjuntos."
            columnas={[
              {
                id: "tipo",
                titulo: "Tipo",
                render: (d) => TIPOS_DOCUMENTO[d.tipo],
              },
              {
                id: "nombre",
                titulo: "Archivo",
                render: (d) => d.nombre_original,
              },
              {
                id: "tam",
                titulo: "Tamaño",
                ocultarEnMovil: true,
                render: (d) => `${Math.round(d.tamano / 1024)} KB`,
              },
              {
                id: "fecha",
                titulo: "Subido",
                ocultarEnMovil: true,
                render: (d) => fechaHora(d.created_at),
              },
              {
                id: "acc",
                titulo: "",
                alinear: "right",
                render: (d) => (
                  <Tooltip title="Ver en una pestaña nueva">
                    <IconButton
                      size="small"
                      onClick={() => verDocumento(d.id)}
                      aria-label="Ver documento"
                    >
                      <OpenInNewRoundedIcon fontSize="small" />
                    </IconButton>
                  </Tooltip>
                ),
              },
            ]}
          />
        </Seccion>
      )}

      <FormularioDialogo
        abierto={actividad !== null}
        titulo={actividad === "nueva" ? "Nueva actividad" : "Editar actividad"}
        esquema={esquemaActividad}
        campos={[
          {
            nombre: "tipo",
            etiqueta: "Tipo",
            tipo: "seleccion",
            ancho: "medio",
            opciones: Object.entries(TIPOS_ACTIVIDAD).map(
              ([valor, etiqueta]) => ({ valor, etiqueta }),
            ),
          },
          { nombre: "lugar", etiqueta: "Lugar", ancho: "medio" },
          {
            nombre: "inicio",
            etiqueta: "Inicio",
            tipo: "fechaHora",
            ancho: "medio",
          },
          {
            nombre: "fin",
            etiqueta: "Término (opcional)",
            tipo: "fechaHora",
            ancho: "medio",
          },
          { nombre: "notas", etiqueta: "Notas", tipo: "area" },
        ]}
        valoresIniciales={
          actividad && actividad !== "nueva"
            ? {
                tipo: actividad.tipo,
                lugar: actividad.lugar,
                inicio: aInputLocal(actividad.inicio),
                fin: aInputLocal(actividad.fin),
                notas: actividad.notas ?? "",
              }
            : { tipo: "retiro", lugar: "", inicio: "", fin: "", notas: "" }
        }
        alCerrar={() => setActividad(null)}
        alGuardar={async (d) => {
          const cuerpo = {
            ...d,
            inicio: desdeInputLocal(d.inicio),
            fin: d.fin ? desdeInputLocal(d.fin) : null,
            notas: d.notas || null,
          };
          if (actividad === "nueva")
            await api.post(`/expedientes/${id}/actividades`, cuerpo);
          else if (actividad)
            await api.put(`/actividades/${actividad.id}`, cuerpo);
          await refrescar();
          await qc.invalidateQueries({ queryKey: ["calendario"] });
          avisar("Actividad guardada");
          setActividad(null);
        }}
      />
      <FormularioDialogo
        abierto={editarEstado}
        titulo="Estado del expediente"
        esquema={esquemaEstado}
        campos={[
          {
            nombre: "estado",
            etiqueta: "Estado",
            tipo: "seleccion",
            opciones: Object.entries(ESTADOS_EXPEDIENTE).map(([valor, v]) => ({
              valor,
              etiqueta: v.etiqueta,
            })),
          },
          { nombre: "observaciones", etiqueta: "Observaciones", tipo: "area" },
        ]}
        valoresIniciales={{
          estado: e.estado,
          observaciones: e.observaciones ?? "",
        }}
        alCerrar={() => setEditarEstado(false)}
        alGuardar={async (d) => {
          await api.put(`/expedientes/${id}`, {
            ...d,
            observaciones: d.observaciones || null,
          });
          await refrescar();
          await qc.invalidateQueries({ queryKey: ["expedientes"] });
          avisar("Expediente actualizado");
          setEditarEstado(false);
        }}
      />
      <Confirmar
        abierto={!!eliminarAct}
        titulo="Eliminar actividad"
        mensaje="La actividad dejará de aparecer en el expediente y el calendario."
        textoConfirmar="Eliminar"
        peligro
        alCerrar={() => setEliminarAct(null)}
        alConfirmar={() => eliminarAct && borrarActividad.mutate(eliminarAct)}
      />
    </>
  );
}
