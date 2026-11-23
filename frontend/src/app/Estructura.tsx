import LogoutRoundedIcon from "@mui/icons-material/LogoutRounded";
import MenuRoundedIcon from "@mui/icons-material/MenuRounded";
import OpenInNewRoundedIcon from "@mui/icons-material/OpenInNewRounded";
import {
  Avatar,
  Box,
  Chip,
  Divider,
  Drawer,
  IconButton,
  List,
  ListItemButton,
  ListItemIcon,
  ListItemText,
  Menu,
  MenuItem,
  Stack,
  Typography,
  useMediaQuery,
} from "@mui/material";
import { useState } from "react";
import { NavLink, Outlet, useLocation } from "react-router-dom";
import { useSesion } from "../auth/Sesion";
import { Campana } from "../components/Campana";
import { fecha } from "../lib/formato";
import { MENU } from "./navegacion";
import { colores, tema } from "./tema";

const ANCHO = 252;

function MenuLateral({ alNavegar }: { alNavegar?: () => void }) {
  const { puede } = useSesion();
  const { pathname } = useLocation();
  const activo = (a: string) =>
    a === "/" ? pathname === "/" : pathname.startsWith(a);

  return (
    <Box
      sx={{
        height: "100%",
        display: "flex",
        flexDirection: "column",
        bgcolor: colores.vinoProfundo,
        color: "#fff",
      }}
    >
      <Box sx={{ px: 3, pt: 2.5, pb: 1.5 }}>
        <Box
          component="img"
          src="/logo-marca-blanco.png"
          alt="Funeraria Cristo Rey"
          sx={{ width: 150, display: "block" }}
        />
      </Box>
      <Box
        component="nav"
        aria-label="Menú principal"
        sx={{ flex: 1, overflowY: "auto", px: 1.5, pb: 2 }}
      >
        {MENU.map((g, i) => {
          const items = g.items.filter((it) => puede(...it.permisos));
          if (!items.length) return null;
          return (
            <Box key={g.titulo ?? i} sx={{ mt: g.titulo ? 1.5 : 0 }}>
              {g.titulo && (
                <Typography
                  sx={{
                    px: 1.5,
                    pb: 0.5,
                    fontSize: "0.75rem",
                    fontWeight: 600,
                    color: "rgba(255,255,255,0.5)",
                  }}
                >
                  {g.titulo}
                </Typography>
              )}
              <List dense disablePadding>
                {items.map((it) => (
                  <ListItemButton
                    key={it.a}
                    component={NavLink}
                    to={it.a}
                    onClick={alNavegar}
                    selected={activo(it.a)}
                    sx={{
                      borderRadius: 1.5,
                      mb: 0.25,
                      py: 0.5,
                      color: "rgba(255,255,255,0.8)",
                      "& .MuiListItemIcon-root": {
                        color: "rgba(255,255,255,0.62)",
                        minWidth: 34,
                      },
                      "& svg": { fontSize: 19 },
                      "&:hover": { bgcolor: "rgba(255,255,255,0.07)" },
                      "&.Mui-selected, &.Mui-selected:hover": {
                        bgcolor: "rgba(255,255,255,0.12)",
                        color: "#fff",
                        boxShadow: `inset 3px 0 0 ${colores.rosaCeniza}`,
                        "& .MuiListItemIcon-root": {
                          color: colores.rosaCeniza,
                        },
                      },
                    }}
                  >
                    <ListItemIcon>{it.icono}</ListItemIcon>
                    <ListItemText
                      primary={it.texto}
                      slotProps={{
                        primary: {
                          sx: { fontWeight: 500, fontSize: "0.875rem" },
                        },
                      }}
                    />
                  </ListItemButton>
                ))}
              </List>
            </Box>
          );
        })}
      </Box>
      <Box
        sx={{
          px: 1.5,
          pb: 1.5,
          pt: 1,
          borderTop: "1px solid rgba(255,255,255,0.08)",
        }}
      >
        <ListItemButton
          component="a"
          href="/"
          target="_blank"
          rel="noopener"
          sx={{
            borderRadius: 1.5,
            color: "rgba(255,255,255,0.7)",
            "& svg": { fontSize: 18 },
          }}
        >
          <ListItemText
            primary="Ver sitio público"
            slotProps={{ primary: { sx: { fontSize: "0.84rem" } } }}
          />
          <OpenInNewRoundedIcon />
        </ListItemButton>
      </Box>
    </Box>
  );
}

