import DeleteOutlineRoundedIcon from "@mui/icons-material/DeleteOutlineRounded";
import UploadRoundedIcon from "@mui/icons-material/UploadRounded";
import {
  Box,
  Button,
  Dialog,
  DialogActions,
  DialogContent,
  DialogTitle,
  IconButton,
  MenuItem,
  Stack,
  TextField,
  Tooltip,
  Typography,
} from "@mui/material";
import { useQuery, useQueryClient } from "@tanstack/react-query";
import { useRef, useState } from "react";
import { api, leerError } from "../../api/cliente";
import type { FotoProducto, Producto } from "../../api/tipos";
import { colores } from "../../app/tema";
import { Confirmar } from "../../components/Confirmar";
import { useAvisar } from "../../components/Notificador";
import { VISTAS_FOTO } from "../../lib/formato";

const MAX_ADICIONALES = 8;

/** Fotos de un modelo: la principal (vista superior) y hasta 8 adicionales (abierta, lateral u otra) */
export function GaleriaFotos({
  producto,
  alCerrar,
  alCambiar,
}: {
  producto: Producto | null;
  alCerrar: () => void;
  alCambiar: () => void;
}) {
  const qc = useQueryClient();
  const avisar = useAvisar();
  const archivo = useRef<HTMLInputElement>(null);
  const [vista, setVista] = useState<FotoProducto["vista"]>("abierta");
  const [quitar, setQuitar] = useState<FotoProducto | null>(null);
  const [subiendo, setSubiendo] = useState(false);

  const fotos = useQuery({
    queryKey: ["producto-fotos", producto?.id],
    queryFn: async () =>
      (await api.get<FotoProducto[]>(`/productos/${producto!.id}/fotos`)).data,
    enabled: !!producto,
  });
  const adicionales = (fotos.data ?? []).filter((f) => f.id !== null).length;

  const refrescar = async () => {
    await qc.invalidateQueries({ queryKey: ["producto-fotos"] });
    alCambiar();
  };

  const subir = async (f: File) => {
    const datos = new FormData();
    datos.append("foto", f);
    setSubiendo(true);
    try {
      if (vista === "superior") {
        await api.post(`/productos/${producto!.id}/foto`, datos);
      } else {
        datos.append("vista", vista);
        await api.post(`/productos/${producto!.id}/fotos`, datos);
      }
      await refrescar();
      avisar("Foto guardada. Se publica en el catálogo si el producto es público.");
    } catch (e) {
      avisar(leerError(e).mensaje, "error");
    } finally {
      setSubiendo(false);
    }
  };

  const quitarFoto = async () => {
    try {
      await api.delete(`/productos/${producto!.id}/fotos/${quitar!.id}`);
      await refrescar();
      avisar("Foto quitada");
    } catch (e) {
      avisar(leerError(e).mensaje, "error");
    }
    setQuitar(null);
  };

  return (
    <>
      <Dialog open={!!producto} onClose={alCerrar} maxWidth="sm" fullWidth>
        <DialogTitle>Fotos de {producto?.nombre}</DialogTitle>
        <DialogContent>
          <Typography variant="body2" color="text.secondary" sx={{ mb: 2 }}>
            En el catálogo público se muestran con flechas, en este orden. La
            vista superior es la foto principal de la tarjeta.
          </Typography>
          <Box
            sx={{
              display: "grid",
              gridTemplateColumns: "repeat(auto-fill, minmax(150px, 1fr))",
              gap: 1.5,
            }}
          >
            {(fotos.data ?? []).map((f) => (
              <Box
                key={f.id ?? "principal"}
                sx={{
                  position: "relative",
                  border: `1px solid ${colores.linea}`,
                  borderRadius: 2,
                  overflow: "hidden",
                }}
              >
                <Box
                  component="img"
                  src={f.url}
                  alt={VISTAS_FOTO[f.vista]}
                  sx={{
                    width: "100%",
                    aspectRatio: "4 / 3",
                    objectFit: "cover",
                    display: "block",
                  }}
                />
                <Typography variant="caption" sx={{ display: "block", p: 0.75 }}>
                  {VISTAS_FOTO[f.vista]}
                </Typography>
                {f.id !== null && (
                  <Tooltip title="Quitar foto">
                    <IconButton
                      size="small"
                      onClick={() => setQuitar(f)}
                      aria-label={`Quitar ${VISTAS_FOTO[f.vista].toLowerCase()}`}
                      sx={{
                        position: "absolute",
                        top: 4,
                        right: 4,
                        bgcolor: "rgba(255,255,255,0.9)",
                      }}
                    >
                      <DeleteOutlineRoundedIcon fontSize="small" />
                    </IconButton>
                  </Tooltip>
                )}
              </Box>
            ))}
          </Box>
          {!fotos.isLoading && !fotos.data?.length && (
            <Typography color="text.secondary">
              Este modelo aún no tiene fotos.
            </Typography>
          )}
          <Stack
            direction="row"
            spacing={1.5}
            sx={{ mt: 3, alignItems: "flex-start" }}
          >
            <TextField
              select
              label="Vista de la foto"
              value={vista}
              onChange={(e) => setVista(e.target.value as FotoProducto["vista"])}
              sx={{ flex: 1 }}
              helperText={
                vista === "superior"
                  ? "Reemplaza la foto principal"
                  : `Adicionales: ${adicionales} de ${MAX_ADICIONALES}`
              }
            >
              {(Object.keys(VISTAS_FOTO) as FotoProducto["vista"][]).map((v) => (
                <MenuItem key={v} value={v}>
                  {VISTAS_FOTO[v]}
                </MenuItem>
              ))}
            </TextField>
            <Button
              variant="contained"
              startIcon={<UploadRoundedIcon />}
              disabled={
                subiendo || (vista !== "superior" && adicionales >= MAX_ADICIONALES)
              }
              onClick={() => archivo.current?.click()}
              sx={{ height: 56 }}
            >
              Subir foto
            </Button>
          </Stack>
          <input
            ref={archivo}
            type="file"
            hidden
            accept="image/jpeg,image/png,image/webp"
            onChange={(e) => {
              const f = e.target.files?.[0];
              if (f) subir(f);
              e.target.value = "";
            }}
          />
        </DialogContent>
        <DialogActions sx={{ px: 3, pb: 2.5 }}>
          <Button onClick={alCerrar} color="inherit">
            Cerrar
          </Button>
        </DialogActions>
      </Dialog>
      <Confirmar
        abierto={!!quitar}
        titulo="Quitar foto"
        mensaje={`Se quita la ${quitar ? VISTAS_FOTO[quitar.vista].toLowerCase() : "foto"} de ${producto?.nombre ?? "este modelo"}. Puede volver a subirla.`}
        textoConfirmar="Quitar"
        peligro
        alCerrar={() => setQuitar(null)}
        alConfirmar={quitarFoto}
      />
    </>
  );
}
