import DirectionsCarOutlinedIcon from "@mui/icons-material/DirectionsCarOutlined";
import HandshakeOutlinedIcon from "@mui/icons-material/HandshakeOutlined";
import Inventory2OutlinedIcon from "@mui/icons-material/Inventory2Outlined";
import LocalFloristOutlinedIcon from "@mui/icons-material/LocalFloristOutlined";
import ParkOutlinedIcon from "@mui/icons-material/ParkOutlined";
import PhoneRoundedIcon from "@mui/icons-material/PhoneRounded";
import PlaceOutlinedIcon from "@mui/icons-material/PlaceOutlined";
import SpaOutlinedIcon from "@mui/icons-material/SpaOutlined";
import { Box, Button, Skeleton, Stack, Typography } from "@mui/material";
import { useQuery } from "@tanstack/react-query";
import { useState, type ReactNode } from "react";
import { Link as RouterLink } from "react-router-dom";
import { api } from "../../api/cliente";
import type { ProductoPublico } from "../../api/tipos";
import { CONTACTO } from "../../app/contacto";
import { colores, fuenteTitulos } from "../../app/tema";
import { MarcoPublico } from "./MarcoPublico";
import { DetalleProducto, TarjetaProducto } from "./TarjetaProducto";

const ANCHO = 1240;

const SERVICIOS: { icono: ReactNode; titulo: string; texto: string }[] = [
  {
    icono: <LocalFloristOutlinedIcon />,
    titulo: "Velatorio",
    texto:
      "Organizamos el velatorio con la fecha, la hora y el lugar que acuerde la familia.",
  },
  {
    icono: <DirectionsCarOutlinedIcon />,
    titulo: "Traslado",
    texto: "Carroza y equipo para el traslado hasta el lugar de sepultación.",
  },
  {
    icono: <ParkOutlinedIcon />,
    titulo: "Sepultación",
    texto:
      "Acompañamiento hasta el cementerio y coordinación de la sepultación.",
  },
  {
    icono: <Inventory2OutlinedIcon />,
    titulo: "Urnas y ataúdes",
    texto: "Modelos para adultos y niños, más detalles en el catálogo.",
  },
  {
    icono: <SpaOutlinedIcon />,
    titulo: "Cremación",
    texto: "Urnas de cremación para conservar las cenizas.",
  },
  {
    icono: <HandshakeOutlinedIcon />,
    titulo: "Convenios y cuotas",
    texto:
      "Descuentos por convenio y la posibilidad de pagar el saldo en cuotas mensuales.",
  },
];

/**
 * Portada pública del sitio (ruta /). Presenta los servicios, algunos modelos del
 * catálogo con su precio y los datos de contacto. Desde aquí se llega al catálogo
 * completo y al acceso del personal.
 */
