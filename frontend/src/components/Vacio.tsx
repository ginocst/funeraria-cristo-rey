import { Box, Typography } from "@mui/material";
import type { ReactNode } from "react";

export function Vacio({
  titulo,
  texto,
  accion,
  icono,
}: {
  titulo: string;
  texto?: string;
  accion?: ReactNode;
  icono?: ReactNode;
}) {
  return (
    <Box sx={{ textAlign: "center", py: 5, px: 2, color: "text.secondary" }}>
      {icono && (
        <Box sx={{ mb: 1, "& svg": { fontSize: 36, color: "primary.light" } }}>
          {icono}
        </Box>
      )}
      <Typography variant="h4" color="text.primary">
        {titulo}
      </Typography>
      {texto && (
        <Typography variant="body2" sx={{ mt: 0.5, maxWidth: 420, mx: "auto" }}>
          {texto}
        </Typography>
      )}
      {accion && <Box sx={{ mt: 2 }}>{accion}</Box>}
    </Box>
  );
}
