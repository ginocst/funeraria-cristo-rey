import PictureAsPdfRoundedIcon from "@mui/icons-material/PictureAsPdfRounded";
import TableViewRoundedIcon from "@mui/icons-material/TableViewRounded";
import {
  Box,
  Button,
  MenuItem,
  Skeleton,
  Stack,
  Table,
  TableBody,
  TableCell,
  TableHead,
  TableRow,
  TextField,
  Typography,
} from "@mui/material";
import { useQuery } from "@tanstack/react-query";
import { useState } from "react";
import { descargarBlob, guardarBlob } from "../../api/cliente";
import { api } from "../../api/cliente";
import type { ReporteMensual } from "../../api/tipos";
import { colores } from "../../app/tema";
import { Encabezado } from "../../components/Encabezado";
import { EstadoSolicitudChip } from "../../components/Estados";
import { useAvisar } from "../../components/Notificador";
import { Seccion } from "../../components/Seccion";
import { Tabla } from "../../components/Tabla";
import { VisorPdf } from "../../components/VisorPdf";
import { ahoraLocal, fechaHora, mesLargo, pesos } from "../../lib/formato";

function Barras({ filas }: { filas: { etiqueta: string; valor: number }[] }) {
  const max = Math.max(1, ...filas.map((f) => f.valor));
  return (
    <Stack spacing={1.25}>
      {filas.map((f) => (
        <Box key={f.etiqueta} title={`${f.etiqueta}: ${pesos(f.valor)}`}>
          <Stack direction="row" sx={{ justifyContent: "space-between" }}>
            <Typography variant="body2">{f.etiqueta}</Typography>
            <Typography variant="body2" sx={{ fontWeight: 600 }}>
              {pesos(f.valor)}
            </Typography>
          </Stack>
          <Box sx={{ height: 6, borderRadius: 3, bgcolor: "#EEEAEC", mt: 0.5 }}>
            <Box
              sx={{
                height: "100%",
                width: `${(f.valor / max) * 100}%`,
                bgcolor: colores.serie1,
                borderRadius: 3,
              }}
            />
          </Box>
        </Box>
      ))}
      {filas.length === 0 && (
        <Typography variant="body2" color="text.secondary">
          Sin movimientos en el mes.
        </Typography>
      )}
    </Stack>
  );
}

