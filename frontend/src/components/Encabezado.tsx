import ArrowBackRoundedIcon from "@mui/icons-material/ArrowBackRounded";
import { Box, Button, Stack, Typography } from "@mui/material";
import type { ReactNode } from "react";
import { Link as RouterLink } from "react-router-dom";

type Props = {
  titulo: ReactNode;
  descripcion?: ReactNode;
  acciones?: ReactNode;
  volver?: { a: string; texto: string };
  extra?: ReactNode;
};

/** Encabezado de página: título en la tipografía del logo, descripción breve y acciones a la derecha */
export function Encabezado({
  titulo,
  descripcion,
  acciones,
  volver,
  extra,
}: Props) {
  return (
    <Box component="header" sx={{ mb: 3 }}>
      {volver && (
        <Button
          component={RouterLink}
          to={volver.a}
          size="small"
          startIcon={<ArrowBackRoundedIcon />}
          sx={{ mb: 1, ml: -1, color: "text.secondary" }}
        >
          {volver.texto}
        </Button>
      )}
      <Stack
        direction={{ xs: "column", md: "row" }}
        spacing={2}
        sx={{ alignItems: { md: "flex-end" }, justifyContent: "space-between" }}
      >
        <Box sx={{ minWidth: 0 }}>
          <Typography variant="h1" component="h1">
            {titulo}
          </Typography>
          {descripcion && (
            <Typography variant="subtitle1" sx={{ mt: 0.75, maxWidth: 720 }}>
              {descripcion}
            </Typography>
          )}
          {extra}
        </Box>
        {acciones && (
          <Stack
            direction="row"
            spacing={1}
            sx={{ flexShrink: 0, flexWrap: "wrap", gap: 1 }}
          >
            {acciones}
          </Stack>
        )}
      </Stack>
    </Box>
  );
}
