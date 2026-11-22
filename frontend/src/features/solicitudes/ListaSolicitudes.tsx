import AddRoundedIcon from "@mui/icons-material/AddRounded";
import CheckRoundedIcon from "@mui/icons-material/CheckRounded";
import { Box, Button, Chip, Stack, Tooltip, Typography } from "@mui/material";
import { keepPreviousData, useQuery } from "@tanstack/react-query";
import {
  Link as RouterLink,
  useNavigate,
  useSearchParams,
} from "react-router-dom";
import { api, type Pagina } from "../../api/cliente";
import type { EstadoSolicitud, FilaSolicitud } from "../../api/tipos";
import { colores } from "../../app/tema";
import { useSesion } from "../../auth/Sesion";
import { Buscador } from "../../components/Buscador";
import { Encabezado } from "../../components/Encabezado";
import {
  ESTADOS_SOLICITUD,
  EstadoSolicitudChip,
} from "../../components/Estados";
import { Seccion } from "../../components/Seccion";
import { DosLineas, Tabla } from "../../components/Tabla";
import { fechaHora, pesos, relativo } from "../../lib/formato";

const FILTROS: { valor: string; texto: string }[] = [
  { valor: "", texto: "Todas" },
  { valor: "en_revision,con_observaciones", texto: "Por evaluar" },
  { valor: "en_revision", texto: "En revisión" },
  { valor: "con_observaciones", texto: "Con observaciones" },
  { valor: "aceptada", texto: "Aceptadas" },
  { valor: "ejecutada", texto: "Ejecutadas" },
  { valor: "cancelada,no_aplica", texto: "Canceladas o no aplica" },
];

export function AbonoIndicador({
  pagado,
  total,
  minimo,
  cumplido,
}: {
  pagado: number;
  total: number;
  minimo: number;
  cumplido: boolean;
}) {
  const pct = total > 0 ? Math.min(100, Math.round((pagado / total) * 100)) : 0;
  return (
    <Tooltip
      title={`Pagado ${pesos(pagado)} de ${pesos(total)}. Abono mínimo: ${pesos(minimo)}.`}
    >
      <Box sx={{ minWidth: 110 }}>
        <Stack direction="row" spacing={0.5} sx={{ alignItems: "center" }}>
          <Typography variant="body2" sx={{ fontWeight: 600 }}>
            {pct} %
          </Typography>
          {cumplido && (
            <CheckRoundedIcon sx={{ fontSize: 16, color: colores.ok }} />
          )}
        </Stack>
        <Box
          sx={{
            height: 4,
            bgcolor: "#EEEAEC",
            borderRadius: 2,
            mt: 0.5,
            position: "relative",
            overflow: "hidden",
          }}
        >
          <Box
            sx={{
              position: "absolute",
              inset: 0,
              width: `${pct}%`,
              bgcolor: cumplido ? colores.ok : colores.alerta,
              borderRadius: 2,
            }}
          />
        </Box>
      </Box>
    </Tooltip>
  );
}

export default function ListaSolicitudes() {
  const { puede } = useSesion();
  const navegar = useNavigate();
  const [params, setParams] = useSearchParams();
  const estado = params.get("estado") ?? "";
  const q = params.get("q") ?? "";
  const pagina = Number(params.get("pagina") ?? 1);
  const cambiar = (cambios: Record<string, string>) => {
    const p = new URLSearchParams(params);
    Object.entries(cambios).forEach(([k, v]) =>
      v ? p.set(k, v) : p.delete(k),
    );
    if (!("pagina" in cambios)) p.delete("pagina");
    setParams(p, { replace: true });
  };

  const { data, isFetching } = useQuery({
    queryKey: ["solicitudes", { estado, q, pagina }],
    queryFn: async () =>
      (
        await api.get<Pagina<FilaSolicitud>>("/solicitudes", {
          params: {
            estado: estado || undefined,
            q: q || undefined,
            page: pagina,
          },
        })
      ).data,
    placeholderData: keepPreviousData,
  });

  return (
    <>
      <Encabezado
        titulo="Solicitudes de servicio"
        descripcion="Reservas de velatorio y traslado. Al aceptarlas se descuenta el stock y se confirman el vehículo y el equipo."
        acciones={
          puede("solicitudes.crear") && (
            <Button
              variant="contained"
              startIcon={<AddRoundedIcon />}
              component={RouterLink}
              to="/expedientes/nuevo"
            >
              Nuevo servicio
            </Button>
          )
        }
      />
      <Seccion sinRelleno>
        <Stack spacing={1.5} sx={{ p: 2 }}>
          <Buscador
            valor={q}
            alCambiar={(v) => cambiar({ q: v })}
            placeholder="Buscar por fallecido, contratante o código"
            ancho={420}
          />
          <Stack direction="row" sx={{ flexWrap: "wrap", gap: 1 }}>
            {FILTROS.map((f) => (
              <Chip
                key={f.valor}
                label={f.texto}
                clickable
                onClick={() => cambiar({ estado: f.valor })}
                color={estado === f.valor ? "primary" : "default"}
                variant={estado === f.valor ? "filled" : "outlined"}
              />
            ))}
          </Stack>
        </Stack>
        <Tabla
          filas={data?.data}
          clave={(f) => f.id}
          cargando={isFetching}
          alClic={(f) => navegar(`/solicitudes/${f.id}`)}
          vacio="No hay solicitudes con estos filtros."
          paginacion={
            data
              ? {
                  pagina,
                  total: data.total,
                  porPagina: data.per_page,
                  alCambiar: (p) => cambiar({ pagina: String(p) }),
                }
              : undefined
          }
          columnas={[
            {
              id: "difunto",
              titulo: "Fallecido",
              render: (f) => (
                <DosLineas
                  principal={f.difunto}
                  secundaria={`${f.codigo}, contratante ${f.cliente}`}
                />
              ),
            },
            {
              id: "traslado",
              titulo: "Traslado",
              render: (f) => (
                <DosLineas
                  principal={fechaHora(f.traslado_at)}
                  secundaria={relativo(f.traslado_at)}
                />
              ),
            },
            {
              id: "destino",
              titulo: "Sepultación",
              ocultarEnMovil: true,
              render: (f) => (
                <Typography variant="body2">{f.lugar_sepultura}</Typography>
              ),
            },
            {
              id: "estado",
              titulo: "Estado",
              render: (f) => <EstadoSolicitudChip estado={f.estado} />,
            },
            {
              id: "abono",
              titulo: "Pagado",
              ocultarEnMovil: true,
              render: (f) => (
                <AbonoIndicador
                  pagado={Number(f.pagado)}
                  total={Number(f.total)}
                  minimo={f.abono_minimo}
                  cumplido={f.abono_cumplido}
                />
              ),
            },
            {
              id: "vehiculo",
              titulo: "Vehículo",
              ocultarEnMovil: true,
              render: (f) => (
                <DosLineas
                  principal={f.vehiculo ?? "—"}
                  secundaria={
                    f.vehiculo_origen === "externo" ? "Arrendado" : undefined
                  }
                />
              ),
            },
          ]}
        />
      </Seccion>
    </>
  );
}

export const etiquetaEstado = (e: EstadoSolicitud) =>
  ESTADOS_SOLICITUD[e].etiqueta;
