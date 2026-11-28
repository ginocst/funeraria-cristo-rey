import AddRoundedIcon from "@mui/icons-material/AddRounded";
import DeleteOutlineRoundedIcon from "@mui/icons-material/DeleteOutlineRounded";
import EditRoundedIcon from "@mui/icons-material/EditRounded";
import SwapHorizRoundedIcon from "@mui/icons-material/SwapHorizRounded";
import {
  Alert,
  Box,
  Button,
  Chip,
  Divider,
  IconButton,
  Link,
  Skeleton,
  Stack,
  Tooltip,
  Typography,
} from "@mui/material";
import { useQuery, useQueryClient } from "@tanstack/react-query";
import { useState } from "react";
import { Link as RouterLink } from "react-router-dom";
import { z } from "zod";
import { api, leerError } from "../../api/cliente";
import type { SalaConAgenda } from "../../api/tipos";
import { useSesion } from "../../auth/Sesion";
import { P } from "../../auth/permisos";
import { Confirmar } from "../../components/Confirmar";
import { Encabezado } from "../../components/Encabezado";
import { EstadoSolicitudChip } from "../../components/Estados";
import { FormularioDialogo } from "../../components/Formulario";
import { useAvisar } from "../../components/Notificador";
import { Seccion } from "../../components/Seccion";
import { aLocal, ahoraLocal, fechaHora } from "../../lib/formato";
import { enlaceRuta } from "../../lib/mapas";
import { ESTADOS_SALA } from "./estados";

const nota = z
  .string()
  .trim()
  .max(250)
  .transform((v) => v || null);

const esquemaSala = z.object({
  nombre: z.string().trim().min(3, "Indique el nombre").max(80),
  direccion: z.string().trim().min(5, "Indique la dirección").max(110),
  capacidad: z
    .union([z.literal(""), z.number().int().min(1).max(1000)])
    .transform((v) => (v === "" ? null : v)),
  observaciones: nota,
});

const esquemaEstado = z.object({
  estado: z.enum(["disponible", "mantenimiento", "fuera_de_servicio"]),
  observaciones: nota,
});