export default function Portada() {
  const [detalle, setDetalle] = useState<ProductoPublico | null>(null);
  const destacados = useQuery({
    queryKey: ["publico", "catalogo", { orden: "relevancia" }],
    queryFn: async () =>
      (
        await api.get<ProductoPublico[]>("/publico/catalogo", {
          params: { orden: "relevancia" },
        })
      ).data,
  });

  return (
    <MarcoPublico>
      {/* Portada */}
      <Box sx={{ bgcolor: colores.vinoProfundo, color: "#fff" }}>
        <Box
          sx={{
            maxWidth: ANCHO,
            mx: "auto",
            px: { xs: 2, md: 4 },
            pt: { xs: 6, md: 10 },
            pb: { xs: 6, md: 10 },
            display: "grid",
            gap: { xs: 5, md: 8 },
            gridTemplateColumns: { xs: "1fr", md: "1.35fr 1fr" },
            alignItems: "center",
          }}
        >
          <Box>
            <Typography
              component="h1"
              sx={{
                fontFamily: fuenteTitulos,
                fontWeight: 600,
                fontSize: { xs: "2.6rem", sm: "3.2rem", md: "4.1rem" },
                lineHeight: 1.02,
                letterSpacing: "-0.01em",
                maxWidth: 640,
              }}
            >
              Servicios funerarios en {CONTACTO.cobertura}
            </Typography>
            <Typography
              sx={{
                mt: 3,
                fontSize: { xs: "1.05rem", md: "1.15rem" },
                lineHeight: 1.65,
                color: "rgba(255,255,255,0.8)",
                maxWidth: 540,
              }}
            >
              Velatorio, traslado y sepultación coordinados por un mismo equipo.
              Le orientamos en cada paso y le brindamos más detalles antes de
              contratar.
            </Typography>
            <Stack
              direction={{ xs: "column", sm: "row" }}
              spacing={1.5}
              sx={{ mt: 4 }}
            >
              <Button
                component={RouterLink}
                to="/catalogo"
                size="large"
                variant="contained"
                sx={{
                  bgcolor: "#fff",
                  color: colores.vinoProfundo,
                  "&:hover": { bgcolor: colores.vinoTenue },
                }}
              >
                Ver catálogo y precios
              </Button>
              <Button
                component={RouterLink}
                to="/#servicios"
                size="large"
                variant="outlined"
                sx={{
                  color: "#fff",
                  borderColor: "rgba(255,255,255,0.45)",
                  "&:hover": {
                    borderColor: "#fff",
                    bgcolor: "rgba(255,255,255,0.06)",
                  },
                }}
              >
                Conocer los servicios
              </Button>
            </Stack>
          </Box>

          {/* Arco: evoca la ventana de una capilla y contiene lo más urgente, el teléfono */}
          <Box
            sx={{
              justifySelf: { md: "end" },
              width: "100%",
              maxWidth: 380,
              textAlign: "center",
              border: `1px solid ${colores.rosaCeniza}`,
              borderRadius: "999px 999px 20px 20px",
              px: { xs: 3, md: 4 },
              pt: { xs: 7, md: 9 },
              pb: 4,
              bgcolor: "rgba(255,255,255,0.04)",
            }}
          >
            <Typography sx={{ color: colores.rosaCeniza, fontSize: "0.95rem" }}>
              Para coordinar un servicio
            </Typography>
            <Typography
              sx={{
                fontFamily: fuenteTitulos,
                fontSize: { xs: "2.3rem", md: "2.6rem" },
                fontWeight: 600,
                lineHeight: 1.1,
                mt: 1,
              }}
            >
              {CONTACTO.telefono}
            </Typography>
            <Button
              href={CONTACTO.telefonoEnlace}
              variant="contained"
              size="large"
              startIcon={<PhoneRoundedIcon />}
              fullWidth
              sx={{
                mt: 3,
                bgcolor: colores.vino,
                "&:hover": { bgcolor: "#A52A32" },
              }}
            >
              Llamar ahora
            </Button>
            <Typography
              variant="body2"
              sx={{ mt: 3, color: "rgba(255,255,255,0.72)" }}
            >
              {CONTACTO.direccion}, {CONTACTO.ciudad}
            </Typography>
          </Box>
        </Box>
      </Box>

      {/* Servicios */}
      <Box id="servicios" sx={{ bgcolor: "#fff", scrollMarginTop: 72 }}>
        <Box
          sx={{
            maxWidth: ANCHO,
            mx: "auto",
            px: { xs: 2, md: 4 },
            py: { xs: 7, md: 10 },
          }}
        >
          <Titulo
            titulo="Lo que coordinamos"
            bajada="Nos encargamos de cada etapa del servicio para que pueda dedicarse a despedir a su ser querido."
          />
          <Box
            sx={{
              display: "grid",
              columnGap: 5,
              rowGap: 0,
              gridTemplateColumns: {
                xs: "1fr",
                sm: "1fr 1fr",
                lg: "1fr 1fr 1fr",
              },
            }}
          >
            {SERVICIOS.map((s) => (
              <Box
                key={s.titulo}
                sx={{ borderTop: `1px solid ${colores.linea}`, py: 3.5 }}
              >
                <Box sx={{ color: colores.vino, "& svg": { fontSize: 30 } }}>
                  {s.icono}
                </Box>
                <Typography
                  component="h3"
                  sx={{
                    fontFamily: fuenteTitulos,
                    fontWeight: 600,
                    fontSize: "1.65rem",
                    mt: 1.5,
                  }}
                >
                  {s.titulo}
                </Typography>
                <Typography
                  sx={{
                    color: colores.piedra,
                    mt: 0.75,
                    lineHeight: 1.6,
                    maxWidth: 360,
                  }}
                >
                  {s.texto}
                </Typography>
              </Box>
            ))}
          </Box>
        </Box>
      </Box>

      {/* Catálogo destacado */}
      <Box
        sx={{
          maxWidth: ANCHO,
          mx: "auto",
          px: { xs: 2, md: 4 },
          py: { xs: 7, md: 10 },
        }}
      >
        <Stack
          direction={{ xs: "column", md: "row" }}
          sx={{
            justifyContent: "space-between",
            alignItems: { md: "flex-end" },
            gap: 2,
            mb: { xs: 3, md: 4 },
          }}
        >
          <Titulo
            titulo="Urnas y ataúdes"
            bajada="Todos los modelos con su precio."
            sinMargen
          />
          <Button
            component={RouterLink}
            to="/catalogo"
            variant="outlined"
            sx={{ flexShrink: 0 }}
          >
            Ver catálogo completo
          </Button>
        </Stack>
        <Box
          sx={{
            display: "grid",
            gap: { xs: 2.5, md: 3.5 },
            gridTemplateColumns: {
              xs: "1fr",
              sm: "1fr 1fr",
              lg: "1fr 1fr 1fr",
            },
          }}
        >
          {destacados.isLoading &&
            Array.from({ length: 3 }, (_, i) => (
              <Skeleton key={i} variant="rounded" height={340} />
            ))}
          {destacados.data?.slice(0, 3).map((p) => (
            <TarjetaProducto
              key={p.id}
              producto={p}
              onClick={() => setDetalle(p)}
            />
          ))}
        </Box>
      </Box>

      {/* Contacto */}
      <Box
        id="contacto"
        sx={{
          bgcolor: "#fff",
          borderTop: `1px solid ${colores.linea}`,
          scrollMarginTop: 72,
        }}
      >
        <Box
          sx={{
            maxWidth: ANCHO,
            mx: "auto",
            px: { xs: 2, md: 4 },
            py: { xs: 7, md: 10 },
            display: "grid",
            gap: { xs: 4, md: 8 },
            gridTemplateColumns: { xs: "1fr", md: "1fr 1fr" },
          }}
        >
          <Titulo
            titulo="Contacto"
            bajada={`Atendemos a familias de ${CONTACTO.cobertura}. Llámenos para coordinar un servicio o resolver sus dudas.`}
            sinMargen
          />
          <Stack spacing={3}>
            {CONTACTO.sucursales.map((sucursal) => (
              <Stack key={sucursal.nombre} direction="row" spacing={2}>
                <PlaceOutlinedIcon sx={{ color: colores.vino, mt: 0.25 }} />
                <Box>
                  <Typography color="text.secondary" variant="body2">
                    {sucursal.nombre}
                  </Typography>
                  <Typography sx={{ fontWeight: 600, fontSize: "1.1rem" }}>
                    {sucursal.direccion}
                  </Typography>
                  <Typography color="text.secondary">
                    {[sucursal.sector, sucursal.ciudad]
                      .filter(Boolean)
                      .join(", ")}
                  </Typography>
                  <Typography
                    component="a"
                    href={sucursal.telefonoEnlace}
                    sx={{
                      display: "block",
                      mt: 0.5,
                      fontWeight: 600,
                      color: "inherit",
                      textDecoration: "none",
                      "&:hover": { textDecoration: "underline" },
                    }}
                  >
                    {sucursal.telefono}
                  </Typography>
                  <Button
                    href={sucursal.mapa}
                    target="_blank"
                    rel="noopener"
                    sx={{ mt: 1, ml: -1 }}
                  >
                    Cómo llegar
                  </Button>
                </Box>
              </Stack>
            ))}
          </Stack>
        </Box>
      </Box>

      <DetalleProducto producto={detalle} onClose={() => setDetalle(null)} />
    </MarcoPublico>
  );
}

function Titulo({
  titulo,
  bajada,
  sinMargen,
}: {
  titulo: string;
  bajada: string;
  sinMargen?: boolean;
}) {
  return (
    <Box sx={{ maxWidth: 620, mb: sinMargen ? 0 : { xs: 3, md: 4 } }}>
      <Typography
        component="h2"
        sx={{
          fontFamily: fuenteTitulos,
          fontWeight: 600,
          fontSize: { xs: "2.2rem", md: "2.8rem" },
          lineHeight: 1.05,
          color: colores.vinoProfundo,
        }}
      >
        {titulo}
      </Typography>
      <Typography
        sx={{
          mt: 1.5,
          color: colores.piedra,
          fontSize: "1.05rem",
          lineHeight: 1.6,
        }}
      >
        {bajada}
      </Typography>
    </Box>
  );
}
