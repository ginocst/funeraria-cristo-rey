import ArrowDownwardRoundedIcon from "@mui/icons-material/ArrowDownwardRounded";
import ArrowUpwardRoundedIcon from "@mui/icons-material/ArrowUpwardRounded";
import WarningAmberRoundedIcon from "@mui/icons-material/WarningAmberRounded";
import {
  Alert,
  Box,
  Button,
  Divider,
  Link,
  Paper,
  Skeleton,
  Stack,
  Typography,
} from "@mui/material";
import { useQuery } from "@tanstack/react-query";
import type { EChartsOption } from "echarts";
import { useMemo, type ReactNode } from "react";
import { Link as RouterLink } from "react-router-dom";
import { api, leerError } from "../../api/cliente";
import type { EstadoSolicitud, Panel as DatosPanel } from "../../api/tipos";
import { colores } from "../../app/tema";
import { useSesion } from "../../auth/Sesion";
import { ESTADOS_SOLICITUD } from "../../components/Estados";
import { Grafico } from "../../components/Grafico";
import { Seccion } from "../../components/Seccion";
import {
  ahoraLocal,
  capitalizar,
  fechaLarga,
  hora,
  mesCorto,
  numero,
  pesos,
  pesosCortos,
  relativo,
} from "../../lib/formato";
import { LineaDelDia } from "./LineaDelDia";

/** Ejes y cuadrícula discretos: los datos son lo protagónico */
const ejeX = (datos: string[]) => ({
  type: "category" as const,
  data: datos,
  axisTick: { show: false },
  axisLine: { lineStyle: { color: colores.linea } },
  axisLabel: { color: colores.piedra, fontSize: 11 },
});
const ejeY = (formato?: (v: number) => string) => ({
  type: "value" as const,
  splitLine: { lineStyle: { color: "#EEEAEC" } },
  axisLabel: { color: colores.piedra, fontSize: 11, formatter: formato },
});
const tooltip = (formato: (v: number) => string) => ({
  trigger: "axis" as const,
  axisPointer: {
    type: "shadow" as const,
    shadowStyle: { color: "rgba(143,29,36,0.06)" },
  },
  backgroundColor: colores.tinta,
  borderWidth: 0,
  textStyle: { color: "#fff", fontSize: 12 },
  valueFormatter: (v: unknown) => formato(Number(v)),
});

function Indicador({
  titulo,
  valor,
  detalle,
  enlace,
  alerta,
}: {
  titulo: string;
  valor: ReactNode;
  detalle?: ReactNode;
  enlace?: string;
  alerta?: boolean;
}) {
  const contenido = (
    <Box sx={{ px: { xs: 2, md: 2.5 }, py: 2, height: "100%" }}>
      <Typography
        variant="body2"
        color="text.secondary"
        sx={{ display: "flex", alignItems: "center", gap: 0.5 }}
      >
        {alerta && (
          <WarningAmberRoundedIcon
            sx={{ fontSize: 16, color: colores.alerta }}
          />
        )}
        {titulo}
      </Typography>
      <Typography
        sx={{
          fontSize: "1.75rem",
          fontWeight: 600,
          lineHeight: 1.25,
          mt: 0.5,
          letterSpacing: "-0.01em",
        }}
      >
        {valor}
      </Typography>
      {detalle && (
        <Typography
          variant="caption"
          color="text.secondary"
          component="div"
          sx={{ mt: 0.25 }}
        >
          {detalle}
        </Typography>
      )}
    </Box>
  );
  return enlace ? (
    <Box
      component={RouterLink}
      to={enlace}
      sx={{
        color: "inherit",
        textDecoration: "none",
        display: "block",
        "&:hover": { bgcolor: colores.vinoTenue },
      }}
    >
      {contenido}
    </Box>
  ) : (
    contenido
  );
}

function Variacion({ actual, anterior }: { actual: number; anterior: number }) {
  if (!anterior) return <>sin datos del mes anterior</>;
  const dif = actual - anterior;
  const Icono = dif >= 0 ? ArrowUpwardRoundedIcon : ArrowDownwardRoundedIcon;
  return (
    <Box
      component="span"
      sx={{ display: "inline-flex", alignItems: "center", gap: 0.25 }}
    >
      <Icono sx={{ fontSize: 13 }} />
      {anterior} el mes pasado
    </Box>
  );
}

