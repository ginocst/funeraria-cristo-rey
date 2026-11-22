import { Alert, Box, Button, Link, TextField } from "@mui/material";
import { useState } from "react";
import {
  Link as RouterLink,
  useNavigate,
  useSearchParams,
} from "react-router-dom";
import { api, leerError, obtenerCsrf } from "../../api/cliente";
import { MarcoAcceso } from "./MarcoAcceso";

/** Solicitud del enlace de recuperación (válido 30 minutos). */
export function Recuperar() {
  const [email, setEmail] = useState("");
  const [estado, setEstado] = useState<"inicio" | "enviado">("inicio");
  const [error, setError] = useState<string | null>(null);

  const enviar = async (e: React.FormEvent) => {
    e.preventDefault();
    setError(null);
    try {
      await obtenerCsrf();
      await api.post("/auth/forgot-password", { email });
    } catch (err) {
      // Por seguridad no se revela si el correo existe; solo se muestran errores de conexión o de límite.
      const e2 = leerError(err);
      if (e2.estado !== 422) return setError(e2.mensaje);
    }
    setEstado("enviado");
  };

  return (
    <MarcoAcceso
      titulo="Recuperar contraseña"
      descripcion="Le enviaremos un enlace para crear una nueva contraseña."
    >
      {estado === "enviado" ? (
        <Alert severity="success">
          Si el correo pertenece a una cuenta activa, recibirá un enlace válido
          por 30 minutos.
        </Alert>
      ) : (
        <Box component="form" onSubmit={enviar} noValidate>
          {error && (
            <Alert severity="error" sx={{ mb: 2 }}>
              {error}
            </Alert>
          )}
          <TextField
            label="Correo"
            type="email"
            value={email}
            onChange={(e) => setEmail(e.target.value)}
            autoFocus
          />
          <Button
            type="submit"
            variant="contained"
            size="large"
            fullWidth
            sx={{ mt: 2.5 }}
            disabled={!email.includes("@")}
          >
            Enviar enlace
          </Button>
        </Box>
      )}
      <Link
        component={RouterLink}
        to="/ingresar"
        variant="body2"
        sx={{ display: "inline-block", mt: 3 }}
      >
        Volver a iniciar sesión
      </Link>
    </MarcoAcceso>
  );
}

/** Nueva contraseña desde el enlace del correo. */
export function Restablecer() {
  const [params] = useSearchParams();
  const navegar = useNavigate();
  const [password, setPassword] = useState("");
  const [confirmacion, setConfirmacion] = useState("");
  const [error, setError] = useState<string | null>(null);

  const enviar = async (e: React.FormEvent) => {
    e.preventDefault();
    setError(null);
    try {
      await obtenerCsrf();
      await api.post("/auth/reset-password", {
        token: params.get("token"),
        email: params.get("email"),
        password,
        password_confirmation: confirmacion,
      });
      navegar("/ingresar", { replace: true });
    } catch (err) {
      setError(leerError(err).mensaje);
    }
  };

  return (
    <MarcoAcceso
      titulo="Nueva contraseña"
      descripcion="Use al menos 12 caracteres, con letras y números. Evite contraseñas usadas en otros sitios."
    >
      <Box component="form" onSubmit={enviar} noValidate>
        {error && (
          <Alert severity="error" sx={{ mb: 2 }}>
            {error}
          </Alert>
        )}
        <TextField
          label="Nueva contraseña"
          type="password"
          autoComplete="new-password"
          value={password}
          onChange={(e) => setPassword(e.target.value)}
          sx={{ mb: 2 }}
        />
        <TextField
          label="Repita la contraseña"
          type="password"
          autoComplete="new-password"
          value={confirmacion}
          onChange={(e) => setConfirmacion(e.target.value)}
          error={!!confirmacion && confirmacion !== password}
          helperText={
            confirmacion && confirmacion !== password
              ? "Las contraseñas no coinciden"
              : " "
          }
        />
        <Button
          type="submit"
          variant="contained"
          size="large"
          fullWidth
          sx={{ mt: 1 }}
          disabled={password.length < 12 || password !== confirmacion}
        >
          Guardar contraseña
        </Button>
      </Box>
    </MarcoAcceso>
  );
}
