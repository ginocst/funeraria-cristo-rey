import { Alert, Snackbar } from "@mui/material";
import {
  createContext,
  useCallback,
  useContext,
  useState,
  type ReactNode,
} from "react";

type Tipo = "success" | "error" | "info" | "warning";
type Mensaje = { texto: string; tipo: Tipo; clave: number };

const Contexto = createContext<(texto: string, tipo?: Tipo) => void>(() => {});

/** Mensajes breves de confirmación o error («Pago registrado», «No se pudo guardar…») */
export function ProveedorNotificaciones({ children }: { children: ReactNode }) {
  const [mensaje, setMensaje] = useState<Mensaje | null>(null);
  const avisar = useCallback(
    (texto: string, tipo: Tipo = "success") =>
      setMensaje({ texto, tipo, clave: Date.now() }),
    [],
  );

  return (
    <Contexto.Provider value={avisar}>
      {children}
      <Snackbar
        key={mensaje?.clave}
        open={!!mensaje}
        autoHideDuration={mensaje?.tipo === "error" ? 8000 : 4000}
        onClose={(_, razon) => razon !== "clickaway" && setMensaje(null)}
        anchorOrigin={{ vertical: "bottom", horizontal: "center" }}
      >
        <Alert
          severity={mensaje?.tipo ?? "info"}
          variant="filled"
          onClose={() => setMensaje(null)}
          sx={{ minWidth: 320 }}
        >
          {mensaje?.texto}
        </Alert>
      </Snackbar>
    </Contexto.Provider>
  );
}

export const useAvisar = () => useContext(Contexto);
