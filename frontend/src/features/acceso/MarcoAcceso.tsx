import { Box, Typography } from "@mui/material";
import type { ReactNode } from "react";
import { colores } from "../../app/tema";

/** Marco de las pantallas de acceso: panel con el logotipo y formulario a la derecha */
export function MarcoAcceso({
  titulo,
  descripcion,
  children,
}: {
  titulo: string;
  descripcion?: string;
  children: ReactNode;
}) {
  return (
    <Box
      sx={{
        minHeight: "100vh",
        display: "grid",
        gridTemplateColumns: { xs: "1fr", md: "minmax(360px, 5fr) 7fr" },
      }}
    >
      <Box
        sx={{
          display: { xs: "none", md: "flex" },
          flexDirection: "column",
          justifyContent: "space-between",
          p: 6,
          color: "#fff",
          background: `radial-gradient(120% 90% at 0% 0%, ${colores.vinoOscuro} 0%, ${colores.vinoProfundo} 55%, #350C10 100%)`,
        }}
      >
        <Box
          component="img"
          src="/logo-marca-blanco.png"
          alt="Funeraria Cristo Rey"
          sx={{ width: 280, maxWidth: "80%" }}
        />
        <Box>
          <Typography
            sx={{
              fontFamily: '"Cormorant Garamond", serif',
              fontSize: "2rem",
              lineHeight: 1.2,
              maxWidth: 420,
            }}
          >
            Cada servicio, acompañado con orden y respeto.
          </Typography>
          <Typography
            sx={{ mt: 2, color: "rgba(255,255,255,0.7)", maxWidth: 400 }}
          >
            Sistema de gestión de servicios funerarios, inventario, flota y
            finanzas.
          </Typography>
        </Box>
      </Box>
      <Box
        sx={{
          display: "flex",
          alignItems: "center",
          justifyContent: "center",
          p: { xs: 3, sm: 6 },
          bgcolor: "background.paper",
        }}
      >
        <Box sx={{ width: "100%", maxWidth: 400 }}>
          <Box
            component="img"
            src="/logo-marca.png"
            alt="Funeraria Cristo Rey"
            sx={{ display: { md: "none" }, width: 200, mb: 4 }}
          />
          <Typography variant="h1" component="h1">
            {titulo}
          </Typography>
          {descripcion && (
            <Typography variant="subtitle1" sx={{ mt: 1, mb: 3 }}>
              {descripcion}
            </Typography>
          )}
          <Box sx={{ mt: descripcion ? 0 : 3 }}>{children}</Box>
        </Box>
      </Box>
    </Box>
  );
}
