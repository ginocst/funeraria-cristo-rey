import { Box, Stack, Tooltip, Typography } from "@mui/material";
import { useEffect, useState } from "react";
import { Link as RouterLink } from "react-router-dom";
import type { ServicioDelDia } from "../../api/tipos";
import { colores } from "../../app/tema";
import { ahoraLocal, aLocal, hora } from "../../lib/formato";

const INICIO = 6; // 06:00
const FIN = 24; // 24:00
const HORAS = FIN - INICIO;

/** Posición de un instante dentro de la ventana de 00:00 a 24:00 horas */
function posicion(valor: string, base: ReturnType<typeof ahoraLocal>) {
  const d = aLocal(valor)!;
  const horas = d.diff(base, "minute") / 60 - INICIO;
  return Math.min(100, Math.max(0, (horas / HORAS) * 100));
}

function estadoActual(s: ServicioDelDia, ahora: ReturnType<typeof ahoraLocal>) {
  const vel = aLocal(s.velatorio_inicio)!;
  const tras = aLocal(s.traslado_at)!;
  const pres = aLocal(s.presentacion)!;
  const fin = aLocal(s.fin)!;
  if (ahora.isBefore(vel))
    return {
      texto: `Velatorio desde las ${hora(s.velatorio_inicio)} en ${s.lugar_velatorio}.`,
      enCurso: false,
    };
  if (ahora.isBefore(pres))
    return {
      texto: `Se está velando en ${s.lugar_velatorio}. Traslado a las ${hora(s.traslado_at)} hacia ${s.lugar_sepultura}.`,
      enCurso: true,
    };
  if (ahora.isBefore(tras))
    return {
      texto: `El equipo se presenta para el traslado de las ${hora(s.traslado_at)} (${s.vehiculo ?? "vehículo por confirmar"}).`,
      enCurso: true,
    };
  if (ahora.isBefore(fin))
    return { texto: `En traslado hacia ${s.lugar_sepultura}.`, enCurso: true };
  return {
    texto: `Servicio terminado. Sepultación en ${s.lugar_sepultura}.`,
    enCurso: false,
  };
}

/**
 * Línea de tiempo de los servicios de hoy
 */
