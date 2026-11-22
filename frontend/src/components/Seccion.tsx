import {
  Box,
  Paper,
  Stack,
  Typography,
  type SxProps,
  type Theme,
} from "@mui/material";
import type { ReactNode } from "react";

type Props = {
  titulo?: ReactNode;
  descripcion?: ReactNode;
  acciones?: ReactNode;
  children: ReactNode;
  sx?: SxProps<Theme>;
  sinRelleno?: boolean;
};

/** Bloque de contenido con título. Es la unidad visual de las pantallas */
export function Seccion({
  titulo,
  descripcion,
  acciones,
  children,
  sx,
  sinRelleno,
}: Props) {
  return (
    <Paper
      variant="outlined"
      sx={{ borderRadius: 2.5, overflow: "hidden", ...sx }}
    >
      {(titulo || acciones) && (
        <Stack
          direction="row"
          spacing={2}
          sx={{
            px: 2.5,
            pt: 2,
            pb: sinRelleno ? 1.5 : 0,
            alignItems: "flex-start",
            justifyContent: "space-between",
          }}
        >
          <Box sx={{ minWidth: 0 }}>
            {titulo && (
              <Typography variant="h3" component="h2">
                {titulo}
              </Typography>
            )}
            {descripcion && (
              <Typography
                variant="body2"
                color="text.secondary"
                sx={{ mt: 0.25 }}
              >
                {descripcion}
              </Typography>
            )}
          </Box>
          {acciones && (
            <Stack direction="row" spacing={1} sx={{ flexShrink: 0 }}>
              {acciones}
            </Stack>
          )}
        </Stack>
      )}
      <Box sx={{ p: sinRelleno ? 0 : 2.5 }}>{children}</Box>
    </Paper>
  );
}

/** Par etiqueta / valor para fichas de detalle */
export function Dato({
  etiqueta,
  children,
}: {
  etiqueta: string;
  children: ReactNode;
}) {
  return (
    <Box sx={{ minWidth: 0 }}>
      <Typography
        variant="body2"
        color="text.secondary"
        sx={{ fontSize: "0.78rem" }}
      >
        {etiqueta}
      </Typography>
      <Typography
        variant="body2"
        component="div"
        sx={{ fontWeight: 500, wordBreak: "break-word" }}
      >
        {children ?? "—"}
      </Typography>
    </Box>
  );
}