/** Mantenedor de las salas velatorias de la funeraria: estado, ocupación y agenda */
export default function Salas() {
  const { puede } = useSesion();
  const qc = useQueryClient();
  const avisar = useAvisar();
  const gestionar = puede(P.salasGestionar);
  const [editando, setEditando] = useState<SalaConAgenda | "nueva" | null>(
    null,
  );
  const [estado, setEstado] = useState<SalaConAgenda | null>(null);
  const [eliminar, setEliminar] = useState<SalaConAgenda | null>(null);
  const { data, isLoading, error } = useQuery({
    queryKey: ["salas-velatorias", "agenda"],
    queryFn: async () =>
      (await api.get<SalaConAgenda[]>("/salas-velatorias/agenda")).data,
    refetchInterval: 60_000,
  });
  const refrescar = () =>
    qc.invalidateQueries({ queryKey: ["salas-velatorias"] });

  return (
    <>
      <Encabezado
        titulo="Salas velatorias"
        descripcion="Salas que administra la funeraria. Una sala no admite dos servicios a la vez; cualquier otro lugar se escribe a mano en la solicitud."
        acciones={
          gestionar && (
            <Button
              variant="contained"
              startIcon={<AddRoundedIcon />}
              onClick={() => setEditando("nueva")}
            >
              Nueva sala
            </Button>
          )
        }
      />
      {error && <Alert severity="error">{leerError(error).mensaje}</Alert>}
      {isLoading && <Skeleton height={300} />}
      {data?.length === 0 && (
        <Typography color="text.secondary">
          No hay salas registradas.
        </Typography>
      )}
      <Box
        sx={{
          display: "grid",
          gap: 3,
          gridTemplateColumns: { xs: "1fr", md: "1fr 1fr" },
          alignItems: "start",
        }}
      >
        {data?.map((s) => {
          const ocupada = s.reservas.find((r) =>
            aLocal(r.velatorio_inicio)!.isBefore(ahoraLocal()),
          );
          return (
            <Seccion
              key={s.id}
              titulo={s.nombre}
              descripcion={
                <>
                  <Link
                    href={enlaceRuta(s.direccion)}
                    target="_blank"
                    rel="noopener noreferrer"
                  >
                    {s.direccion}
                  </Link>
                  {s.capacidad ? ` · Capacidad: ${s.capacidad} personas` : ""}
                </>
              }
              acciones={
                gestionar && (
                  <>
                    <Tooltip title="Editar">
                      <IconButton
                        size="small"
                        onClick={() => setEditando(s)}
                        aria-label={`Editar ${s.nombre}`}
                      >
                        <EditRoundedIcon fontSize="small" />
                      </IconButton>
                    </Tooltip>
                    <Tooltip title="Eliminar">
                      <IconButton
                        size="small"
                        onClick={() => setEliminar(s)}
                        aria-label={`Eliminar ${s.nombre}`}
                      >
                        <DeleteOutlineRoundedIcon fontSize="small" />
                      </IconButton>
                    </Tooltip>
                  </>
                )
              }
            >
              <Stack spacing={2}>
                <Stack
                  direction="row"
                  sx={{ alignItems: "center", flexWrap: "wrap", gap: 1 }}
                >
                  <Chip
                    size="small"
                    color={ESTADOS_SALA[s.estado].color}
                    label={ESTADOS_SALA[s.estado].etiqueta}
                  />
                  {s.estado === "disponible" && (
                    <Chip
                      size="small"
                      variant="outlined"
                      label={
                        ocupada
                          ? `Ocupada ahora, hasta el ${fechaHora(ocupada.traslado_at)}`
                          : "Libre ahora"
                      }
                    />
                  )}
                  <Box sx={{ flex: 1 }} />
                  {gestionar && (
                    <Button
                      size="small"
                      variant="outlined"
                      startIcon={<SwapHorizRoundedIcon />}
                      onClick={() => setEstado(s)}
                    >
                      Cambiar estado
                    </Button>
                  )}
                </Stack>
                {s.observaciones && (
                  <Typography variant="body2" color="text.secondary">
                    {s.observaciones}
                  </Typography>
                )}
                <Box>
                  <Typography variant="subtitle2" sx={{ mb: 1 }}>
                    Próximos servicios
                  </Typography>
                  {s.reservas.length === 0 && (
                    <Typography variant="body2" color="text.secondary">
                      No hay servicios programados.
                    </Typography>
                  )}
                  <Stack divider={<Divider />} spacing={1}>
                    {s.reservas.map((r) => (
                      <Stack
                        key={r.solicitud_id}
                        direction="row"
                        sx={{
                          justifyContent: "space-between",
                          alignItems: "center",
                          gap: 1,
                        }}
                      >
                        <Box sx={{ minWidth: 0 }}>
                          <Link
                            component={RouterLink}
                            to={`/solicitudes/${r.solicitud_id}`}
                            underline="hover"
                            variant="body2"
                            sx={{ fontWeight: 600 }}
                          >
                            {r.difunto}
                          </Link>
                          <Typography
                            variant="caption"
                            color="text.secondary"
                            component="div"
                          >
                            {r.codigo} · {fechaHora(r.velatorio_inicio)} al{" "}
                            {fechaHora(r.traslado_at)}
                          </Typography>
                        </Box>
                        <EstadoSolicitudChip estado={r.estado} />
                      </Stack>
                    ))}
                  </Stack>
                </Box>
              </Stack>
            </Seccion>
          );
        })}
      </Box>

      <FormularioDialogo
        abierto={editando !== null}
        titulo={editando === "nueva" ? "Nueva sala" : "Editar sala"}
        esquema={esquemaSala}
        campos={[
          { nombre: "nombre", etiqueta: "Nombre" },
          { nombre: "direccion", etiqueta: "Dirección" },
          {
            nombre: "capacidad",
            etiqueta: "Capacidad en personas (opcional)",
            tipo: "numero",
          },
          {
            nombre: "observaciones",
            etiqueta: "Observaciones (opcional)",
            tipo: "area",
          },
        ]}
        valoresIniciales={
          editando && editando !== "nueva"
            ? {
                nombre: editando.nombre,
                direccion: editando.direccion,
                capacidad: editando.capacidad ?? "",
                observaciones: editando.observaciones ?? "",
              }
            : { nombre: "", direccion: "", capacidad: "", observaciones: "" }
        }
        alCerrar={() => setEditando(null)}
        alGuardar={async (d) => {
          if (editando === "nueva") await api.post("/salas-velatorias", d);
          else if (editando)
            await api.put(`/salas-velatorias/${editando.id}`, d);
          await refrescar();
          avisar("Sala guardada");
          setEditando(null);
        }}
      />
      <FormularioDialogo
        abierto={!!estado}
        titulo="Cambiar estado de la sala"
        descripcion={`${estado?.nombre ?? ""}. Solo una sala disponible admite servicios.`}
        esquema={esquemaEstado}
        campos={[
          {
            nombre: "estado",
            etiqueta: "Estado",
            tipo: "seleccion",
            opciones: Object.entries(ESTADOS_SALA).map(([valor, e]) => ({
              valor,
              etiqueta: e.etiqueta,
            })),
          },
          {
            nombre: "observaciones",
            etiqueta: "Motivo u observaciones (opcional)",
            tipo: "area",
          },
        ]}
        valoresIniciales={{
          estado: estado?.estado,
          observaciones: estado?.observaciones ?? "",
        }}
        alCerrar={() => setEstado(null)}
        alGuardar={async (d) => {
          await api.post(`/salas-velatorias/${estado!.id}/estado`, d);
          await refrescar();
          avisar("Estado actualizado");
          setEstado(null);
        }}
      />
      <Confirmar
        abierto={!!eliminar}
        titulo="Eliminar sala"
        mensaje="La sala dejará de ofrecerse. Los servicios anteriores conservan su lugar."
        textoConfirmar="Eliminar"
        peligro
        alCerrar={() => setEliminar(null)}
        alConfirmar={async () => {
          try {
            await api.delete(`/salas-velatorias/${eliminar!.id}`);
            await refrescar();
            avisar("Sala eliminada");
          } catch (e) {
            avisar(leerError(e).mensaje, "error");
          }
          setEliminar(null);
        }}
      />
    </>
  );
}
