import PictureAsPdfRoundedIcon from "@mui/icons-material/PictureAsPdfRounded";
import TableViewRoundedIcon from "@mui/icons-material/TableViewRounded";
import {
  Box,
  Button,
  Stack,
  Table,
  TableBody,
  TableCell,
  TableHead,
  TableRow,
  Typography,
} from "@mui/material";
import { useQuery } from "@tanstack/react-query";
import { useState } from "react";
import { z } from "zod";
import { api, descargarBlob, guardarBlob } from "../../api/cliente";
import type {
  Donacion,
  ResumenDonaciones,
  TotalDonaciones,
} from "../../api/tipos";
import { P } from "../../auth/permisos";
import { PantallaCrud } from "../../components/Crud";
import { useAvisar } from "../../components/Notificador";
import { Seccion } from "../../components/Seccion";
import { VisorPdf } from "../../components/VisorPdf";
import { ahoraLocal, fecha, mesLargo, pesos } from "../../lib/formato";
import { useOrganizaciones } from "./consultas";
import { SelectorMes } from "./SelectorMes";

const esquema = z.object({
  organizacion_id: z.number({ message: "Seleccione la organización" }),
  fecha: z.string().min(1, "Indique la fecha"),
  monto: z
    .number({ message: "Indique el monto" })
    .int()
    .min(1, "Debe ser mayor que cero"),
  descripcion: z
    .string()
    .trim()
    .max(250)
    .transform((v) => v || null),
});

function Totales({
  concepto,
  filas,
}: {
  concepto: string;
  filas: TotalDonaciones[];
}) {
  return (
    <Table size="small">
      <TableHead>
        <TableRow>
          <TableCell>{concepto}</TableCell>
          <TableCell align="right">Donaciones</TableCell>
          <TableCell align="right">Monto</TableCell>
        </TableRow>
      </TableHead>
      <TableBody>
        {filas.map((f) => (
          <TableRow key={f.nombre}>
            <TableCell>{f.nombre}</TableCell>
            <TableCell align="right">{f.cantidad}</TableCell>
            <TableCell align="right">{pesos(f.monto)}</TableCell>
          </TableRow>
        ))}
        {filas.length === 0 && (
          <TableRow>
            <TableCell colSpan={3}>Sin donaciones en el mes.</TableCell>
          </TableRow>
        )}
      </TableBody>
    </Table>
  );
}

/** Resumen del mes elegido, sus exportables y el registro de donaciones */
export function DonacionesDelMes() {
  const avisar = useAvisar();
  const hoy = ahoraLocal().format("YYYY-MM-DD");
  const [mes, setMes] = useState(hoy.slice(0, 7));
  const [verPdf, setVerPdf] = useState(false);
  const organizaciones = useOrganizaciones();
  const { data: resumen } = useQuery({
    queryKey: ["donaciones", "mensual", mes],
    queryFn: async () =>
      (
        await api.get<ResumenDonaciones>("/donaciones/mensual", {
          params: { mes },
        })
      ).data,
  });

  const excel = async () => {
    try {
      guardarBlob(
        await descargarBlob(`/donaciones/mensual.xlsx?mes=${mes}`),
        `donaciones-${mes}.xlsx`,
      );
    } catch {
      avisar("No se pudo descargar el Excel.", "error");
    }
  };

  return (
    <Stack spacing={3}>
      <Stack
        direction={{ xs: "column", sm: "row" }}
        spacing={1.5}
        sx={{ alignItems: { sm: "center" } }}
      >
        <SelectorMes valor={mes} alCambiar={setMes} />
        <Box sx={{ flex: 1 }}>
          <Typography variant="body2" color="text.secondary">
            Donado en {mesLargo(mes).toLowerCase()}
          </Typography>
          <Typography sx={{ fontSize: "1.6rem", fontWeight: 600 }}>
            {resumen ? pesos(resumen.totales.monto) : "…"}
          </Typography>
        </Box>
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
      </Stack>
      <Box
        sx={{
          display: "grid",
          gap: 3,
          gridTemplateColumns: { xs: "1fr", md: "1fr 1fr" },
        }}
      >
        <Seccion titulo="Por organización" sinRelleno>
          <Totales
            concepto="Organización"
            filas={resumen?.por_organizacion ?? []}
          />
        </Seccion>
        <Seccion titulo="Por categoría" sinRelleno>
          <Totales concepto="Categoría" filas={resumen?.por_categoria ?? []} />
        </Seccion>
      </Box>

      <PantallaCrud<Donacion, typeof esquema>
        titulo="Donaciones"
        singular="Donación"
        femenino
        sinEncabezado
        recurso="donaciones"
        invalidar={["organizaciones"]}
        buscar="Buscar en el motivo"
        parametros={{ mes }}
        permisos={{
          crear: P.donaciones,
          editar: P.donaciones,
          eliminar: P.donaciones,
        }}
        esquema={esquema}
        nuevo={{
          organizacion_id: undefined,
          fecha: hoy,
          monto: undefined,
          descripcion: "",
        }}
        aFormulario={(d) => ({
          organizacion_id: d.organizacion_id,
          fecha: d.fecha,
          monto: d.monto,
          descripcion: d.descripcion ?? "",
        })}
        campos={[
          {
            nombre: "organizacion_id",
            etiqueta: "Organización",
            tipo: "seleccion",
            // Las inactivas solo se muestran en las donaciones que ya las tenían
            opciones: (organizaciones.data ?? []).map((o) => ({
              valor: o.id,
              etiqueta: o.activo ? o.nombre : `${o.nombre} (inactiva)`,
            })),
          },
          { nombre: "monto", etiqueta: "Monto", tipo: "pesos", ancho: "medio" },
          { nombre: "fecha", etiqueta: "Fecha", tipo: "fecha", ancho: "medio" },
          { nombre: "descripcion", etiqueta: "Motivo (opcional)" },
        ]}
        columnas={[
          { id: "fecha", titulo: "Fecha", render: (d) => fecha(d.fecha) },
          {
            id: "org",
            titulo: "Organización",
            render: (d) => d.organizacion?.nombre,
          },
          {
            id: "motivo",
            titulo: "Motivo",
            ocultarEnMovil: true,
            render: (d) => d.descripcion ?? "—",
          },
          {
            id: "monto",
            titulo: "Monto",
            alinear: "right",
            render: (d) => pesos(d.monto),
          },
        ]}
      />
      <VisorPdf
        abierto={verPdf}
        titulo={`Donaciones de ${mesLargo(mes).toLowerCase()}`}
        ruta={`/donaciones/mensual.pdf?mes=${mes}`}
        nombreArchivo={`donaciones-${mes}.pdf`}
        alCerrar={() => setVerPdf(false)}
      />
    </Stack>
  );
}
