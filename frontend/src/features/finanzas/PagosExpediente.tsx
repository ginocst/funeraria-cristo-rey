import AddRoundedIcon from "@mui/icons-material/AddRounded";
import EventRepeatRoundedIcon from "@mui/icons-material/EventRepeatRounded";
import ReceiptRoundedIcon from "@mui/icons-material/ReceiptRounded";
import UndoRoundedIcon from "@mui/icons-material/UndoRounded";
import {
  Box,
  Button,
  Chip,
  IconButton,
  Skeleton,
  Stack,
  Tooltip,
  Typography,
} from "@mui/material";
import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { useState } from "react";
import { z } from "zod";
import { api, leerError } from "../../api/cliente";
import type { Finanzas } from "../../api/tipos";
import { colores } from "../../app/tema";
import { useSesion } from "../../auth/Sesion";
import { Confirmar } from "../../components/Confirmar";
import { EstadoCuotaChip } from "../../components/Estados";
import { FormularioDialogo } from "../../components/Formulario";
import { useAvisar } from "../../components/Notificador";
import { Seccion } from "../../components/Seccion";
import { Tabla } from "../../components/Tabla";
import { VisorPdf } from "../../components/VisorPdf";
import { ahoraLocal, fecha, fechaHora, pesos } from "../../lib/formato";
import { RegistrarPago } from "./RegistrarPago";

const esquemaCuotas = z.object({
  numero_cuotas: z
    .number({ message: "Indique el número de cuotas" })
    .int()
    .min(1)
    .max(24),
  primer_vencimiento: z.string().min(1, "Indique la fecha"),
});

