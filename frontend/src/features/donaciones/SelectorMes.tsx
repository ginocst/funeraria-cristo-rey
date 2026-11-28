import { MenuItem, TextField } from "@mui/material";
import { ahoraLocal, mesLargo } from "../../lib/formato";

/** El mes actual y los doce anteriores */
export function SelectorMes({
  valor,
  alCambiar,
}: {
  valor: string;
  alCambiar: (mes: string) => void;
}) {
  const ahora = ahoraLocal();
  return (
    <TextField
      select
      label="Mes"
      value={valor}
      onChange={(e) => alCambiar(e.target.value)}
      sx={{ width: 220 }}
    >
      {Array.from({ length: 13 }, (_, i) =>
        ahora.subtract(i, "month").format("YYYY-MM"),
      ).map((m) => (
        <MenuItem key={m} value={m}>
          {mesLargo(m)}
        </MenuItem>
      ))}
    </TextField>
  );
}
