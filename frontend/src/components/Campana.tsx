import CheckCircleRoundedIcon from "@mui/icons-material/CheckCircleRounded";
import ErrorRoundedIcon from "@mui/icons-material/ErrorRounded";
import InfoRoundedIcon from "@mui/icons-material/InfoRounded";
import NotificationsNoneRoundedIcon from "@mui/icons-material/NotificationsNoneRounded";
import WarningRoundedIcon from "@mui/icons-material/WarningRounded";
import {
  Badge,
  Box,
  Button,
  Divider,
  IconButton,
  List,
  ListItemButton,
  Popover,
  Stack,
  Tooltip,
  Typography,
} from "@mui/material";
import { useQuery, useQueryClient } from "@tanstack/react-query";
import { useEffect, useState } from "react";
import { useNavigate } from "react-router-dom";
import { api, BASE_API } from "../api/cliente";
import type { Aviso } from "../api/tipos";
import { colores } from "../app/tema";
import { relativo } from "../lib/formato";

const ICONOS = {
  info: <InfoRoundedIcon sx={{ color: colores.info }} />,
  exito: <CheckCircleRoundedIcon sx={{ color: colores.ok }} />,
  alerta: <WarningRoundedIcon sx={{ color: colores.alerta }} />,
  critico: <ErrorRoundedIcon sx={{ color: colores.critico }} />,
};

/**
 * Avisos del usuario. Se actualizan en tiempo real con Server-Sent Events
 * (/api/avisos/stream); si el navegador o la red no lo permiten, se consulta
 * cada minuto
 */
export function Campana() {
  const qc = useQueryClient();
  const navegar = useNavigate();
  const [ancla, setAncla] = useState<HTMLElement | null>(null);
  const { data } = useQuery({
    queryKey: ["avisos"],
    queryFn: async () =>
      (await api.get<{ data: Aviso[]; no_leidos: number }>("/avisos")).data,
    refetchInterval: 60_000,
  });

  useEffect(() => {
    if (typeof EventSource === "undefined") return;
    const fuente = new EventSource(`${BASE_API}/api/avisos/stream`, {
      withCredentials: true,
    });
    fuente.addEventListener("aviso", () =>
      qc.invalidateQueries({ queryKey: ["avisos"] }),
    );
    return () => fuente.close();
  }, [qc]);

  const abrir = async (a: Aviso) => {
    setAncla(null);
    if (!a.leido_at) await api.post(`/avisos/${a.id}/leer`).catch(() => {});
    qc.invalidateQueries({ queryKey: ["avisos"] });
    if (a.enlace) navegar(a.enlace);
  };

  const leerTodos = async () => {
    await api.post("/avisos/leer-todos");
    qc.invalidateQueries({ queryKey: ["avisos"] });
  };

  return (
    <>
      <Tooltip title="Avisos">
        <IconButton
          onClick={(e) => setAncla(e.currentTarget)}
          aria-label={`Avisos, ${data?.no_leidos ?? 0} sin leer`}
        >
          <Badge badgeContent={data?.no_leidos ?? 0} color="primary" max={9}>
            <NotificationsNoneRoundedIcon />
          </Badge>
        </IconButton>
      </Tooltip>
      <Popover
        open={!!ancla}
        anchorEl={ancla}
        onClose={() => setAncla(null)}
        anchorOrigin={{ vertical: "bottom", horizontal: "right" }}
        transformOrigin={{ vertical: "top", horizontal: "right" }}
        slotProps={{
          paper: {
            sx: {
              width: 380,
              maxWidth: "calc(100vw - 24px)",
              mt: 1,
              border: `1px solid ${colores.linea}`,
            },
          },
        }}
      >
        <Stack
          direction="row"
          sx={{
            px: 2,
            py: 1.5,
            alignItems: "center",
            justifyContent: "space-between",
          }}
        >
          <Typography variant="h3" component="h2">
            Avisos
          </Typography>
          {!!data?.no_leidos && (
            <Button size="small" onClick={leerTodos}>
              Marcar todo como leído
            </Button>
          )}
        </Stack>
        <Divider />
        <List disablePadding sx={{ maxHeight: 420, overflow: "auto" }}>
          {data?.data.length === 0 && (
            <Typography
              color="text.secondary"
              sx={{ p: 3, textAlign: "center" }}
            >
              No tiene avisos.
            </Typography>
          )}
          {data?.data.map((a) => (
            <ListItemButton
              key={a.id}
              onClick={() => abrir(a)}
              sx={{
                alignItems: "flex-start",
                gap: 1.5,
                py: 1.5,
                bgcolor: a.leido_at ? undefined : colores.vinoTenue,
              }}
            >
              <Box sx={{ mt: 0.25 }}>{ICONOS[a.tipo]}</Box>
              <Box sx={{ minWidth: 0 }}>
                <Typography
                  variant="body2"
                  sx={{ fontWeight: a.leido_at ? 500 : 700 }}
                >
                  {a.titulo}
                </Typography>
                <Typography variant="body2" color="text.secondary">
                  {a.mensaje}
                </Typography>
                <Typography variant="caption" color="text.secondary">
                  {relativo(a.created_at)}
                </Typography>
              </Box>
            </ListItemButton>
          ))}
        </List>
      </Popover>
    </>
  );
}
