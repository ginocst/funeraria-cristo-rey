import CloseRoundedIcon from "@mui/icons-material/CloseRounded";
import DownloadRoundedIcon from "@mui/icons-material/DownloadRounded";
import {
  Alert,
  Box,
  Button,
  CircularProgress,
  Dialog,
  DialogContent,
  DialogTitle,
  IconButton,
  Stack,
} from "@mui/material";
import { useEffect, useState } from "react";
import { descargarBlob, guardarBlob, leerError } from "../api/cliente";

type Props = {
  abierto: boolean;
  titulo: string;
  ruta: string | null;
  nombreArchivo: string;
  alCerrar: () => void;
};

/**
 * Muestra un PDF generado por la API (orden de servicio, comprobante, reporte)
 * dentro de la aplicación y permite descargarlo. El archivo se pide con la
 * sesión del usuario, por eso se descarga como Blob y no con un enlace directo
 */
export function VisorPdf({
  abierto,
  titulo,
  ruta,
  nombreArchivo,
  alCerrar,
}: Props) {
  const [url, setUrl] = useState<string | null>(null);
  const [blob, setBlob] = useState<Blob | null>(null);
  const [error, setError] = useState<string | null>(null);

  useEffect(() => {
    if (!abierto || !ruta) return;
    let vigente = true;
    let creada: string | null = null;
    setError(null);
    setUrl(null);
    descargarBlob(ruta)
      .then((b) => {
        if (!vigente) return;
        const pdf =
          b.type === "application/pdf"
            ? b
            : new Blob([b], { type: "application/pdf" });
        creada = URL.createObjectURL(pdf);
        setBlob(pdf);
        setUrl(creada);
      })
      .catch((e) => vigente && setError(leerError(e).mensaje));
    return () => {
      vigente = false;
      if (creada) URL.revokeObjectURL(creada);
    };
  }, [abierto, ruta]);

  return (
    <Dialog
      open={abierto}
      onClose={alCerrar}
      fullWidth
      maxWidth="lg"
      slotProps={{ paper: { sx: { height: "92vh" } } }}
    >
      <DialogTitle
        component="div"
        sx={{ display: "flex", alignItems: "center", gap: 2, pr: 1.5 }}
      >
        <Box
          sx={{
            flex: 1,
            minWidth: 0,
            overflow: "hidden",
            textOverflow: "ellipsis",
            whiteSpace: "nowrap",
          }}
        >
          {titulo}
        </Box>
        <Stack direction="row" spacing={1}>
          <Button
            variant="contained"
            startIcon={<DownloadRoundedIcon />}
            disabled={!blob}
            onClick={() => blob && guardarBlob(blob, nombreArchivo)}
          >
            Descargar PDF
          </Button>
          <IconButton onClick={alCerrar} aria-label="Cerrar vista previa">
            <CloseRoundedIcon />
          </IconButton>
        </Stack>
      </DialogTitle>
      <DialogContent sx={{ p: 0, bgcolor: "#525659", display: "flex" }}>
        {error ? (
          <Alert severity="error" sx={{ m: 3, alignSelf: "flex-start" }}>
            {error}
          </Alert>
        ) : url ? (
          <Box
            component="iframe"
            title={titulo}
            src={url}
            sx={{ border: 0, width: "100%", height: "100%" }}
          />
        ) : (
          <CircularProgress sx={{ m: "auto", color: "#fff" }} />
        )}
      </DialogContent>
    </Dialog>
  );
}
