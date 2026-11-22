import {
  Button,
  Dialog,
  DialogActions,
  DialogContent,
  DialogContentText,
  DialogTitle,
  TextField,
} from "@mui/material";
import { useState, type ReactNode } from "react";

type Props = {
  abierto: boolean;
  titulo: string;
  mensaje: ReactNode;
  textoConfirmar: string;
  peligro?: boolean;
  pedirMotivo?: { etiqueta: string; minimo?: number };
  cargando?: boolean;
  alCerrar: () => void;
  alConfirmar: (motivo: string) => void;
};

/** Confirmación de acciones importantes. Si la acción exige motivo, lo pide antes de habilitar el botón */
export function Confirmar({
  abierto,
  titulo,
  mensaje,
  textoConfirmar,
  peligro,
  pedirMotivo,
  cargando,
  alCerrar,
  alConfirmar,
}: Props) {
  const [motivo, setMotivo] = useState("");
  const minimo = pedirMotivo?.minimo ?? 5;
  const valido = !pedirMotivo || motivo.trim().length >= minimo;

  return (
    <Dialog
      open={abierto}
      onClose={alCerrar}
      maxWidth="xs"
      fullWidth
      slotProps={{ transition: { onExited: () => setMotivo("") } }}
    >
      <DialogTitle>{titulo}</DialogTitle>
      <DialogContent>
        <DialogContentText component="div">{mensaje}</DialogContentText>
        {pedirMotivo && (
          <TextField
            autoFocus
            multiline
            minRows={3}
            label={pedirMotivo.etiqueta}
            value={motivo}
            onChange={(e) => setMotivo(e.target.value)}
            helperText={`Mínimo ${minimo} caracteres. Quedará en la bitácora.`}
            sx={{ mt: 2 }}
          />
        )}
      </DialogContent>
      <DialogActions sx={{ px: 3, pb: 2.5 }}>
        <Button onClick={alCerrar} color="inherit">
          Volver
        </Button>
        <Button
          variant="contained"
          color={peligro ? "error" : "primary"}
          disabled={!valido || cargando}
          onClick={() => alConfirmar(motivo.trim())}
        >
          {textoConfirmar}
        </Button>
      </DialogActions>
    </Dialog>
  );
}
