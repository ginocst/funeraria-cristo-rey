import AddRoundedIcon from "@mui/icons-material/AddRounded";
import ReceiptRoundedIcon from "@mui/icons-material/ReceiptRounded";
import {
  Autocomplete,
  Button,
  Chip,
  Dialog,
  DialogContent,
  DialogTitle,
  Stack,
  TextField,
  Typography,
} from "@mui/material";
import { keepPreviousData, useQuery } from "@tanstack/react-query";
import { useState } from "react";
import { useNavigate } from "react-router-dom";
import { api, type Pagina } from "../../api/cliente";
import type { FilaExpediente, FilaPago, Resumen } from "../../api/tipos";
import { colores } from "../../app/tema";
import { useSesion } from "../../auth/Sesion";
import { Encabezado } from "../../components/Encabezado";
import { Seccion } from "../../components/Seccion";
import { DosLineas, Tabla } from "../../components/Tabla";
import { VisorPdf } from "../../components/VisorPdf";
import { fechaHora, pesos } from "../../lib/formato";
import { RegistrarPago } from "./RegistrarPago";

/** Registro de todos los pagos con su comprobante. Para pagar, se elige el expediente */
export default function Pagos() {
  const { puede } = useSesion();
  const navegar = useNavigate();
  const [pagina, setPagina] = useState(1);
  const [desde, setDesde] = useState("");
  const [hasta, setHasta] = useState("");
  const [comprobante, setComprobante] = useState<FilaPago | null>(null);
  const [elegir, setElegir] = useState(false);
  const [busqueda, setBusqueda] = useState("");
  const [expediente, setExpediente] = useState<FilaExpediente | null>(null);

  const { data, isFetching } = useQuery({
    queryKey: ["pagos", { pagina, desde, hasta }],
    queryFn: async () =>
      (
        await api.get<Pagina<FilaPago>>("/pagos", {
          params: {
            page: pagina,
            desde: desde || undefined,
            hasta: hasta || undefined,
          },
        })
      ).data,
    placeholderData: keepPreviousData,
  });
  const conSaldo = useQuery({
    queryKey: ["expedientes", "con-saldo", busqueda],
    queryFn: async () =>
      (
        await api.get<Pagina<FilaExpediente>>("/expedientes", {
          params: { con_saldo: 1, q: busqueda || undefined, por_pagina: 20 },
        })
      ).data.data,
    enabled: elegir,
  });

  return (
    <>
      <Encabezado
        titulo="Pagos"
        descripcion="Cada pago emite un comprobante con folio. Los pagos no se modifican: un error se corrige anulándolo desde el expediente."
        acciones={
          puede("pagos.registrar") && (
            <Button
              variant="contained"
              startIcon={<AddRoundedIcon />}
              onClick={() => setElegir(true)}
            >
              Registrar pago
            </Button>
          )
        }
      />
      <Seccion sinRelleno>
        <Stack
          direction="row"
          spacing={1.5}
          sx={{ p: 2, flexWrap: "wrap", gap: 1.5 }}
        >
          <TextField
            type="date"
            label="Desde"
            value={desde}
            onChange={(e) => {
              setDesde(e.target.value);
              setPagina(1);
            }}
            slotProps={{ inputLabel: { shrink: true } }}
            sx={{ width: 180 }}
          />
          <TextField
            type="date"
            label="Hasta"
            value={hasta}
            onChange={(e) => {
              setHasta(e.target.value);
              setPagina(1);
            }}
            slotProps={{ inputLabel: { shrink: true } }}
            sx={{ width: 180 }}
          />
        </Stack>
        <Tabla
          filas={data?.data}
          clave={(p) => p.id}
          cargando={isFetching}
          alClic={(p) =>
            navegar(`/expedientes/${p.expediente_id}?pestana=pagos`)
          }
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
          columnas={[
            { id: "fecha", titulo: "Fecha", render: (p) => fechaHora(p.fecha) },
            {
              id: "exp",
              titulo: "Expediente",
              render: (p) => (
                <DosLineas principal={p.codigo} secundaria={p.cliente} />
              ),
            },
            {
              id: "medio",
              titulo: "Medio",
              ocultarEnMovil: true,
              render: (p) => (
                <DosLineas
                  principal={p.medio}
                  secundaria={p.referencia ?? undefined}
                />
              ),
            },
            {
              id: "tipo",
              titulo: "Tipo",
              render: (p) =>
                p.tipo === "anulacion" ? (
                  <Chip
                    size="small"
                    color="warning"
                    variant="outlined"
                    label="Anulación"
                    title={p.motivo_anulacion ?? ""}
                  />
                ) : (
                  "Abono"
                ),
            },
            {
              id: "monto",
              titulo: "Monto",
              alinear: "right",
              render: (p) => (
                <Typography
                  variant="body2"
                  sx={{
                    fontWeight: 600,
                    color: p.monto < 0 ? colores.critico : undefined,
                  }}
                >
                  {pesos(p.monto)}
                </Typography>
              ),
            },
            {
              id: "usuario",
              titulo: "Registró",
              ocultarEnMovil: true,
              render: (p) => p.usuario ?? "—",
            },
            {
              id: "folio",
              titulo: "Comprobante",
              render: (p) =>
                p.comprobante_id ? (
                  <Button
                    size="small"
                    startIcon={<ReceiptRoundedIcon />}
                    onClick={(e) => {
                      e.stopPropagation();
                      setComprobante(p);
                    }}
                  >
                    {p.folio}
                  </Button>
                ) : (
                  "—"
                ),
            },
          ]}
        />
      </Seccion>

      <Dialog
        open={elegir && !expediente}
        onClose={() => setElegir(false)}
        fullWidth
        maxWidth="sm"
      >
        <DialogTitle>¿Qué expediente se paga?</DialogTitle>
        <DialogContent>
          <Autocomplete
            sx={{ mt: 1 }}
            options={conSaldo.data ?? []}
            filterOptions={(x) => x}
            onInputChange={(_, v) => setBusqueda(v)}
            getOptionLabel={(e) =>
              `${e.codigo}, ${e.difunto} (saldo ${pesos(e.saldo)})`
            }
            onChange={(_, e) => setExpediente(e)}
            noOptionsText="No hay expedientes con saldo pendiente que coincidan."
            renderInput={(p) => (
              <TextField
                {...p}
                autoFocus
                label="Buscar por código, fallecido o contratante"
              />
            )}
          />
        </DialogContent>
      </Dialog>
      {expediente && (
        <RegistrarPago
          abierto
          expedienteId={expediente.id}
          codigo={expediente.codigo}
          resumen={
            {
              total: Number(expediente.total),
              pagado: Number(expediente.pagado),
              saldo: Number(expediente.saldo),
            } as Resumen
          }
          alCerrar={() => {
            setExpediente(null);
            setElegir(false);
          }}
        />
      )}
      <VisorPdf
        abierto={!!comprobante}
        titulo={`Comprobante ${comprobante?.folio ?? ""}`}
        ruta={
          comprobante ? `/comprobantes/${comprobante.comprobante_id}/pdf` : null
        }
        nombreArchivo={`comprobante-${comprobante?.folio}.pdf`}
        alCerrar={() => setComprobante(null)}
      />
    </>
  );
}
