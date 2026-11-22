import SearchRoundedIcon from "@mui/icons-material/SearchRounded";
import { InputAdornment, TextField } from "@mui/material";
import { useEffect, useState } from "react";

/** Campo de búsqueda que espera a que el usuario deje de escribir (300 ms) */
export function Buscador({
  valor,
  alCambiar,
  placeholder = "Buscar",
  ancho = 320,
}: {
  valor: string;
  alCambiar: (v: string) => void;
  placeholder?: string;
  ancho?: number | string;
}) {
  const [texto, setTexto] = useState(valor);
  useEffect(() => setTexto(valor), [valor]);
  useEffect(() => {
    const t = setTimeout(() => texto !== valor && alCambiar(texto), 300);
    return () => clearTimeout(t);
  }, [texto, valor, alCambiar]);

  return (
    <TextField
      value={texto}
      onChange={(e) => setTexto(e.target.value)}
      placeholder={placeholder}
      sx={{ maxWidth: ancho }}
      slotProps={{
        input: {
          startAdornment: (
            <InputAdornment position="start">
              <SearchRoundedIcon fontSize="small" />
            </InputAdornment>
          ),
        },
        htmlInput: { "aria-label": placeholder },
      }}
    />
  );
}
