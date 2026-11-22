import {
  Alert,
  Button,
  Dialog,
  DialogActions,
  DialogContent,
  DialogTitle,
  InputAdornment,
  MenuItem,
  Stack,
  TextField,
  Typography,
} from "@mui/material";
import { useQuery, useQueryClient } from "@tanstack/react-query";
import { useEffect, useMemo, useState } from "react";
import { api, leerError, nuevaClave, type Pagina } from "../../api/cliente";
import type { Cuota, MedioPago, Pago, Resumen } from "../../api/tipos";
import { useAvisar } from "../../components/Notificador";
import { fecha, pesos } from "../../lib/formato";

type Props = {
  abierto: boolean;
  expedienteId: number;
  codigo: string;
  resumen: Resumen;
  cuotas?: Cuota[];
  alCerrar: () => void;
  alRegistrar?: (pago: Pago) => void;
};

/**
 * Registro de un abono. Cada intento lleva una clave de idempotencia:
 * si la red falla y el usuario reintenta, el pago no se duplica
 */
export function RegistrarPago({
  abierto,
  expedienteId,
  codigo,
  resumen,
  cuotas = [],
  alCerrar,
  alRegistrar,
}: Props) {
  const qc = useQueryClient();
  const avisar = useAvisar();
  const [monto, setMonto] = useState<number | "">("");
  const [medio, setMedio] = useState<number | "">("");
  const [cuota, setCuota] = useState<number | "">("");
  const [referencia, setReferencia] = useState("");
  const [error, setError] = useState<string | null>(null);
  const [enviando, setEnviando] = useState(false);
  const [clave, setClave] = useState(nuevaClave());

  const medios = useQuery({
    queryKey: ["medios-pago"],
    queryFn: async () =>
      (
        await api.get<Pagina<MedioPago>>("/medios-pago", {
          params: { por_pagina: 50, activo: 1 },
        })
      ).data.data,
  });
  const medioSel = useMemo(
    () => medios.data?.find((m) => m.id === medio),
    [medios.data, medio],
  );
  const pendientes = cuotas.filter((c) => c.estado !== "pagada");

  useEffect(() => {
    if (abierto) {
      setMonto("");
      setMedio("");
      setCuota("");
      setReferencia("");
      setError(null);
      setClave(nuevaClave());
    }
  }, [abierto]);

  const elegirCuota = (id: number | "") => {
    setCuota(id);
    const c = pendientes.find((x) => x.id === id);
    if (c) setMonto(c.monto - c.pagado);
  };

  const registrar = async () => {
    setError(null);
    setEnviando(true);
    try {
      const r = await api.post<{ pago: Pago }>(
        "/pagos",
        {
          expediente_id: expedienteId,
          monto,
          medio_pago_id: medio,
          cuota_id: cuota || null,
          referencia: referencia || null,
        },
        { headers: { "Idempotency-Key": clave } },
      );
      await Promise.all(
        [
          "finanzas",
          "expediente",
          "expedientes",
          "solicitud",
          "solicitudes",
          "pagos",
          "panel",
          "cobranza",
        ].map((k) => qc.invalidateQueries({ queryKey: [k] })),
      );
      avisar(
        `Pago registrado. Comprobante ${r.data.pago.comprobante?.folio ?? ""}`,
      );
      alRegistrar?.(r.data.pago);
      alCerrar();
    } catch (e) {
      setError(leerError(e).mensaje);
    } finally {
      setEnviando(false);
    }
  };

  const valido =
    typeof monto === "number" &&
    monto > 0 &&
    monto <= resumen.saldo &&
    !!medio &&
    (!medioSel?.requiere_referencia || referencia.trim().length > 0);

  return (
    <Dialog
      open={abierto}
      onClose={enviando ? undefined : alCerrar}
      fullWidth
      maxWidth="xs"
    >
      <DialogTitle>Registrar pago</DialogTitle>
      <DialogContent>
        <Typography variant="body2" color="text.secondary" sx={{ mb: 2 }}>
          Expediente {codigo}. Saldo pendiente:{" "}
          <strong>{pesos(resumen.saldo)}</strong>.
        </Typography>
        {resumen.saldo <= 0 && (
          <Alert severity="info" sx={{ mb: 2 }}>
            Este expediente no tiene saldo por pagar.
          </Alert>
        )}
        {error && (
          <Alert severity="error" sx={{ mb: 2 }}>
            {error}
          </Alert>
        )}
        <Stack spacing={2}>
          {pendientes.length > 0 && (
            <TextField
              select
              label="Cuota (opcional)"
              value={cuota}
              onChange={(e) =>
                elegirCuota(e.target.value === "" ? "" : Number(e.target.value))
              }
            >
              <MenuItem value="">Abono sin cuota</MenuItem>
              {pendientes.map((c) => (
                <MenuItem key={c.id} value={c.id}>
                  Cuota {c.numero}, vence {fecha(c.vencimiento)},{" "}
                  {pesos(c.monto - c.pagado)}
                </MenuItem>
              ))}
            </TextField>
          )}
          <TextField
            label="Monto"
            type="number"
            value={monto}
            onChange={(e) =>
              setMonto(e.target.value === "" ? "" : Number(e.target.value))
            }
            error={typeof monto === "number" && monto > resumen.saldo}
            helperText={
              typeof monto === "number" && monto > resumen.saldo
                ? "Supera el saldo pendiente"
                : " "
            }
            slotProps={{
              input: {
                startAdornment: (
                  <InputAdornment position="start">$</InputAdornment>
                ),
              },
            }}
          />
          <TextField
            select
            label="Medio de pago"
            value={medio}
            onChange={(e) => setMedio(Number(e.target.value))}
          >
            {medios.data
              ?.filter((m) => m.activo)
              .map((m) => (
                <MenuItem key={m.id} value={m.id}>
                  {m.nombre}
                </MenuItem>
              ))}
          </TextField>
          {medioSel?.requiere_referencia && (
            <TextField
              label="Referencia"
              value={referencia}
              onChange={(e) => setReferencia(e.target.value)}
              helperText="Número de operación, voucher o cheque"
            />
          )}
        </Stack>
      </DialogContent>
      <DialogActions sx={{ px: 3, pb: 2.5 }}>
        <Button color="inherit" onClick={alCerrar} disabled={enviando}>
          Cancelar
        </Button>
        <Button
          variant="contained"
          onClick={registrar}
          disabled={!valido || enviando}
        >
          {enviando ? "Registrando…" : "Registrar pago"}
        </Button>
      </DialogActions>
    </Dialog>
  );
}
