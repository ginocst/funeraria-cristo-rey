import HandshakeOutlinedIcon from "@mui/icons-material/HandshakeOutlined";
import PhoneRoundedIcon from "@mui/icons-material/PhoneRounded";
import {
  Box,
  Button,
  IconButton,
  Link,
  Stack,
  Typography,
} from "@mui/material";
import { useQuery } from "@tanstack/react-query";
import { useEffect, type ReactNode } from "react";
import { Link as RouterLink, useLocation } from "react-router-dom";
import { api } from "../../api/cliente";
import { CONTACTO } from "../../app/contacto";
import { colores, fuenteTitulos } from "../../app/tema";
import { useSesion } from "../../auth/Sesion";

const ANCHO = 1240;
const ANIO = new Date().getFullYear();

const ENLACES = [
  { a: "/#servicios", texto: "Servicios" },
  { a: "/catalogo", texto: "Catálogo" },
  { a: "/#convenios", texto: "Convenios" },
  { a: "/#contacto", texto: "Contacto" },
];

/**
 * Cabecera y pie de las páginas públicas (portada y catálogo).
 * La cabecera muestra el acceso del personal, o el enlace al panel si ya hay sesión.
 */
export function MarcoPublico({ children }: { children: ReactNode }) {
  const { sesion } = useSesion();
  const { hash, pathname } = useLocation();

  // React Router no desplaza la página hasta un ancla (#servicios); se hace aquí.
  useEffect(() => {
    if (!hash) return;
    const t = setTimeout(
      () =>
        document
          .getElementById(hash.slice(1))
          ?.scrollIntoView({ behavior: "smooth", block: "start" }),
      50,
    );
    return () => clearTimeout(t);
  }, [hash, pathname]);

  return (
    <Box
      sx={{
        bgcolor: "#FBFAFB",
        minHeight: "100vh",
        display: "flex",
        flexDirection: "column",
      }}
    >
      <Box
        component="header"
        sx={{
          borderBottom: `1px solid ${colores.linea}`,
          bgcolor: "#fff",
          position: "sticky",
          top: 0,
          zIndex: 10,
        }}
      >
        <Stack
          direction="row"
          sx={{
            maxWidth: ANCHO,
            mx: "auto",
            px: { xs: 2, md: 4 },
            py: 1.5,
            alignItems: "center",
            justifyContent: "space-between",
            gap: 2,
          }}
        >
          <Box
            component={RouterLink}
            to="/"
            aria-label="Funeraria Cristo Rey, ir a la portada"
            sx={{ display: "block", lineHeight: 0 }}
          >
            <Box
              component="img"
              src="/logo-marca.png"
              alt="Funeraria Cristo Rey"
              sx={{ height: { xs: 38, md: 48 } }}
            />
          </Box>
          <Stack
            component="nav"
            aria-label="Secciones"
            direction="row"
            spacing={0.5}
            sx={{ display: { xs: "none", md: "flex" } }}
          >
            {ENLACES.map((e) => (
              <Button
                key={e.a}
                component={RouterLink}
                to={e.a}
                color="inherit"
                sx={{ color: colores.tinta, fontWeight: 500 }}
              >
                {e.texto}
              </Button>
            ))}
          </Stack>
          <Stack direction="row" spacing={1} sx={{ alignItems: "center" }}>
            <Button
              href={CONTACTO.telefonoEnlace}
              variant="contained"
              startIcon={<PhoneRoundedIcon />}
              sx={{ display: { xs: "none", sm: "inline-flex" } }}
            >
              {CONTACTO.telefono}
            </Button>
            <IconButton
              href={CONTACTO.telefonoEnlace}
              aria-label={`Llamar al ${CONTACTO.telefono}`}
              sx={{ display: { sm: "none" }, color: colores.vino }}
            >
              <PhoneRoundedIcon />
            </IconButton>
            {sesion ? (
              <Button
                component={RouterLink}
                to="/panel"
                size="small"
                color="inherit"
              >
                Ir al panel
              </Button>
            ) : (
              <Button
                component={RouterLink}
                to="/ingresar"
                size="small"
                color="inherit"
              >
                Acceso del personal
              </Button>
            )}
          </Stack>
        </Stack>
      </Box>

      <Box component="main" sx={{ flex: 1 }}>
        {children}
      </Box>

      <Pie />
    </Box>
  );
}