/** Reporte mensual de servicios, ingresos, gastos y urnas, exportable a PDF y Excel */
export default function Reportes() {
  const avisar = useAvisar();
  const ahora = ahoraLocal();
  const meses = Array.from({ length: 13 }, (_, i) =>
    ahora.subtract(i, "month").format("YYYY-MM"),
  );
  const [mes, setMes] = useState(meses[1]);
  const [verPdf, setVerPdf] = useState(false);
  const { data: r, isFetching } = useQuery({
    queryKey: ["reporte", mes],
    queryFn: async () =>
      (await api.get<ReporteMensual>("/reportes/mensual", { params: { mes } }))
        .data,
  });

  const excel = async () => {
    try {
      guardarBlob(
        await descargarBlob(`/reportes/mensual.xlsx?mes=${mes}`),
        `reporte-${mes}.xlsx`,
      );
    } catch {
      avisar("No se pudo descargar el Excel.", "error");
    }
  };

  return (
    <>
      <Encabezado
        titulo="Reporte mensual"
        descripcion="Resumen para la gestión del mes: servicios realizados, ingresos por medio de pago, gastos por categoría y urnas vendidas."
        acciones={
          <>
            <TextField
              select
              value={mes}
              onChange={(e) => setMes(e.target.value)}
              sx={{ width: 220 }}
              slotProps={{ htmlInput: { "aria-label": "Mes del reporte" } }}
            >
              {meses.map((m) => (
                <MenuItem key={m} value={m}>
                  {mesLargo(m)}
                </MenuItem>
              ))}
            </TextField>
            <Button
              variant="outlined"
              startIcon={<TableViewRoundedIcon />}
              onClick={excel}
            >
              Excel
            </Button>
            <Button
              variant="contained"
              startIcon={<PictureAsPdfRoundedIcon />}
              onClick={() => setVerPdf(true)}
            >
              Ver PDF
            </Button>
          </>
        }
      />
      {!r || isFetching ? (
        <Skeleton height={400} />
      ) : (
        <Stack spacing={3}>
          <Box
            sx={{
              display: "grid",
              gap: 3,
              gridTemplateColumns: { xs: "1fr 1fr", md: "repeat(4, 1fr)" },
            }}
          >
            {[
              { t: "Servicios", v: String(r.totales.servicios) },
              { t: "Ingresos", v: pesos(r.totales.ingresos) },
              { t: "Gastos", v: pesos(r.totales.gastos) },
              {
                t: "Resultado",
                v: pesos(r.totales.resultado),
                c: r.totales.resultado < 0 ? colores.critico : colores.ok,
              },
            ].map((x) => (
              <Box
                key={x.t}
                sx={{
                  p: 2.5,
                  borderRadius: 2.5,
                  border: `1px solid ${colores.linea}`,
                  bgcolor: "background.paper",
                }}
              >
                <Typography variant="body2" color="text.secondary">
                  {x.t}
                </Typography>
                <Typography
                  sx={{ fontSize: "1.6rem", fontWeight: 600, color: x.c }}
                >
                  {x.v}
                </Typography>
              </Box>
            ))}
          </Box>
          <Box
            sx={{
              display: "grid",
              gap: 3,
              gridTemplateColumns: { xs: "1fr", md: "1fr 1fr 1fr" },
            }}
          >
            <Seccion titulo="Ingresos por medio de pago">
              <Barras
                filas={r.ingresos_por_medio.map((x) => ({
                  etiqueta: x.medio,
                  valor: Number(x.total),
                }))}
              />
            </Seccion>
            <Seccion titulo="Gastos por categoría">
              <Barras
                filas={r.gastos_por_categoria.map((x) => ({
                  etiqueta: x.categoria,
                  valor: Number(x.total),
                }))}
              />
            </Seccion>
            <Seccion titulo="Urnas vendidas" sinRelleno>
              <Table size="small">
                <TableHead>
                  <TableRow>
                    <TableCell>Producto</TableCell>
                    <TableCell align="right">Unid.</TableCell>
                    <TableCell align="right">Monto</TableCell>
                  </TableRow>
                </TableHead>
                <TableBody>
                  {r.urnas_vendidas.map((u) => (
                    <TableRow key={u.id}>
                      <TableCell>{u.nombre}</TableCell>
                      <TableCell align="right">{u.cantidad}</TableCell>
                      <TableCell align="right">{pesos(u.monto)}</TableCell>
                    </TableRow>
                  ))}
                </TableBody>
              </Table>
            </Seccion>
          </Box>
          <Seccion
            titulo={`Servicios de ${mesLargo(mes).toLowerCase()}`}
            sinRelleno
          >
            <Tabla
              filas={r.servicios}
              clave={(s) => s.codigo}
              densa
              vacio="No hubo servicios este mes."
              columnas={[
                { id: "cod", titulo: "Expediente", render: (s) => s.codigo },
                { id: "dif", titulo: "Fallecido", render: (s) => s.difunto },
                {
                  id: "cli",
                  titulo: "Contratante",
                  ocultarEnMovil: true,
                  render: (s) => s.cliente,
                },
                {
                  id: "tras",
                  titulo: "Traslado",
                  render: (s) => fechaHora(s.traslado_at),
                },
                {
                  id: "est",
                  titulo: "Estado",
                  render: (s) => <EstadoSolicitudChip estado={s.estado} />,
                },
                {
                  id: "tot",
                  titulo: "Total",
                  alinear: "right",
                  render: (s) => pesos(s.total),
                },
                {
                  id: "sal",
                  titulo: "Saldo",
                  alinear: "right",
                  render: (s) => pesos(s.saldo),
                },
              ]}
            />
          </Seccion>
        </Stack>
      )}
      <VisorPdf
        abierto={verPdf}
        titulo={`Reporte de ${mesLargo(mes).toLowerCase()}`}
        ruta={`/reportes/mensual.pdf?mes=${mes}`}
        nombreArchivo={`reporte-${mes}.pdf`}
        alCerrar={() => setVerPdf(false)}
      />
    </>
  );
}