export default function Panel() {
  const { sesion, puede } = useSesion();
  const conMontos = puede("reportes.financieros");
  const {
    data: d,
    isLoading,
    error,
  } = useQuery({
    queryKey: ["panel"],
    queryFn: async () => (await api.get<DatosPanel>("/dashboard")).data,
    refetchInterval: 60_000,
  });

  const opcionServicios = useMemo<EChartsOption | null>(
    () =>
      !d
        ? null
        : {
            grid: { left: 8, right: 8, top: 16, bottom: 4, containLabel: true },
            tooltip: tooltip((v) => `${v} servicios`),
            xAxis: ejeX(d.servicios_por_mes.map((m) => mesCorto(m.mes))),
            yAxis: { ...ejeY(), minInterval: 1 },
            series: [
              {
                type: "bar",
                name: "Servicios",
                data: d.servicios_por_mes.map((m) => m.total),
                barMaxWidth: 22,
                itemStyle: {
                  color: colores.serie1,
                  borderRadius: [4, 4, 0, 0],
                },
                emphasis: { itemStyle: { color: colores.vinoOscuro } },
              },
            ],
          },
    [d],
  );

  const opcionFinanzas = useMemo<EChartsOption | null>(
    () =>
      d?.finanzas_por_mes
        ? {
            grid: { left: 8, right: 8, top: 36, bottom: 4, containLabel: true },
            legend: {
              top: 0,
              left: 0,
              icon: "roundRect",
              itemWidth: 12,
              itemHeight: 8,
              textStyle: { color: colores.piedra },
            },
            tooltip: tooltip(pesos),
            xAxis: ejeX(d.finanzas_por_mes.map((m) => mesCorto(m.mes))),
            yAxis: ejeY(pesosCortos),
            series: [
              {
                type: "bar",
                name: "Ingresos",
                data: d.finanzas_por_mes.map((m) => m.ingresos),
                barMaxWidth: 14,
                barGap: "15%",
                itemStyle: {
                  color: colores.serie1,
                  borderRadius: [4, 4, 0, 0],
                },
              },
              {
                type: "bar",
                name: "Gastos",
                data: d.finanzas_por_mes.map((m) => m.gastos),
                barMaxWidth: 14,
                itemStyle: {
                  color: colores.serie2,
                  borderRadius: [4, 4, 0, 0],
                },
              },
            ],
          }
        : null,
    [d],
  );

  const opcionUrnas = useMemo<EChartsOption | null>(
    () =>
      !d
        ? null
        : {
            grid: { left: 8, right: 36, top: 4, bottom: 4, containLabel: true },
            tooltip: {
              ...tooltip((v) => `${v} unidades`),
              axisPointer: { type: "none" },
            },
            xAxis: {
              ...ejeY(),
              minInterval: 1,
              splitLine: { show: false },
              axisLabel: { show: false },
            },
            yAxis: {
              ...ejeX([...d.urnas_mas_vendidas].reverse().map((u) => u.nombre)),
              axisLine: { show: false },
            },
            series: [
              {
                type: "bar",
                name: "Unidades",
                data: [...d.urnas_mas_vendidas]
                  .reverse()
                  .map((u) => Number(u.cantidad)),
                barMaxWidth: 16,
                itemStyle: {
                  color: colores.serie1,
                  borderRadius: [0, 4, 4, 0],
                },
                label: {
                  show: true,
                  position: "right",
                  color: colores.piedra,
                  fontSize: 11,
                },
              },
            ],
          },
    [d],
  );

  if (error) return <Alert severity="error">{leerError(error).mensaje}</Alert>;

  const k = d?.kpis;
  const hoy = ahoraLocal();
  const saludo =
    hoy.hour() < 12
      ? "Buenos días"
      : hoy.hour() < 20
        ? "Buenas tardes"
        : "Buenas noches";
  const porEvaluar =
    (d?.solicitudes_por_estado.en_revision ?? 0) +
    (d?.solicitudes_por_estado.con_observaciones ?? 0);

  return (
    <Box>
      <Box component="header" sx={{ mb: 3 }}>
        <Typography variant="h1">
          {saludo}, {sesion?.nombre.split(" ")[0]}
        </Typography>
        <Typography variant="subtitle1" sx={{ mt: 0.5 }}>
          {capitalizar(fechaLarga(hoy.toISOString(), false))}
        </Typography>
      </Box>

      {/* Indicadores del mes en una sola franja */}
      <Paper
        variant="outlined"
        sx={{ borderRadius: 2.5, mb: 3, overflow: "hidden" }}
      >
        {isLoading || !k ? (
          <Skeleton variant="rectangular" height={104} />
        ) : (
          <Box
            sx={{
              display: "grid",
              gridTemplateColumns: {
                xs: "1fr 1fr",
                md: `repeat(${conMontos ? 6 : 5}, 1fr)`,
              },
              "& > *": {
                borderRight: `1px solid ${colores.linea}`,
                borderBottom: { xs: `1px solid ${colores.linea}`, md: 0 },
              },
            }}
          >
            <Indicador
              titulo="Servicios este mes"
              valor={numero(k.servicios_mes)}
              detalle={
                <Variacion
                  actual={k.servicios_mes}
                  anterior={k.servicios_mes_anterior}
                />
              }
              enlace="/calendario"
            />
            <Indicador
              titulo="En curso ahora"
              valor={numero(k.en_curso)}
              detalle="velatorios o traslados"
            />
            <Indicador
              titulo="Por evaluar"
              valor={numero(k.en_revision)}
              detalle="solicitudes en revisión o con observaciones"
              enlace="/solicitudes?estado=en_revision,con_observaciones"
              alerta={k.en_revision > 0}
            />
            {conMontos && (
              <Indicador
                titulo="Ingresos del mes"
                valor={pesos(k.ingresos_mes)}
                detalle={`Resultado ${pesos(k.resultado_mes)} tras ${pesos(k.gastos_mes)} en gastos`}
                enlace="/reportes"
              />
            )}
            {conMontos && (
              <Indicador
                titulo="Saldo por cobrar"
                valor={pesos(k.saldo_por_cobrar)}
                detalle={`${k.cuotas_atrasadas} cuotas atrasadas`}
                enlace="/cobranza"
                alerta={k.cuotas_atrasadas > 0}
              />
            )}
            {!conMontos && (
              <Indicador
                titulo="Cuotas atrasadas"
                valor={numero(k.cuotas_atrasadas)}
                detalle="requieren seguimiento"
                enlace="/cobranza"
                alerta={k.cuotas_atrasadas > 0}
              />
            )}
            <Indicador
              titulo="Stock crítico"
              valor={numero(k.stock_critico)}
              detalle="productos en el mínimo"
              enlace="/inventario?criticos=1"
              alerta={k.stock_critico > 0}
            />
          </Box>
        )}
      </Paper>

      <Box
        sx={{
          display: "grid",
          gap: 3,
          gridTemplateColumns: { xs: "1fr", lg: "repeat(12, 1fr)" },
        }}
      >
        <Seccion
          titulo="Servicios de hoy"
          descripcion="Dónde está cada servicio en este momento"
          sx={{ gridColumn: { lg: "span 8" } }}
        >
          {d ? (
            <LineaDelDia servicios={d.servicios_hoy} />
          ) : (
            <Skeleton height={160} />
          )}
        </Seccion>

        <Seccion
          titulo="Próximos traslados"
          sx={{ gridColumn: { lg: "span 4" } }}
          acciones={
            <Button size="small" component={RouterLink} to="/calendario">
              Calendario
            </Button>
          }
        >
          {d?.proximos_traslados.length === 0 && (
            <Typography color="text.secondary">
              No hay traslados confirmados.
            </Typography>
          )}
          <Stack divider={<Divider />} spacing={1.5}>
            {d?.proximos_traslados.slice(0, 5).map((t) => (
              <Box key={t.id}>
                <Stack
                  direction="row"
                  sx={{
                    justifyContent: "space-between",
                    alignItems: "baseline",
                    gap: 1,
                  }}
                >
                  <Link
                    component={RouterLink}
                    to={`/solicitudes/${t.id}`}
                    underline="hover"
                    sx={{ fontWeight: 600, color: "text.primary" }}
                  >
                    {t.difunto}
                  </Link>
                  <Typography
                    variant="body2"
                    sx={{
                      fontWeight: 600,
                      color: "primary.main",
                      whiteSpace: "nowrap",
                    }}
                  >
                    {hora(t.traslado_at)}
                  </Typography>
                </Stack>
                <Typography variant="body2" color="text.secondary">
                  {capitalizar(relativo(t.traslado_at))}, hacia{" "}
                  {t.lugar_sepultura}
                </Typography>
                <Typography variant="caption" color="text.secondary">
                  {t.vehiculo}
                  {t.origen === "externo" ? " (arrendada)" : ""}
                  {t.equipo.length ? `, con ${t.equipo.join(" y ")}` : ""}.
                  Presentación {hora(t.presentacion)}.
                </Typography>
              </Box>
            ))}
          </Stack>
        </Seccion>

        <Seccion
          titulo="Servicios por mes"
          descripcion="Servicios aceptados o ejecutados, últimos 12 meses"
          sx={{ gridColumn: { lg: conMontos ? "span 6" : "span 7" } }}
        >
          {opcionServicios ? (
            <Grafico
              opcion={opcionServicios}
              etiqueta="Gráfico de barras de servicios por mes"
            />
          ) : (
            <Skeleton height={260} />
          )}
        </Seccion>

        {conMontos ? (
          <Seccion
            titulo="Ingresos y gastos"
            descripcion="Pagos recibidos y gastos registrados por mes"
            sx={{ gridColumn: { lg: "span 6" } }}
          >
            {opcionFinanzas ? (
              <Grafico
                opcion={opcionFinanzas}
                etiqueta="Gráfico de barras de ingresos y gastos por mes"
              />
            ) : (
              <Skeleton height={260} />
            )}
          </Seccion>
        ) : (
          <Seccion
            titulo="Urnas más elegidas"
            descripcion="Últimos 12 meses"
            sx={{ gridColumn: { lg: "span 5" } }}
          >
            {opcionUrnas ? (
              <Grafico opcion={opcionUrnas} etiqueta="Urnas más elegidas" />
            ) : (
              <Skeleton height={260} />
            )}
          </Seccion>
        )}

        {conMontos && (
          <Seccion
            titulo="Urnas más elegidas"
            descripcion="Unidades en los últimos 12 meses"
            sx={{ gridColumn: { lg: "span 5" } }}
          >
            {opcionUrnas ? (
              <Grafico
                opcion={opcionUrnas}
                alto={230}
                etiqueta="Urnas más elegidas"
              />
            ) : (
              <Skeleton height={230} />
            )}
          </Seccion>
        )}

        <Seccion
          titulo="Solicitudes"
          sx={{ gridColumn: { lg: conMontos ? "span 3" : "span 6" } }}
          acciones={
            <Button size="small" component={RouterLink} to="/solicitudes">
              Ver todas
            </Button>
          }
        >
          <Stack spacing={1}>
            {(
              [
                "en_revision",
                "con_observaciones",
                "aceptada",
              ] as EstadoSolicitud[]
            ).map((e) => (
              <Stack
                key={e}
                direction="row"
                component={RouterLink}
                to={`/solicitudes?estado=${e}`}
                sx={{
                  alignItems: "center",
                  justifyContent: "space-between",
                  textDecoration: "none",
                  color: "inherit",
                  p: 1,
                  mx: -1,
                  borderRadius: 1.5,
                  "&:hover": { bgcolor: colores.vinoTenue },
                }}
              >
                <Stack
                  direction="row"
                  spacing={1}
                  sx={{
                    alignItems: "center",
                    "& svg": {
                      fontSize: 18,
                      color: ESTADOS_SOLICITUD[e].color,
                    },
                  }}
                >
                  {ESTADOS_SOLICITUD[e].icono}
                  <Typography variant="body2">
                    {ESTADOS_SOLICITUD[e].etiqueta}
                  </Typography>
                </Stack>
                <Typography sx={{ fontWeight: 700 }}>
                  {d?.solicitudes_por_estado[e] ?? 0}
                </Typography>
              </Stack>
            ))}
          </Stack>
          {porEvaluar > 0 && puede("solicitudes.evaluar") && (
            <Button
              fullWidth
              variant="contained"
              sx={{ mt: 2 }}
              component={RouterLink}
              to="/solicitudes?estado=en_revision"
            >
              Evaluar solicitudes
            </Button>
          )}
        </Seccion>

        <Seccion
          titulo="Stock crítico"
          sx={{ gridColumn: { lg: conMontos ? "span 4" : "span 6" } }}
          acciones={
            <Button
              size="small"
              component={RouterLink}
              to="/inventario?criticos=1"
            >
              Inventario
            </Button>
          }
        >
          {d?.stock_critico.length === 0 && (
            <Typography color="text.secondary">
              Todo el inventario está sobre el mínimo.
            </Typography>
          )}
          <Stack spacing={1.25}>
            {d?.stock_critico.map((p) => (
              <Stack
                key={p.id}
                direction="row"
                sx={{
                  alignItems: "center",
                  justifyContent: "space-between",
                  gap: 1,
                }}
              >
                <Box sx={{ minWidth: 0 }}>
                  <Typography variant="body2" sx={{ fontWeight: 600 }}>
                    {p.nombre}
                  </Typography>
                  <Typography variant="caption" color="text.secondary">
                    {p.codigo}, mínimo {p.umbral_minimo}
                  </Typography>
                </Box>
                <Typography
                  sx={{
                    fontWeight: 700,
                    color: p.stock === 0 ? colores.critico : colores.alerta,
                    whiteSpace: "nowrap",
                  }}
                >
                  {p.stock} {p.stock === 1 ? "unidad" : "unidades"}
                </Typography>
              </Stack>
            ))}
          </Stack>
        </Seccion>
      </Box>
    </Box>
  );
}
