import CloseRoundedIcon from "@mui/icons-material/CloseRounded";
import {
  Box,
  Dialog,
  DialogContent,
  IconButton,
  Stack,
  Typography,
} from "@mui/material";
import type { ProductoPublico } from "../../api/tipos";
import { colores, fuenteTitulos } from "../../app/tema";
import { TIPOS_PRODUCTO } from "../../components/Estados";
import { pesos } from "../../lib/formato";

/** Tarjeta de un modelo del catálogo público (foto, tipo, nombre, material y precio). La usan la portada y el catálogo. */
export function TarjetaProducto({
  producto: p,
  onClick,
}: {
  producto: ProductoPublico;
  onClick: () => void;
}) {
  return (
    <Box
      component="button"
      onClick={onClick}
      sx={{
        textAlign: "left",
        font: "inherit",
        color: "inherit",
        cursor: "pointer",
        p: 0,
        border: `1px solid ${colores.linea}`,
        borderRadius: 3,
        overflow: "hidden",
        bgcolor: "#fff",
        transition: "border-color .2s, box-shadow .2s",
        "&:hover, &:focus-visible": {
          borderColor: colores.rosaCeniza,
          boxShadow: "0 6px 24px rgba(75,18,23,0.08)",
        },
      }}
    >
      <Box
        sx={{ aspectRatio: "4 / 3", bgcolor: "#F1EFF2", overflow: "hidden" }}
      >
        {p.foto_url && (
          <Box
            component="img"
            src={p.foto_url}
            alt={p.nombre}
            loading="lazy"
            sx={{
              width: "100%",
              height: "100%",
              objectFit: "cover",
              display: "block",
            }}
          />
        )}
      </Box>
      <Box sx={{ p: 2.5 }}>
        <Typography variant="caption" sx={{ color: colores.piedra }}>
          {p.infantil ? "Infantil" : TIPOS_PRODUCTO[p.tipo]}
        </Typography>
        <Typography
          sx={{
            fontFamily: fuenteTitulos,
            fontWeight: 600,
            fontSize: "1.6rem",
            lineHeight: 1.15,
            mt: 0.25,
          }}
        >
          {p.nombre}
        </Typography>
        <Typography variant="body2" color="text.secondary" sx={{ mt: 0.5 }}>
          {[p.material, p.color?.toLowerCase()].filter(Boolean).join(", ")}
        </Typography>
        <Typography
          sx={{
            mt: 1.5,
            fontWeight: 600,
            fontSize: "1.1rem",
            color: colores.vino,
          }}
        >
          {pesos(p.precio)}
        </Typography>
      </Box>
    </Box>
  );
}

/** Ficha ampliada de un modelo, en un diálogo */
export function DetalleProducto({
  producto: detalle,
  onClose,
}: {
  producto: ProductoPublico | null;
  onClose: () => void;
}) {
  return (
    <Dialog open={!!detalle} onClose={onClose} maxWidth="md" fullWidth>
      {detalle && (
        <DialogContent
          sx={{
            p: 0,
            display: "grid",
            gridTemplateColumns: { xs: "1fr", md: "1.2fr 1fr" },
          }}
        >
          <Box sx={{ bgcolor: "#F1EFF2", minHeight: 280 }}>
            {detalle.foto_url && (
              <Box
                component="img"
                src={detalle.foto_url}
                alt={detalle.nombre}
                sx={{
                  width: "100%",
                  height: "100%",
                  objectFit: "cover",
                  display: "block",
                }}
              />
            )}
          </Box>
          <Box sx={{ p: 3.5, position: "relative" }}>
            <IconButton
              onClick={onClose}
              aria-label="Cerrar"
              sx={{ position: "absolute", top: 8, right: 8 }}
            >
              <CloseRoundedIcon />
            </IconButton>
            <Typography variant="caption" color="text.secondary">
              {detalle.infantil
                ? "Modelo infantil"
                : TIPOS_PRODUCTO[detalle.tipo]}
            </Typography>
            <Typography
              sx={{
                fontFamily: fuenteTitulos,
                fontWeight: 600,
                fontSize: "2.2rem",
                lineHeight: 1.1,
                mt: 0.5,
                pr: 4,
              }}
            >
              {detalle.nombre}
            </Typography>
            <Typography
              sx={{
                mt: 1.5,
                fontWeight: 600,
                fontSize: "1.35rem",
                color: colores.vino,
              }}
            >
              {pesos(detalle.precio)}
            </Typography>
            <Typography sx={{ mt: 2, lineHeight: 1.65 }}>
              {detalle.descripcion}
            </Typography>
            <Stack spacing={1} sx={{ mt: 3 }}>
              {[
                ["Material", detalle.material],
                ["Color", detalle.color],
                ["Medidas", detalle.medidas],
                ["Código", detalle.codigo],
              ]
                .filter(([, v]) => v)
                .map(([k, v]) => (
                  <Stack
                    key={k}
                    direction="row"
                    sx={{
                      justifyContent: "space-between",
                      borderBottom: `1px solid ${colores.linea}`,
                      pb: 1,
                    }}
                  >
                    <Typography variant="body2" color="text.secondary">
                      {k}
                    </Typography>
                    <Typography variant="body2" sx={{ fontWeight: 500 }}>
                      {v}
                    </Typography>
                  </Stack>
                ))}
            </Stack>
          </Box>
        </DialogContent>
      )}
    </Dialog>
  );
}