function Pie() {
  const convenios = useQuery({
    queryKey: ["publico", "convenios"],
    queryFn: async () =>
      (
        await api.get<{ id: number; nombre: string; institucion: string }[]>(
          "/publico/convenios",
        )
      ).data,
  });
  const tenue = "rgba(255,255,255,0.72)";

  return (
    <Box
      component="footer"
      sx={{ bgcolor: colores.vinoProfundo, color: "#fff" }}
    >
      <Box
        sx={{
          maxWidth: ANCHO,
          mx: "auto",
          px: { xs: 2, md: 4 },
          py: { xs: 6, md: 8 },
          display: "grid",
          gap: { xs: 5, md: 6 },
          gridTemplateColumns: { xs: "1fr", md: "1fr 2fr" },
        }}
      >
        <Box>
          <Box
            component="img"
            src="/logo-marca-blanco.png"
            alt="Funeraria Cristo Rey"
            sx={{ width: 200 }}
          />
          <Typography sx={{ mt: 3, fontWeight: 600 }}>
            {CONTACTO.direccion}
          </Typography>
          <Typography sx={{ color: tenue }}>{CONTACTO.ciudad}</Typography>
          <Link
            href={CONTACTO.telefonoEnlace}
            underline="hover"
            sx={{
              display: "inline-block",
              mt: 1.5,
              color: "#fff",
              fontSize: "1.15rem",
              fontWeight: 600,
            }}
          >
            {CONTACTO.telefono}
          </Link>
          <Typography
            variant="body2"
            sx={{ mt: 3, color: tenue, maxWidth: 340 }}
          >
            Los precios del catálogo son de referencia y pueden variar según el
            servicio contratado. Consulte para más detalles.
          </Typography>
        </Box>
        <Box id="convenios" sx={{ scrollMarginTop: 96 }}>
          <Stack
            direction="row"
            spacing={1}
            sx={{ alignItems: "center", mb: 1 }}
          >
            <HandshakeOutlinedIcon sx={{ color: colores.rosaCeniza }} />
            <Typography
              component="h2"
              sx={{
                fontFamily: fuenteTitulos,
                fontSize: "1.7rem",
                fontWeight: 600,
              }}
            >
              Convenios vigentes
            </Typography>
          </Stack>
          <Typography variant="body2" sx={{ color: tenue, mb: 2.5 }}>
            Si pertenece a alguna de estas instituciones, indíquelo al
            contratar.
          </Typography>
          <Box
            sx={{
              display: "grid",
              gap: 2,
              gridTemplateColumns: { xs: "1fr", sm: "1fr 1fr" },
            }}
          >
            {convenios.data?.map((c) => (
              <Box
                key={c.id}
                sx={{ borderLeft: `2px solid ${colores.rosaCeniza}`, pl: 1.5 }}
              >
                <Typography sx={{ fontWeight: 600 }}>{c.nombre}</Typography>
                <Typography variant="body2" sx={{ color: tenue }}>
                  {c.institucion}
                </Typography>
              </Box>
            ))}
            {convenios.data?.length === 0 && (
              <Typography variant="body2" sx={{ color: tenue }}>
                Consulte por teléfono los convenios disponibles.
              </Typography>
            )}
          </Box>
        </Box>
      </Box>
      <Box sx={{ borderTop: "1px solid rgba(255,255,255,0.1)" }}>
        <Stack
          direction={{ xs: "column", sm: "row" }}
          sx={{
            maxWidth: ANCHO,
            mx: "auto",
            px: { xs: 2, md: 4 },
            py: 2.5,
            justifyContent: "space-between",
            gap: 1,
          }}
        >
          <Typography variant="body2" sx={{ color: tenue }}>
            © {ANIO} Funeraria Cristo Rey. {CONTACTO.cobertura}.
          </Typography>
          <Link
            component={RouterLink}
            to="/ingresar"
            underline="hover"
            variant="body2"
            sx={{ color: tenue }}
          >
            Acceso del personal
          </Link>
        </Stack>
      </Box>
    </Box>
  );
}