/** Pagos, comprobantes y cuotas de un expediente. Los pagos no se editan: se anulan con un movimiento compensatorio */
export function PagosExpediente({
  expedienteId,
  codigo,
}: {
  expedienteId: number;
  codigo: string;
}) {
  const { puede } = useSesion();
  const qc = useQueryClient();
  const avisar = useAvisar();
  const [registrar, setRegistrar] = useState(false);
  const [pactar, setPactar] = useState(false);
  const [anular, setAnular] = useState<number | null>(null);
  const [comprobante, setComprobante] = useState<{
    id: number;
    folio: string;
  } | null>(null);

  const { data, isLoading } = useQuery({
    queryKey: ["finanzas", expedienteId],
    queryFn: async () =>
      (await api.get<Finanzas>(`/expedientes/${expedienteId}/finanzas`)).data,
  });

  const anulacion = useMutation({
    mutationFn: async ({ id, motivo }: { id: number; motivo: string }) =>
      api.post(`/pagos/${id}/anular`, { motivo }),
    onSuccess: async () => {
      setAnular(null);
      await Promise.all(
        ["finanzas", "expediente", "pagos", "panel"].map((k) =>
          qc.invalidateQueries({ queryKey: [k] }),
        ),
      );
      avisar("Pago anulado con un movimiento compensatorio");
    },
    onError: (e) => {
      setAnular(null);
      avisar(leerError(e).mensaje, "error");
    },
  });

  if (isLoading || !data) return <Skeleton height={300} />;
  const r = data.resumen;
  const pct = r.total
    ? Math.min(100, Math.round((r.pagado / r.total) * 100))
    : 0;

  return (
    <Stack spacing={3}>
      <Box
        sx={{
          display: "grid",
          gap: 3,
          gridTemplateColumns: { xs: "1fr", md: "1fr 1fr 1fr" },
        }}
      >
        {[
          { t: "Total del servicio", v: pesos(r.total) },
          { t: "Pagado", v: `${pesos(r.pagado)}`, d: `${pct} % del total` },
          {
            t: "Saldo pendiente",
            v: pesos(r.saldo),
            d: r.abono_minimo
              ? `Abono mínimo (${r.abono_minimo_pct} %): ${pesos(r.abono_minimo)}`
              : undefined,
            destacado: r.saldo > 0,
          },
        ].map((x) => (
          <Box
            key={x.t}
            sx={{
              p: 2.5,
              borderRadius: 2.5,
              border: `1px solid ${x.destacado ? colores.rosaCeniza : colores.linea}`,
              bgcolor: x.destacado ? colores.vinoTenue : "background.paper",
            }}
          >
            <Typography variant="body2" color="text.secondary">
              {x.t}
            </Typography>
            <Typography sx={{ fontSize: "1.6rem", fontWeight: 600 }}>
              {x.v}
            </Typography>
            {x.d && (
              <Typography variant="caption" color="text.secondary">
                {x.d}
              </Typography>
            )}
          </Box>
        ))}
      </Box>

      <Seccion
        titulo="Pagos y comprobantes"
        sinRelleno
        acciones={
          puede("pagos.registrar") && (
            <Button
              variant="contained"
              size="small"
              startIcon={<AddRoundedIcon />}
              disabled={r.saldo <= 0}
              onClick={() => setRegistrar(true)}
            >
              Registrar pago
            </Button>
          )
        }
      >
        <Tabla
          filas={data.pagos}
          clave={(p) => p.id}
          vacio="Aún no hay pagos registrados."
          columnas={[
            { id: "fecha", titulo: "Fecha", render: (p) => fechaHora(p.fecha) },
            {
              id: "medio",
              titulo: "Medio",
              render: (p) => (
                <>
                  {p.medio_pago?.nombre}
                  {p.referencia ? (
                    <Typography
                      variant="caption"
                      color="text.secondary"
                      component="div"
                    >
                      {p.referencia}
                    </Typography>
                  ) : null}
                </>
              ),
            },
            {
              id: "tipo",
              titulo: "Tipo",
              render: (p) =>
                p.tipo === "anulacion" ? (
                  <Tooltip title={p.motivo_anulacion ?? ""}>
                    <Chip
                      size="small"
                      label="Anulación"
                      color="warning"
                      variant="outlined"
                    />
                  </Tooltip>
                ) : p.anulado ? (
                  <Chip size="small" label="Anulado" variant="outlined" />
                ) : p.cuota_id ? (
                  "Cuota"
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
                    textDecoration: p.anulado ? "line-through" : undefined,
                  }}
                >
                  {pesos(p.monto)}
                </Typography>
              ),
            },
            {
              id: "folio",
              titulo: "Comprobante",
              render: (p) =>
                p.comprobante ? (
                  <Button
                    size="small"
                    startIcon={<ReceiptRoundedIcon />}
                    onClick={() =>
                      setComprobante({
                        id: p.comprobante!.id,
                        folio: p.comprobante!.folio,
                      })
                    }
                  >
                    {p.comprobante.folio}
                  </Button>
                ) : (
                  "—"
                ),
            },
            {
              id: "acc",
              titulo: "",
              alinear: "right",
              render: (p) =>
                p.tipo === "abono" && !p.anulado && puede("pagos.anular") ? (
                  <Tooltip title="Anular pago">
                    <IconButton
                      size="small"
                      onClick={() => setAnular(p.id)}
                      aria-label="Anular pago"
                    >
                      <UndoRoundedIcon fontSize="small" />
                    </IconButton>
                  </Tooltip>
                ) : null,
            },
          ]}
        />
      </Seccion>

      <Seccion
        titulo="Cuotas"
        sinRelleno
        descripcion={
          data.cuotas.length
            ? undefined
            : "El saldo puede pactarse en cuotas mensuales."
        }
        acciones={
          puede("pagos.registrar") &&
          r.saldo > 0 && (
            <Button
              size="small"
              variant="outlined"
              startIcon={<EventRepeatRoundedIcon />}
              onClick={() => setPactar(true)}
            >
              {data.cuotas.length ? "Volver a pactar" : "Pactar cuotas"}
            </Button>
          )
        }
      >
        {data.cuotas.length > 0 && (
          <Tabla
            filas={data.cuotas}
            clave={(c) => c.id}
            columnas={[
              { id: "n", titulo: "Cuota", render: (c) => `N.º ${c.numero}` },
              {
                id: "v",
                titulo: "Vencimiento",
                render: (c) => fecha(c.vencimiento),
              },
              {
                id: "m",
                titulo: "Monto",
                alinear: "right",
                render: (c) => pesos(c.monto),
              },
              {
                id: "p",
                titulo: "Pagado",
                alinear: "right",
                render: (c) => pesos(c.pagado),
              },
              {
                id: "e",
                titulo: "Estado",
                render: (c) => <EstadoCuotaChip estado={c.estado} />,
              },
            ]}
          />
        )}
      </Seccion>

      <RegistrarPago
        abierto={registrar}
        expedienteId={expedienteId}
        codigo={codigo}
        resumen={r}
        cuotas={data.cuotas}
        alCerrar={() => setRegistrar(false)}
      />
      <FormularioDialogo
        abierto={pactar}
        titulo="Pactar cuotas"
        descripcion={`El saldo de ${pesos(r.saldo)} se divide en cuotas mensuales iguales; la diferencia por redondeo queda en la última.`}
        esquema={esquemaCuotas}
        campos={[
          {
            nombre: "numero_cuotas",
            etiqueta: "Número de cuotas",
            tipo: "numero",
            ancho: "medio",
          },
          {
            nombre: "primer_vencimiento",
            etiqueta: "Primer vencimiento",
            tipo: "fecha",
            ancho: "medio",
          },
        ]}
        valoresIniciales={{
          numero_cuotas: 3,
          primer_vencimiento: ahoraLocal().add(1, "month").format("YYYY-MM-DD"),
        }}
        textoGuardar="Pactar"
        alCerrar={() => setPactar(false)}
        alGuardar={async (d) => {
          await api.post(`/expedientes/${expedienteId}/cuotas`, d);
          await qc.invalidateQueries({ queryKey: ["finanzas", expedienteId] });
          avisar("Cuotas pactadas");
          setPactar(false);
        }}
      />
      <Confirmar
        abierto={anular !== null}
        titulo="Anular pago"
        mensaje="El pago original no se borra: se registra un movimiento negativo que lo compensa y se emite su comprobante. El saldo vuelve a aumentar."
        textoConfirmar="Anular pago"
        peligro
        pedirMotivo={{ etiqueta: "Motivo de la anulación" }}
        cargando={anulacion.isPending}
        alCerrar={() => setAnular(null)}
        alConfirmar={(motivo) =>
          anular && anulacion.mutate({ id: anular, motivo })
        }
      />
      <VisorPdf
        abierto={!!comprobante}
        titulo={`Comprobante ${comprobante?.folio ?? ""}`}
        ruta={comprobante ? `/comprobantes/${comprobante.id}/pdf` : null}
        nombreArchivo={`comprobante-${comprobante?.folio}.pdf`}
        alCerrar={() => setComprobante(null)}
      />
    </Stack>
  );
}