export function LineaDelDia({ servicios }: { servicios: ServicioDelDia[] }) {
  const [ahora, setAhora] = useState(ahoraLocal());
  useEffect(() => {
    const t = setInterval(() => setAhora(ahoraLocal()), 60_000);
    return () => clearInterval(t);
  }, []);
  const base = ahora.startOf("day");
  const pAhora = posicion(ahora.toISOString(), base);
  const marcas = Array.from(
    { length: HORAS / 3 + 1 },
    (_, i) => INICIO + i * 3,
  );

  if (!servicios.length) {
    return (
      <Typography color="text.secondary" sx={{ py: 4, textAlign: "center" }}>
        No hay velatorios ni traslados programados para hoy.
      </Typography>
    );
  }

  return (
    <Box>
      {/* Escala de horas */}
      <Box
        sx={{
          display: "grid",
          gridTemplateColumns: { xs: "1fr", md: "220px 1fr" },
          columnGap: 2,
        }}
      >
        <Box sx={{ display: { xs: "none", md: "block" } }} />
        <Box sx={{ position: "relative", height: 20, mb: 0.5 }}>
          {marcas.map((h) => (
            <Typography
              key={h}
              variant="caption"
              color="text.secondary"
              sx={{
                position: "absolute",
                left: `${((h - INICIO) / HORAS) * 100}%`,
                transform:
                  h === FIN
                    ? "translateX(-100%)"
                    : h === INICIO
                      ? "none"
                      : "translateX(-50%)",
              }}
            >
              {String(h % 24).padStart(2, "0")}:00
            </Typography>
          ))}
        </Box>
      </Box>

      <Stack spacing={2.25}>
        {servicios.map((s) => {
          const desdeAyer = aLocal(s.velatorio_inicio)!.isBefore(
            base.add(INICIO, "hour"),
          );
          const pVel = posicion(s.velatorio_inicio, base);
          const pPres = posicion(s.presentacion, base);
          const pTras = posicion(s.traslado_at, base);
          const pFin = posicion(s.fin, base);
          const estado = estadoActual(s, ahora);
          return (
            <Box
              key={s.id}
              sx={{
                display: "grid",
                gridTemplateColumns: { xs: "1fr", md: "220px 1fr" },
                columnGap: 2,
                rowGap: 0.75,
                alignItems: "center",
              }}
            >
              <Box sx={{ minWidth: 0 }}>
                <Typography
                  component={RouterLink}
                  to={`/solicitudes/${s.id}`}
                  variant="body2"
                  sx={{
                    fontWeight: 600,
                    color: "text.primary",
                    textDecoration: "none",
                    "&:hover": { color: "primary.main" },
                    display: "block",
                    overflow: "hidden",
                    textOverflow: "ellipsis",
                    whiteSpace: "nowrap",
                  }}
                >
                  {s.difunto}
                </Typography>
                <Typography variant="caption" color="text.secondary">
                  {s.codigo}
                </Typography>
              </Box>
              <Box>
                <Box
                  sx={{
                    position: "relative",
                    height: 26,
                    borderRadius: 1,
                    bgcolor: "#F1EEF0",
                    backgroundImage: `repeating-linear-gradient(90deg, transparent 0 calc(${100 / (HORAS / 3)}% - 1px), ${colores.linea} calc(${100 / (HORAS / 3)}% - 1px) calc(${100 / (HORAS / 3)}%))`,
                  }}
                >
                  <Tooltip
                    title={`Velatorio en ${s.lugar_velatorio}${desdeAyer ? " (comenzó antes de las 06:00)" : ""}`}
                  >
                    <Box
                      sx={{
                        position: "absolute",
                        top: 5,
                        bottom: 5,
                        left: `${pVel}%`,
                        width: `${Math.max(pPres - pVel, 0.5)}%`,
                        bgcolor: colores.rosaCeniza,
                        borderRadius: desdeAyer ? "0 4px 4px 0" : 1,
                      }}
                    />
                  </Tooltip>
                  <Tooltip
                    title={`Traslado a las ${hora(s.traslado_at)}: equipo desde las ${hora(s.presentacion)} hasta las ${hora(s.fin)} aprox.`}
                  >
                    <Box
                      sx={{
                        position: "absolute",
                        top: 2,
                        bottom: 2,
                        left: `${pPres}%`,
                        width: `${Math.max(pFin - pPres, 0.8)}%`,
                        bgcolor: colores.vino,
                        borderRadius: 1,
                        boxShadow: "0 0 0 2px #fff",
                      }}
                    >
                      <Box
                        sx={{
                          position: "absolute",
                          top: 3,
                          bottom: 3,
                          left: `${((pTras - pPres) / Math.max(pFin - pPres, 0.8)) * 100}%`,
                          width: 2,
                          bgcolor: "#fff",
                          opacity: 0.85,
                        }}
                      />
                    </Box>
                  </Tooltip>
                  <Box
                    aria-hidden
                    sx={{
                      position: "absolute",
                      top: -4,
                      bottom: -4,
                      left: `${pAhora}%`,
                      width: 2,
                      bgcolor: colores.serie2,
                      borderRadius: 1,
                    }}
                  />
                </Box>
                <Typography
                  variant="body2"
                  sx={{
                    mt: 0.75,
                    color: estado.enCurso ? "text.primary" : "text.secondary",
                  }}
                >
                  {estado.enCurso && (
                    <Box
                      component="span"
                      sx={{
                        display: "inline-block",
                        width: 7,
                        height: 7,
                        borderRadius: "50%",
                        bgcolor: colores.serie2,
                        mr: 1,
                        verticalAlign: "middle",
                      }}
                    />
                  )}
                  {estado.texto}
                </Typography>
              </Box>
            </Box>
          );
        })}
      </Stack>

      <Stack
        direction="row"
        spacing={2.5}
        sx={{ mt: 2.5, flexWrap: "wrap", gap: 1, color: "text.secondary" }}
      >
        <Leyenda color={colores.rosaCeniza} texto="Velatorio" />
        <Leyenda
          color={colores.vino}
          texto="Vehículo y equipo comprometidos"
        />
        <Leyenda
          color={colores.serie2}
          texto={`Ahora, ${ahora.format("HH:mm")}`}
          linea
        />
      </Stack>
    </Box>
  );
}

function Leyenda({
  color,
  texto,
  linea,
}: {
  color: string;
  texto: string;
  linea?: boolean;
}) {
  return (
    <Stack direction="row" spacing={0.75} sx={{ alignItems: "center" }}>
      <Box
        sx={{
          width: linea ? 3 : 14,
          height: linea ? 14 : 10,
          bgcolor: color,
          borderRadius: 0.5,
        }}
      />
      <Typography variant="caption">{texto}</Typography>
    </Stack>
  );
}
