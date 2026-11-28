import { Box, Tab, Tabs } from "@mui/material";
import { useSearchParams } from "react-router-dom";
import { Encabezado } from "../../components/Encabezado";
import { Categorias } from "./Categorias";
import { DonacionesDelMes } from "./DonacionesDelMes";
import { Organizaciones } from "./Organizaciones";

const PESTANAS = [
  { valor: "donaciones", texto: "Donaciones" },
  { valor: "organizaciones", texto: "Organizaciones" },
  { valor: "categorias", texto: "Categorías" },
];

/** Donaciones de la funeraria: registro mensual, organizaciones y sus categorías */
export default function Donaciones() {
  const [parametros, setParametros] = useSearchParams();
  const pestana =
    PESTANAS.find((p) => p.valor === parametros.get("pestana"))?.valor ??
    "donaciones";

  return (
    <>
      <Encabezado
        titulo="Donaciones"
        descripcion="Aportes a clubes deportivos, obras benéficas y otras causas, con el historial de cada organización."
      />
      <Box sx={{ borderBottom: 1, borderColor: "divider", mb: 3 }}>
        <Tabs
          value={pestana}
          onChange={(_, v) => setParametros({ pestana: v }, { replace: true })}
          variant="scrollable"
        >
          {PESTANAS.map((p) => (
            <Tab key={p.valor} value={p.valor} label={p.texto} />
          ))}
        </Tabs>
      </Box>
      {pestana === "donaciones" && <DonacionesDelMes />}
      {pestana === "organizaciones" && <Organizaciones />}
      {pestana === "categorias" && <Categorias />}
    </>
  );
}
