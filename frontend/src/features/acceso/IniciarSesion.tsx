import { zodResolver } from "@hookform/resolvers/zod";
import {
  Alert,
  Box,
  Button,
  Checkbox,
  FormControlLabel,
  Link,
  Stack,
  TextField,
  Typography,
} from "@mui/material";
import { useState } from "react";
import { useForm } from "react-hook-form";
import { Link as RouterLink, Navigate, useLocation } from "react-router-dom";
import { z } from "zod";
import { leerError } from "../../api/cliente";
import { useSesion } from "../../auth/Sesion";
import { MarcoAcceso } from "./MarcoAcceso";

const esquema = z.object({
  email: z
    .string()
    .trim()
    .min(1, "Ingrese su correo")
    .email("Ingrese un correo válido"),
  password: z.string().min(1, "Ingrese su contraseña"),
  recordar: z.boolean(),
});

export default function IniciarSesion() {
  const { sesion, iniciar, verificar2fa } = useSesion();
  const destino =
    (useLocation().state as { desde?: string } | null)?.desde ?? "/panel";
  const [paso, setPaso] = useState<"credenciales" | "2fa">("credenciales");
  const [error, setError] = useState<string | null>(null);
  const [codigo, setCodigo] = useState("");
  const [recuperacion, setRecuperacion] = useState(false);
  const [enviando, setEnviando] = useState(false);
  const {
    register,
    handleSubmit,
    formState: { errors },
  } = useForm({
    resolver: zodResolver(esquema),
    defaultValues: { email: "", password: "", recordar: false },
  });

  if (sesion) return <Navigate to={destino} replace />;

  const entrar = handleSubmit(async (d) => {
    setError(null);
    setEnviando(true);
    try {
      const r = await iniciar(d.email, d.password, d.recordar);
      if (r.requiere2fa) setPaso("2fa");
    } catch (e) {
      const err = leerError(e);
      setError(
        err.estado === 422
          ? "El correo o la contraseña no son correctos."
          : err.mensaje,
      );
    } finally {
      setEnviando(false);
    }
  });

  const confirmar = async (e: React.FormEvent) => {
    e.preventDefault();
    setError(null);
    setEnviando(true);
    try {
      await verificar2fa(codigo.trim(), recuperacion);
    } catch (err) {
      setError(
        leerError(err).estado === 422
          ? "El código no es válido. Revise su aplicación de autenticación."
          : leerError(err).mensaje,
      );
    } finally {
      setEnviando(false);
    }
  };

  if (paso === "2fa") {
    return (
      <MarcoAcceso
        titulo="Verificación en dos pasos"
        descripcion={
          recuperacion
            ? "Ingrese uno de sus códigos de recuperación."
            : "Ingrese el código de 6 dígitos que muestra su aplicación de autenticación."
        }
      >
        <Box component="form" onSubmit={confirmar} noValidate>
          {error && (
            <Alert severity="error" sx={{ mb: 2 }}>
              {error}
            </Alert>
          )}
          <TextField
            autoFocus
            label={recuperacion ? "Código de recuperación" : "Código"}
            value={codigo}
            onChange={(e) => setCodigo(e.target.value)}
            slotProps={{
              htmlInput: recuperacion
                ? {}
                : {
                    inputMode: "numeric",
                    autoComplete: "one-time-code",
                    maxLength: 6,
                  },
            }}
          />
          <Button
            type="submit"
            variant="contained"
            size="large"
            fullWidth
            sx={{ mt: 2.5 }}
            disabled={enviando || codigo.trim().length < 6}
          >
            Verificar
          </Button>
          <Button
            fullWidth
            sx={{ mt: 1 }}
            onClick={() => {
              setRecuperacion(!recuperacion);
              setCodigo("");
            }}
          >
            {recuperacion
              ? "Usar el código de la aplicación"
              : "No tengo acceso a mi teléfono"}
          </Button>
        </Box>
      </MarcoAcceso>
    );
  }

  return (
    <MarcoAcceso
      titulo="Iniciar sesión"
      descripcion="Use la cuenta que le entregó la administración."
    >
      <Box component="form" onSubmit={entrar} noValidate>
        {error && (
          <Alert severity="error" sx={{ mb: 2 }}>
            {error}
          </Alert>
        )}
        <Stack spacing={2}>
          <TextField
            label="Correo"
            type="email"
            autoComplete="username"
            autoFocus
            {...register("email")}
            error={!!errors.email}
            helperText={errors.email?.message}
          />
          <TextField
            label="Contraseña"
            type="password"
            autoComplete="current-password"
            {...register("password")}
            error={!!errors.password}
            helperText={errors.password?.message}
          />
        </Stack>
        <Stack
          direction="row"
          sx={{ mt: 1, alignItems: "center", justifyContent: "space-between" }}
        >
          <FormControlLabel
            control={<Checkbox size="small" {...register("recordar")} />}
            label={
              <Typography variant="body2">
                Mantener la sesión en este equipo
              </Typography>
            }
          />
          <Link component={RouterLink} to="/recuperar" variant="body2">
            Olvidé mi contraseña
          </Link>
        </Stack>
        <Button
          type="submit"
          variant="contained"
          size="large"
          fullWidth
          sx={{ mt: 2.5 }}
          disabled={enviando}
        >
          {enviando ? "Ingresando…" : "Ingresar"}
        </Button>
      </Box>
    </MarcoAcceso>
  );
}
