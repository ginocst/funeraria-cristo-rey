import CloseRoundedIcon from "@mui/icons-material/CloseRounded";
import PictureAsPdfRoundedIcon from "@mui/icons-material/PictureAsPdfRounded";
import {
  Box,
  Button,
  Dialog,
  DialogContent,
  DialogTitle,
  IconButton,
  Stack,
  Typography,
} from "@mui/material";
import { keepPreviousData, useQuery } from "@tanstack/react-query";
import { useState } from "react";
import { api, type Pagina } from "../../api/cliente";
import type { Donacion, Organizacion } from "../../api/tipos";
import { Tabla } from "../../components/Tabla";
import { VisorPdf } from "../../components/VisorPdf";
import { ahoraLocal, fecha, mesLargo, pesos } from "../../lib/formato";
import { SelectorMes } from "./SelectorMes";

/** Todas las donaciones a una organización y el PDF de un mes específico */
export function Historial({
  organizacion,
  alCerrar,
}: {
  organizacion: Organizacion;
  alCerrar: () => void;
}) {
  const [pagina, setPagina] = useState(1);
  const [mes, setMes] = useState(ahoraLocal().format("YYYY-MM"));
  const [verPdf, setVerPdf] = useState(false);
  const { data, isFetching } = useQuery({
    queryKey: ["donaciones", "historial", organizacion.id, pagina],
    queryFn: async () =>
      (
        await api.get<Pagina<Donacion>>("/donaciones", {
          params: {
            organizacion_id: organizacion.id,
            page: pagina,
            por_pagina: 10,
          },
        })
      ).data,
    placeholderData: keepPreviousData,
  });

  return (
    <Dialog open onClose={alCerrar} fullWidth maxWidth="md">
      <DialogTitle
        component="div"
        sx={{ display: "flex", alignItems: "flex-start", gap: 2 }}
      >
        <Box sx={{ flex: 1 }}>
          <Typography variant="h3" component="h2">
            {organizacion.nombre}
          </Typography>
          <Typography variant="body2" color="text.secondary">
            {organizacion.categoria?.nombre} · {organizacion.donaciones_count}{" "}
            {organizacion.donaciones_count === 1 ? "donación" : "donaciones"} ·
            Total {pesos(organizacion.total_donado)}
          </Typography>
        </Box>
        <IconButton onClick={alCerrar} aria-label="Cerrar historial">
          <CloseRoundedIcon />
        </IconButton>
      </DialogTitle>
      <DialogContent>
        <Stack
          direction={{ xs: "column", sm: "row" }}
          spacing={1.5}
          sx={{ pt: 1, pb: 2, alignItems: { sm: "center" } }}
        >
          <SelectorMes valor={mes} alCambiar={setMes} />
          <Button
            variant="contained"
            startIcon={<PictureAsPdfRoundedIcon />}
            onClick={() => setVerPdf(true)}
          >
            PDF del mes
          </Button>
        </Stack>
        <Typography variant="subtitle2" sx={{ mb: 1 }}>
          Historial completo
        </Typography>
        <Tabla
          filas={data?.data}
          clave={(d) => d.id}
          cargando={isFetching}
          densa
          vacio="Todavía no hay donaciones a esta organización."
          columnas={[
            { id: "fecha", titulo: "Fecha", render: (d) => fecha(d.fecha) },
            {
              id: "motivo",
              titulo: "Motivo",
              render: (d) => d.descripcion ?? "—",
            },
            {
              id: "monto",
              titulo: "Monto",
              alinear: "right",
              render: (d) => pesos(d.monto),
            },
          ]}
          paginacion={
            data
              ? {
                  pagina,
                  total: data.total,
                  porPagina: data.per_page,
                  alCambiar: setPagina,
                }
              : undefined
          }
        />
      </DialogContent>
      <VisorPdf
        abierto={verPdf}
        titulo={`${organizacion.nombre}, ${mesLargo(mes).toLowerCase()}`}
        ruta={`/organizaciones/${organizacion.id}/donaciones.pdf?mes=${mes}`}
        nombreArchivo={`donaciones-${organizacion.id}-${mes}.pdf`}
        alCerrar={() => setVerPdf(false)}
      />
    </Dialog>
  );
}
