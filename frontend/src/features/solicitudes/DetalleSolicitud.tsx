import BlockRoundedIcon from "@mui/icons-material/BlockRounded";
import CheckCircleRoundedIcon from "@mui/icons-material/CheckCircleRounded";
import DoneAllRoundedIcon from "@mui/icons-material/DoneAllRounded";
import EditRoundedIcon from "@mui/icons-material/EditRounded";
import HighlightOffRoundedIcon from "@mui/icons-material/HighlightOffRounded";
import PictureAsPdfRoundedIcon from "@mui/icons-material/PictureAsPdfRounded";
import RateReviewRoundedIcon from "@mui/icons-material/RateReviewRounded";
import {
  Alert,
  Box,
  Button,
  Chip,
  LinearProgress,
  Skeleton,
  Stack,
  Table,
  TableBody,
  TableCell,
  TableHead,
  TableRow,
  Typography,
} from "@mui/material";
import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { useState } from "react";
import { Link as RouterLink, useParams } from "react-router-dom";
import { api, leerError, nuevaClave } from "../../api/cliente";
import type { Solicitud } from "../../api/tipos";
import { colores } from "../../app/tema";
import { useSesion } from "../../auth/Sesion";
import { Confirmar } from "../../components/Confirmar";
import { Encabezado } from "../../components/Encabezado";
import { EstadoSolicitudChip, TIPOS_PRODUCTO } from "../../components/Estados";
import { useAvisar } from "../../components/Notificador";
import { Dato, Seccion } from "../../components/Seccion";
import { VisorPdf } from "../../components/VisorPdf";
import {
  ahoraLocal,
  aLocal,
  fechaHora,
  fechaLarga,
  capitalizar,
  hora,
  pesos,
} from "../../lib/formato";
import { formatearRut } from "../../lib/rut";

type Accion = "aceptar" | "observar" | "no-aplica" | "cancelar" | "ejecutar";

const ACCIONES: Record<
  Accion,
  {
    titulo: string;
    texto: string;
    mensaje: string;
    motivo?: string;
    peligro?: boolean;
  }
> = {
  aceptar: {
    titulo: "Aceptar solicitud",
    texto: "Aceptar",
    mensaje:
      "Se descontará el stock de los productos y quedarán confirmados el vehículo y el equipo. Si otro servicio los ocupa en ese horario, el sistema lo impedirá.",
  },
  observar: {
    titulo: "Enviar con observaciones",
    texto: "Enviar observaciones",
    motivo: "Qué debe corregir el coordinador",
    mensaje: "La solicitud vuelve al coordinador para que la corrija.",
  },
  "no-aplica": {
    titulo: "Marcar como no aplica",
    texto: "Marcar no aplica",
    motivo: "Motivo",
    peligro: true,
    mensaje: "La solicitud queda cerrada y no compromete recursos.",
  },
  cancelar: {
    titulo: "Cancelar solicitud",
    texto: "Cancelar solicitud",
    motivo: "Motivo de la cancelación",
    peligro: true,
    mensaje:
      "Si estaba aceptada, el stock se repone y el vehículo y el equipo quedan libres.",
  },
  ejecutar: {
    titulo: "Marcar como ejecutada",
    texto: "Confirmar",
    mensaje:
      "Confirma que el servicio se realizó. La solicitud ya no podrá modificarse.",
  },
};

