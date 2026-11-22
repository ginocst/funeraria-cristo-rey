import {
  Alert,
  Box,
  Button,
  Paper,
  Stack,
  TextField,
  Typography,
} from "@mui/material";
import { useState } from "react";
import { api, leerError } from "../../api/cliente";
import { useSesion } from "../../auth/Sesion";
import { MarcoAcceso } from "./MarcoAcceso";

/**
 * Configuración obligatoria del TOTP para Administrador y Coordinador:
 * 1) se genera el secreto, 2) se escanea el QR, 3) se confirma con un código,
 * 4) se muestran los códigos de recuperación una sola vez
 */
export default function ConfigurarMfa() {
  const { sesion, recargar, cerrar } = useSesion();
  const [qr, setQr] = useState<string | null>(null);
  const [codigo, setCodigo] = useState("");
  const [codigos, setCodigos] = useState<string[] | null>(null);
  const [error, setError] = useState<string | null>(null);

  const comenzar = async () => {
    setError(null);
    try {
      await api.post("/auth/user/two-factor-authentication");
      setQr(
        (await api.get<{ svg: string }>("/auth/user/two-factor-qr-code")).data
          .svg,
      );
    } catch (e) {
      setError(leerError(e).mensaje);
    }
  };

  const confirmar = async (e: React.FormEvent) => {
    e.preventDefault();
    setError(null);
    try {
      await api.post("/auth/user/confirmed-two-factor-authentication", {
        code: codigo.trim(),
      });
      setCodigos(
        (await api.get<string[]>("/auth/user/two-factor-recovery-codes")).data,
      );
    } catch (err) {
      setError(
        leerError(err).estado === 422
          ? "El código no coincide. Verifique la hora de su teléfono e intente con el siguiente código."
          : leerError(err).mensaje,
      );
    }
  };

  return (
    <MarcoAcceso
      titulo="Proteja su cuenta"
      descripcion={`${sesion?.nombre ?? ""}, su perfil de ${sesion?.rol?.toLowerCase() ?? ""} requiere verificación en dos pasos. Necesita una aplicación como Google Authenticator o Microsoft Authenticator.`}
    >
      {error && (
        <Alert severity="error" sx={{ mb: 2 }}>
          {error}
        </Alert>
      )}
      {codigos ? (
        <>
          <Alert severity="success" sx={{ mb: 2 }}>
            Verificación activada.
          </Alert>
          <Typography variant="body2" sx={{ mb: 1 }}>
            Guarde estos códigos de recuperación en un lugar seguro. Cada uno
            sirve una vez si pierde su teléfono
          </Typography>
          <Paper
            variant="outlined"
            sx={{
              p: 2,
              fontFamily: "ui-monospace, monospace",
              display: "grid",
              gridTemplateColumns: "1fr 1fr",
              gap: 1,
              mb: 2,
            }}
          >
            {codigos.map((c) => (
              <span key={c}>{c}</span>
            ))}
          </Paper>
          <Button
            variant="contained"
            fullWidth
            size="large"
            onClick={() => recargar()}
          >
            Continuar
          </Button>
        </>
      ) : qr ? (
        <Box component="form" onSubmit={confirmar}>
          <Typography variant="body2" sx={{ mb: 2 }}>
            Escanee el código con su aplicación y escriba el código de 6 dígitos
            que aparece
          </Typography>
          <Box
            sx={{
              p: 2,
              bgcolor: "#fff",
              border: 1,
              borderColor: "divider",
              borderRadius: 2,
              width: "fit-content",
              mx: "auto",
              mb: 2,
              "& svg": { display: "block" },
            }}
            // eslint-disable-next-line no-restricted-syntax -- el SVG del QR lo genera el servidor; no contiene texto escrito por personas
            dangerouslySetInnerHTML={{ __html: qr }}
          />
          <TextField
            label="Código de 6 dígitos"
            value={codigo}
            onChange={(e) => setCodigo(e.target.value)}
            slotProps={{
              htmlInput: {
                inputMode: "numeric",
                maxLength: 6,
                autoComplete: "one-time-code",
              },
            }}
          />
          <Button
            type="submit"
            variant="contained"
            size="large"
            fullWidth
            sx={{ mt: 2 }}
            disabled={codigo.trim().length !== 6}
          >
            Activar
          </Button>
        </Box>
      ) : (
        <Stack spacing={1.5}>
          <Button variant="contained" size="large" onClick={comenzar}>
            Configurar ahora
          </Button>
          <Button color="inherit" onClick={() => cerrar()}>
            Cerrar sesión
          </Button>
        </Stack>
      )}
    </MarcoAcceso>
  );
}