function MenuUsuario() {
  const { sesion, cerrar } = useSesion();
  const [ancla, setAncla] = useState<HTMLElement | null>(null);
  if (!sesion) return null;
  const iniciales = sesion.nombre
    .split(" ")
    .map((p) => p[0])
    .slice(0, 2)
    .join("");

  return (
    <>
      <Box
        component="button"
        onClick={(e: React.MouseEvent<HTMLElement>) =>
          setAncla(e.currentTarget)
        }
        sx={{
          display: "flex",
          alignItems: "center",
          gap: 1.25,
          border: 0,
          bgcolor: "transparent",
          cursor: "pointer",
          p: 0.5,
          borderRadius: 2,
          font: "inherit",
          color: "inherit",
          textAlign: "left",
        }}
        aria-label="Menú de la cuenta"
      >
        <Avatar
          sx={{
            width: 34,
            height: 34,
            bgcolor: colores.vinoSuave,
            color: colores.vino,
            fontSize: "0.85rem",
            fontWeight: 700,
          }}
        >
          {iniciales}
        </Avatar>
        <Box sx={{ display: { xs: "none", sm: "block" } }}>
          <Typography variant="body2" sx={{ fontWeight: 600, lineHeight: 1.2 }}>
            {sesion.nombre}
          </Typography>
          <Typography variant="caption" color="text.secondary">
            {sesion.rol}
          </Typography>
        </Box>
      </Box>
      <Menu
        anchorEl={ancla}
        open={!!ancla}
        onClose={() => setAncla(null)}
        anchorOrigin={{ vertical: "bottom", horizontal: "right" }}
        transformOrigin={{ vertical: "top", horizontal: "right" }}
      >
        <Box sx={{ px: 2, py: 1, minWidth: 240 }}>
          <Typography variant="body2" sx={{ fontWeight: 600 }}>
            {sesion.nombre}
          </Typography>
          <Typography variant="body2" color="text.secondary">
            {sesion.email}
          </Typography>
          {sesion.suplencia_hasta && (
            <Chip
              size="small"
              color="primary"
              variant="outlined"
              sx={{ mt: 1 }}
              label={`Suplencia de administrador hasta el ${fecha(sesion.suplencia_hasta)}`}
            />
          )}
        </Box>
        <Divider />
        <MenuItem
          onClick={() => {
            setAncla(null);
            cerrar();
          }}
        >
          <ListItemIcon>
            <LogoutRoundedIcon fontSize="small" />
          </ListItemIcon>
          Cerrar sesión
        </MenuItem>
      </Menu>
    </>
  );
}

/** Estructura de la aplicación interna: menú lateral, barra superior y contenido */
export function Estructura() {
  const escritorio = useMediaQuery(tema.breakpoints.up("lg"));
  const [abierto, setAbierto] = useState(false);

  return (
    <Box sx={{ display: "flex", minHeight: "100vh" }}>
      {escritorio ? (
        <Box
          component="aside"
          sx={{
            width: ANCHO,
            flexShrink: 0,
            position: "sticky",
            top: 0,
            height: "100vh",
          }}
        >
          <MenuLateral />
        </Box>
      ) : (
        <Drawer
          open={abierto}
          onClose={() => setAbierto(false)}
          slotProps={{ paper: { sx: { width: ANCHO, border: 0 } } }}
        >
          <MenuLateral alNavegar={() => setAbierto(false)} />
        </Drawer>
      )}
      <Box
        sx={{ flex: 1, minWidth: 0, display: "flex", flexDirection: "column" }}
      >
        <Stack
          direction="row"
          component="div"
          sx={{
            position: "sticky",
            top: 0,
            zIndex: 10,
            alignItems: "center",
            gap: 1,
            px: { xs: 1.5, md: 4 },
            py: 1.25,
            bgcolor: "rgba(244,243,245,0.88)",
            backdropFilter: "blur(8px)",
            borderBottom: `1px solid ${colores.linea}`,
          }}
        >
          {!escritorio && (
            <IconButton
              onClick={() => setAbierto(true)}
              aria-label="Abrir menú"
            >
              <MenuRoundedIcon />
            </IconButton>
          )}
          {!escritorio && (
            <Box
              component="img"
              src="/logo-marca.png"
              alt="Funeraria Cristo Rey"
              sx={{ height: 30 }}
            />
          )}
          <Box sx={{ flex: 1 }} />
          <Campana />
          <MenuUsuario />
        </Stack>
        <Box
          component="main"
          sx={{
            flex: 1,
            px: { xs: 2, md: 4 },
            py: { xs: 2.5, md: 4 },
            maxWidth: 1480,
            width: "100%",
            mx: "auto",
          }}
        >
          <Outlet />
        </Box>
      </Box>
    </Box>
  );
}
