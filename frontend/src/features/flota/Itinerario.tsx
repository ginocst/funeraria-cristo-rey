import LocalShippingRoundedIcon from "@mui/icons-material/LocalShippingRounded";
import PlaceRoundedIcon from "@mui/icons-material/PlaceRounded";
import ScheduleRoundedIcon from "@mui/icons-material/ScheduleRounded";
import { Alert, Box, Chip, Skeleton, Stack, Typography } from "@mui/material";
import { useQuery } from "@tanstack/react-query";
import { api } from "../../api/cliente";
import type { Ausencia, ItemItinerario } from "../../api/tipos";
import { colores } from "../../app/tema";
import { useSesion } from "../../auth/Sesion";
import { Encabezado } from "../../components/Encabezado";
import { Vacio } from "../../components/Vacio";
import {
  ahoraLocal,
  aLocal,
  capitalizar,
  fecha,
  hora,
  relativo,
} from "../../lib/formato";

/**
 * Itinerario del operario: solo ve los servicios en que participa,
 * con la hora de presentación, el vehículo y el destino. Pensado para el teléfono
 */
export default function Itinerario() {
  const { sesion } = useSesion();
  const { data, isLoading } = useQuery({
    queryKey: ["itinerario"],
    queryFn: async () => (await api.get<ItemItinerario[]>("/itinerario")).data,
    refetchInterval: 120_000,
  });
  const ausencias = useQuery({
    queryKey: ["ausencias", "propias"],
    queryFn: async () =>
      (await api.get<Ausencia[]>("/ausencias", { params: { vigentes: 1 } }))
        .data,
  });
  const ahora = ahoraLocal();
  const proximos = (data ?? []).filter((i) =>
    aLocal(i.traslado_at)!.add(4, "hour").isAfter(ahora),
  );
  const mias = (ausencias.data ?? []).filter(
    (a) => a.usuario_id === sesion?.id,
  );

  return (
    <Box sx={{ maxWidth: 760 }}>
      <Encabezado
        titulo="Mi itinerario"
        descripcion="Servicios en los que usted participa. Preséntese a la hora indicada con el vehículo asignado."
      />
      {mias.map((a) => (
        <Alert key={a.id} severity="info" sx={{ mb: 2 }}>
          Tiene una ausencia registrada del {fecha(a.desde)} al {fecha(a.hasta)}
          .
        </Alert>
      ))}
      {isLoading && <Skeleton height={200} />}
      {!isLoading && proximos.length === 0 && (
        <Vacio
          icono={<LocalShippingRoundedIcon />}
          titulo="No tiene servicios asignados"
          texto="Cuando la administración acepte un servicio con usted en el equipo, aparecerá aquí y recibirá un aviso."
        />
      )}
      <Stack spacing={2}>
        {proximos.map((i) => {
          const yo = i.equipo.find((m) => m.id === sesion?.id);
          const enCurso = aLocal(i.presentacion_at)!.isBefore(ahora);
          return (
            <Box
              key={i.id}
              sx={{
                borderRadius: 3,
                border: `1px solid ${enCurso ? colores.vino : colores.linea}`,
                bgcolor: "background.paper",
                overflow: "hidden",
              }}
            >
              <Box
                sx={{
                  px: 2.5,
                  py: 2,
                  bgcolor: enCurso ? colores.vino : colores.vinoTenue,
                  color: enCurso ? "#fff" : "text.primary",
                }}
              >
                <Typography sx={{ fontWeight: 600 }}>
                  {capitalizar(
                    aLocal(i.traslado_at)!.format("dddd D [de] MMMM"),
                  )}
                </Typography>
                <Typography
                  sx={{
                    fontFamily: '"Cormorant Garamond", serif',
                    fontSize: "1.9rem",
                    fontWeight: 600,
                    lineHeight: 1.1,
                  }}
                >
                  Presentarse a las {hora(i.presentacion_at)}
                </Typography>
                <Typography variant="body2" sx={{ opacity: 0.85 }}>
                  {enCurso
                    ? "En curso"
                    : capitalizar(relativo(i.presentacion_at))}
                  . Traslado a las {hora(i.traslado_at)}.
                </Typography>
              </Box>
              <Stack spacing={1.5} sx={{ p: 2.5 }}>
                <Typography variant="h3" component="h2">
                  {i.difunto}
                </Typography>
                <Fila
                  icono={<PlaceRoundedIcon />}
                  titulo="Velatorio"
                  texto={`${i.lugar_velatorio} (desde el ${fecha(i.velatorio_inicio)} a las ${hora(i.velatorio_inicio)})`}
                />
                <Fila
                  icono={<PlaceRoundedIcon />}
                  titulo="Destino"
                  texto={`${i.lugar_sepultura}${i.sepultura_detalle ? `, ${i.sepultura_detalle}` : ""}`}
                />
                <Fila
                  icono={<LocalShippingRoundedIcon />}
                  titulo="Vehículo"
                  texto={`${i.vehiculo.nombre}${i.vehiculo.patente ? `, ${i.vehiculo.patente}` : ""}${i.vehiculo.origen === "externo" ? " (arrendado)" : ""}`}
                />
                <Fila
                  icono={<ScheduleRoundedIcon />}
                  titulo="Su función"
                  texto={yo?.funcion === "conductor" ? "Conductor" : "Apoyo"}
                />
                <Stack direction="row" sx={{ flexWrap: "wrap", gap: 1 }}>
                  {i.equipo.map((m) => (
                    <Chip
                      key={m.id}
                      size="small"
                      label={`${m.nombre}${m.funcion === "conductor" ? ", conductor" : ""}`}
                      variant={m.id === sesion?.id ? "filled" : "outlined"}
                    />
                  ))}
                </Stack>
                <Typography variant="caption" color="text.secondary">
                  {i.codigo}, sucursal {i.sucursal}
                </Typography>
              </Stack>
            </Box>
          );
        })}
      </Stack>
    </Box>
  );
}

function Fila({
  icono,
  titulo,
  texto,
}: {
  icono: React.ReactNode;
  titulo: string;
  texto: string;
}) {
  return (
    <Stack
      direction="row"
      spacing={1.5}
      sx={{ "& svg": { fontSize: 20, color: colores.vino, mt: 0.25 } }}
    >
      {icono}
      <Box>
        <Typography variant="body2" color="text.secondary">
          {titulo}
        </Typography>
        <Typography variant="body2" sx={{ fontWeight: 500 }}>
          {texto}
        </Typography>
      </Box>
    </Stack>
  );
}