export default function DetalleSolicitud() {
  const { id } = useParams();
  const { puede } = useSesion();
  const qc = useQueryClient();
  const avisar = useAvisar();
  const [accion, setAccion] = useState<Accion | null>(null);
  const [verOrden, setVerOrden] = useState(false);

  const {
    data: s,
    isLoading,
    error,
  } = useQuery({
    queryKey: ["solicitud", id],
    queryFn: async () => (await api.get<Solicitud>(`/solicitudes/${id}`)).data,
  });

  const ejecutar = useMutation({
    mutationFn: async ({ a, motivo }: { a: Accion; motivo: string }) => {
      const cuerpo =
        a === "cancelar"
          ? { motivo }
          : a === "observar" || a === "no-aplica"
            ? { comentario: motivo }
            : {};
      const cabeceras =
        a === "aceptar" ? { "Idempotency-Key": nuevaClave() } : undefined;
      return api.post(`/solicitudes/${id}/${a}`, cuerpo, {
        headers: cabeceras,
      });
    },
    onSuccess: async (_, { a }) => {
      setAccion(null);
      await Promise.all(
        [
          "solicitud",
          "solicitudes",
          "panel",
          "expediente",
          "productos",
          "calendario",
        ].map((k) => qc.invalidateQueries({ queryKey: [k] })),
      );
      avisar(
        {
          aceptar: "Solicitud aceptada",
          observar: "Observaciones enviadas",
          "no-aplica": "Solicitud marcada como no aplica",
          cancelar: "Solicitud cancelada",
          ejecutar: "Servicio marcado como ejecutado",
        }[a],
      );
    },
    onError: (e) => {
      setAccion(null);
      avisar(leerError(e).mensaje, "error");
    },
  });

  if (error) return <Alert severity="error">{leerError(error).mensaje}</Alert>;
  if (isLoading || !s) return <Skeleton height={400} />;

  const exp = s.expediente!;
  const r = s.resumen!;
  const evaluar = puede("solicitudes.evaluar");
  const editable =
    ["en_revision", "con_observaciones", "aceptada"].includes(s.estado) &&
    puede("solicitudes.editar");
  const trasladoPasado = aLocal(s.traslado_at)!.isBefore(ahoraLocal());
  const subtotalProductos = s.productos.reduce(
    (a, p) =>
      a + (p.pivot?.cantidad ?? 1) * (p.pivot?.precio_unitario ?? p.precio),
    0,
  );
  const pctPagado = r.total
    ? Math.min(100, Math.round((r.pagado / r.total) * 100))
    : 0;
  const pctMinimo = r.total
    ? Math.round(((r.abono_minimo ?? 0) / r.total) * 100)
    : 0;

  return (
    <>
      <Encabezado
        volver={{ a: "/solicitudes", texto: "Solicitudes" }}
        titulo={exp.difunto.nombre}
        descripcion={
          <>
            Servicio {exp.codigo}, contratado por {exp.cliente.nombre}.
            Registrada el {fechaHora(s.created_at)}.
          </>
        }
        extra={
          <Box sx={{ mt: 1.5 }}>
            <EstadoSolicitudChip estado={s.estado} />
          </Box>
        }
        acciones={
          <>
            <Button
              variant="outlined"
              startIcon={<PictureAsPdfRoundedIcon />}
              onClick={() => setVerOrden(true)}
            >
              Orden de servicio
            </Button>
            {editable && (
              <Button
                variant="outlined"
                startIcon={<EditRoundedIcon />}
                component={RouterLink}
                to={`/solicitudes/${s.id}/editar`}
              >
                Editar
              </Button>
            )}
          </>
        }
      />

      {s.estado === "con_observaciones" && s.comentario && (
        <Alert
          severity="warning"
          icon={<RateReviewRoundedIcon />}
          sx={{ mb: 3 }}
        >
          <strong>Observaciones de la administración:</strong> {s.comentario}
          {editable && (
            <>
              {" "}
              Edite la solicitud para corregirla; volverá a revisión al guardar.
            </>
          )}
        </Alert>
      )}
      {s.estado === "no_aplica" && s.comentario && (
        <Alert severity="error" sx={{ mb: 3 }}>
          No aplica: {s.comentario}
        </Alert>
      )}
      {s.estado === "cancelada" && (
        <Alert severity="info" sx={{ mb: 3 }}>
          Cancelada. {s.motivo_cancelacion}
        </Alert>
      )}

      {/* Barra de decisión para el administrador */}
      {s.estado === "en_revision" && evaluar && (
        <Box
          sx={{
            mb: 3,
            p: 2.5,
            borderRadius: 2.5,
            border: `1px solid ${colores.rosaCeniza}`,
            bgcolor: colores.vinoTenue,
            display: "flex",
            gap: 2,
            flexWrap: "wrap",
            alignItems: "center",
          }}
        >
          <Box sx={{ flex: 1, minWidth: 260 }}>
            <Typography variant="h3" component="h2">
              Evaluación
            </Typography>
            <Typography variant="body2" color="text.secondary">
              {r.abono_cumplido
                ? `El abono mínimo está cubierto (${pesos(r.pagado)} de ${pesos(r.abono_minimo)} requeridos).`
                : `Falta abono: se requieren ${pesos(r.abono_minimo)} y hay ${pesos(r.pagado)} pagados. Registre el pago antes de aceptar.`}
            </Typography>
          </Box>
          <Stack direction="row" spacing={1} sx={{ flexWrap: "wrap", gap: 1 }}>
            <Button
              color="inherit"
              startIcon={<HighlightOffRoundedIcon />}
              onClick={() => setAccion("no-aplica")}
            >
              No aplica
            </Button>
            <Button
              variant="outlined"
              startIcon={<RateReviewRoundedIcon />}
              onClick={() => setAccion("observar")}
            >
              Con observaciones
            </Button>
            <Button
              variant="contained"
              startIcon={<CheckCircleRoundedIcon />}
              disabled={!r.abono_cumplido}
              onClick={() => setAccion("aceptar")}
            >
              Aceptar
            </Button>
          </Stack>
        </Box>
      )}

      <Box
        sx={{
          display: "grid",
          gap: 3,
          gridTemplateColumns: { xs: "1fr", lg: "2fr 1fr" },
          alignItems: "start",
        }}
      >
        <Stack spacing={3}>
          <Seccion titulo="Servicio">
            <Box
              sx={{
                display: "grid",
                gap: 2.5,
                gridTemplateColumns: { xs: "1fr", sm: "1fr 1fr" },
              }}
            >
              <Dato etiqueta="Inicio del velatorio">
                {capitalizar(fechaLarga(s.velatorio_inicio))}
              </Dato>
              <Dato etiqueta="Lugar del velatorio">{s.lugar_velatorio}</Dato>
              <Dato etiqueta="Traslado">
                {capitalizar(fechaLarga(s.traslado_at))}
              </Dato>
              <Dato etiqueta="Sucursal">{s.sucursal?.nombre}</Dato>
              <Dato etiqueta="Lugar de sepultura">{s.lugar_sepultura}</Dato>
              <Dato etiqueta="Ubicación en el cementerio">
                {s.sepultura_detalle || "Sin detalle"}
              </Dato>
            </Box>
            {s.mensaje && (
              <Box
                sx={{
                  mt: 2.5,
                  p: 2,
                  borderRadius: 2,
                  bgcolor: "background.default",
                }}
              >
                <Typography variant="body2" color="text.secondary">
                  Mensaje para la cinta o tarjeta
                </Typography>
                <Typography
                  sx={{
                    fontFamily: '"Cormorant Garamond", serif',
                    fontSize: "1.25rem",
                    mt: 0.5,
                  }}
                >
                  «{s.mensaje}»
                </Typography>
              </Box>
            )}
          </Seccion>

          <Seccion titulo="Urna y elementos" sinRelleno>
            <Table size="small">
              <TableHead>
                <TableRow>
                  <TableCell>Producto</TableCell>
                  <TableCell align="right">Cantidad</TableCell>
                  <TableCell align="right">Precio</TableCell>
                  <TableCell align="right">Subtotal</TableCell>
                </TableRow>
              </TableHead>
              <TableBody>
                {s.productos.map((p) => (
                  <TableRow key={p.id}>
                    <TableCell>
                      <Typography variant="body2" sx={{ fontWeight: 600 }}>
                        {p.nombre}
                      </Typography>
                      <Typography variant="caption" color="text.secondary">
                        {p.codigo}, {TIPOS_PRODUCTO[p.tipo].toLowerCase()}
                        {s.estado === "en_revision"
                          ? `, stock actual ${p.stock}`
                          : ""}
                      </Typography>
                    </TableCell>
                    <TableCell align="right">{p.pivot?.cantidad}</TableCell>
                    <TableCell align="right">
                      {pesos(p.pivot?.precio_unitario)}
                    </TableCell>
                    <TableCell align="right">
                      {pesos(
                        (p.pivot?.cantidad ?? 1) *
                          (p.pivot?.precio_unitario ?? 0),
                      )}
                    </TableCell>
                  </TableRow>
                ))}
                <TableRow>
                  <TableCell colSpan={3}>Valor del servicio</TableCell>
                  <TableCell align="right">{pesos(s.valor_servicio)}</TableCell>
                </TableRow>
                {s.descuento > 0 && (
                  <TableRow>
                    <TableCell colSpan={3}>Descuento por convenio</TableCell>
                    <TableCell align="right">−{pesos(s.descuento)}</TableCell>
                  </TableRow>
                )}
                <TableRow sx={{ "& td": { fontWeight: 700, borderBottom: 0 } }}>
                  <TableCell colSpan={3}>Total</TableCell>
                  <TableCell align="right">
                    {pesos(s.valor_servicio - s.descuento + subtotalProductos)}
                  </TableCell>
                </TableRow>
              </TableBody>
            </Table>
          </Seccion>

          <Seccion titulo="Vehículo y equipo">
            {s.asignacion ? (
              <Box
                sx={{
                  display: "grid",
                  gap: 2.5,
                  gridTemplateColumns: { xs: "1fr", sm: "1fr 1fr" },
                }}
              >
                <Dato etiqueta="Vehículo">
                  {s.asignacion.vehiculo?.nombre}
                  {s.asignacion.vehiculo?.patente
                    ? `, patente ${s.asignacion.vehiculo.patente}`
                    : ""}
                  {s.asignacion.vehiculo?.origen === "externo" && (
                    <Chip
                      size="small"
                      label={`Arrendado a ${s.asignacion.vehiculo.proveedor_externo}`}
                      sx={{ ml: 1 }}
                    />
                  )}
                </Dato>
                <Dato etiqueta="Compromiso">
                  Desde las {hora(s.asignacion.inicio)} (presentación) hasta las{" "}
                  {hora(s.asignacion.fin)} aprox.
                  {!s.asignacion.activa && (
                    <Typography
                      variant="caption"
                      color="text.secondary"
                      component="div"
                    >
                      Propuesto; se confirma al aceptar.
                    </Typography>
                  )}
                </Dato>
                <Dato etiqueta="Equipo">
                  <Stack
                    direction="row"
                    sx={{ flexWrap: "wrap", gap: 1, mt: 0.5 }}
                  >
                    {s.asignacion.equipo?.map((m) => (
                      <Chip
                        key={m.id}
                        size="small"
                        label={`${m.nombre}${m.pivot?.funcion === "conductor" ? ", conductor" : ""}`}
                      />
                    ))}
                  </Stack>
                </Dato>
              </Box>
            ) : (
              <Typography color="text.secondary">
                Sin vehículo asignado.
              </Typography>
            )}
          </Seccion>
        </Stack>

        <Stack spacing={3}>
          <Seccion
            titulo="Pagos"
            acciones={
              <Button
                size="small"
                component={RouterLink}
                to={`/expedientes/${exp.id}?pestana=pagos`}
              >
                Registrar pago
              </Button>
            }
          >
            <Typography
              sx={{ fontSize: "1.9rem", fontWeight: 600, lineHeight: 1.2 }}
            >
              {pesos(r.saldo)}
            </Typography>
            <Typography variant="body2" color="text.secondary">
              saldo pendiente
            </Typography>
            <Box sx={{ position: "relative", mt: 2, mb: 1 }}>
              <LinearProgress
                variant="determinate"
                value={pctPagado}
                color={r.abono_cumplido ? "success" : "warning"}
                sx={{ height: 8, borderRadius: 4, bgcolor: "#EEEAEC" }}
              />
              <Box
                title="Abono mínimo"
                sx={{
                  position: "absolute",
                  top: -3,
                  bottom: -3,
                  left: `${pctMinimo}%`,
                  width: 2,
                  bgcolor: colores.tinta,
                }}
              />
            </Box>
            <Stack spacing={0.75} sx={{ mt: 1.5 }}>
              <Fila etiqueta="Total del servicio" valor={pesos(r.total)} />
              <Fila
                etiqueta="Pagado"
                valor={`${pesos(r.pagado)} (${pctPagado} %)`}
              />
              <Fila
                etiqueta="Abono mínimo para aceptar"
                valor={pesos(r.abono_minimo)}
              />
            </Stack>
          </Seccion>

          <Seccion titulo="Contratante y fallecido">
            <Stack spacing={2}>
              <Dato etiqueta="Contratante">
                <RouterLink
                  to={`/clientes/${exp.cliente.id}`}
                  style={{ color: "inherit" }}
                >
                  {exp.cliente.nombre}
                </RouterLink>
                <Typography variant="body2" color="text.secondary">
                  {formatearRut(exp.cliente.rut)}
                  {exp.cliente.telefono ? `, ${exp.cliente.telefono}` : ""}
                </Typography>
              </Dato>
              <Dato etiqueta="Fallecido">
                {exp.difunto.nombre}
                <Typography variant="body2" color="text.secondary">
                  {exp.difunto.rut
                    ? formatearRut(exp.difunto.rut)
                    : "RUT no informado"}
                </Typography>
              </Dato>
              <Button
                variant="text"
                component={RouterLink}
                to={`/expedientes/${exp.id}`}
                sx={{ alignSelf: "flex-start", ml: -1 }}
              >
                Abrir expediente {exp.codigo}
              </Button>
            </Stack>
          </Seccion>

          {(s.estado === "aceptada" ||
            s.estado === "con_observaciones" ||
            (s.estado === "en_revision" && !evaluar)) && (
            <Seccion titulo="Acciones">
              <Stack spacing={1}>
                {s.estado === "aceptada" && puede("solicitudes.editar") && (
                  <Button
                    variant="contained"
                    startIcon={<DoneAllRoundedIcon />}
                    disabled={!trasladoPasado}
                    onClick={() => setAccion("ejecutar")}
                  >
                    Marcar como ejecutada
                  </Button>
                )}
                {s.estado === "aceptada" && !trasladoPasado && (
                  <Typography variant="caption" color="text.secondary">
                    Podrá marcarla como ejecutada después del traslado.
                  </Typography>
                )}
                {puede("solicitudes.editar") && (
                  <Button
                    color="error"
                    startIcon={<BlockRoundedIcon />}
                    onClick={() => setAccion("cancelar")}
                  >
                    Cancelar solicitud
                  </Button>
                )}
              </Stack>
            </Seccion>
          )}
          {s.evaluador && (
            <Typography variant="caption" color="text.secondary">
              Evaluada por {s.evaluador.nombre} el {fechaHora(s.evaluada_at)}.
            </Typography>
          )}
        </Stack>
      </Box>

      {accion && (
        <Confirmar
          abierto
          titulo={ACCIONES[accion].titulo}
          mensaje={ACCIONES[accion].mensaje}
          textoConfirmar={ACCIONES[accion].texto}
          peligro={ACCIONES[accion].peligro}
          pedirMotivo={
            ACCIONES[accion].motivo
              ? { etiqueta: ACCIONES[accion].motivo! }
              : undefined
          }
          cargando={ejecutar.isPending}
          alCerrar={() => setAccion(null)}
          alConfirmar={(motivo) => ejecutar.mutate({ a: accion, motivo })}
        />
      )}
      <VisorPdf
        abierto={verOrden}
        titulo={`Orden de servicio ${exp.codigo}`}
        ruta={`/solicitudes/${s.id}/orden.pdf`}
        nombreArchivo={`orden-servicio-${exp.codigo}.pdf`}
        alCerrar={() => setVerOrden(false)}
      />
    </>
  );
}

function Fila({ etiqueta, valor }: { etiqueta: string; valor: string }) {
  return (
    <Stack direction="row" sx={{ justifyContent: "space-between" }}>
      <Typography variant="body2" color="text.secondary">
        {etiqueta}
      </Typography>
      <Typography variant="body2" sx={{ fontWeight: 600 }}>
        {valor}
      </Typography>
    </Stack>
  );
}
