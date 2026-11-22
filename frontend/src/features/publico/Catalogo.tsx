import SearchRoundedIcon from "@mui/icons-material/SearchRounded";
import {
  Box,
  Button,
  Chip,
  InputAdornment,
  MenuItem,
  Skeleton,
  Slider,
  Stack,
  TextField,
  Typography,
} from "@mui/material";
import { keepPreviousData, useQuery } from "@tanstack/react-query";
import { useEffect, useMemo, useState } from "react";
import { api } from "../../api/cliente";
import type { ProductoPublico } from "../../api/tipos";
import { colores, fuenteTitulos } from "../../app/tema";
import { pesos } from "../../lib/formato";
import { MarcoPublico } from "./MarcoPublico";
import { DetalleProducto, TarjetaProducto } from "./TarjetaProducto";

type Filtros = {
  tipos: string[];
  materiales: string[];
  colores: string[];
  precio_min: number;
  precio_max: number;
};

const CATEGORIAS = [
  { valor: "", texto: "Todo el catálogo" },
  { valor: "ataud", texto: "Ataúdes" },
  { valor: "urna", texto: "Urnas personalizadas" },
  { valor: "urna_cremacion", texto: "Urnas de cremación" },
  { valor: "infantil", texto: "Modelos infantiles" },
];

const ORDENES = [
  { valor: "relevancia", texto: "Destacados" },
  { valor: "precio_asc", texto: "Precio: menor a mayor" },
  { valor: "precio_desc", texto: "Precio: mayor a menor" },
  { valor: "nombre", texto: "Nombre (A a Z)" },
];

/**
 * Catálogo público. Muestra modelos, materiales y precios;
 */
export default function Catalogo() {
  const [categoria, setCategoria] = useState("");
  const [texto, setTexto] = useState("");
  const [q, setQ] = useState("");
  const [material, setMaterial] = useState("");
  const [orden, setOrden] = useState("relevancia");
  const [precio, setPrecio] = useState<number[] | null>(null);
  const [detalle, setDetalle] = useState<ProductoPublico | null>(null);

  useEffect(() => {
    const t = setTimeout(() => setQ(texto.trim()), 300);
    return () => clearTimeout(t);
  }, [texto]);

  const filtros = useQuery({
    queryKey: ["publico", "filtros"],
    queryFn: async () =>
      (await api.get<Filtros>("/publico/catalogo/filtros")).data,
  });
  const parametros = useMemo(
    () => ({
      q: q || undefined,
      tipo: categoria && categoria !== "infantil" ? categoria : undefined,
      infantil: categoria === "infantil" ? 1 : undefined,
      material: material || undefined,
      orden,
      precio_min: precio?.[0],
      precio_max: precio?.[1],
    }),
    [q, categoria, material, orden, precio],
  );
  const productos = useQuery({
    queryKey: ["publico", "catalogo", parametros],
    queryFn: async () =>
      (
        await api.get<ProductoPublico[]>("/publico/catalogo", {
          params: parametros,
        })
      ).data,
    placeholderData: keepPreviousData,
  });

  const rango = filtros.data
    ? [filtros.data.precio_min, filtros.data.precio_max]
    : [0, 0];
  const limpiar = () => {
    setCategoria("");
    setTexto("");
    setMaterial("");
    setOrden("relevancia");
    setPrecio(null);
  };
  const hayFiltros = !!(
    categoria ||
    q ||
    material ||
    precio ||
    orden !== "relevancia"
  );

  return (
    <MarcoPublico>
      <Box
        sx={{
          maxWidth: 1240,
          mx: "auto",
          px: { xs: 2, md: 4 },
          pt: { xs: 4, md: 7 },
          pb: 8,
        }}
      >
        <Box sx={{ maxWidth: 720, mb: { xs: 4, md: 5 } }}>
          <Typography
            component="h1"
            sx={{
              fontFamily: fuenteTitulos,
              fontWeight: 600,
              fontSize: { xs: "2.4rem", md: "3.4rem" },
              lineHeight: 1.05,
              color: colores.vinoProfundo,
            }}
          >
            Catálogo de urnas y ataúdes
          </Typography>
          <Typography
            sx={{
              mt: 2,
              fontSize: "1.05rem",
              color: colores.piedra,
              lineHeight: 1.6,
            }}
          >
            Modelos disponibles con precio de referencia. Nuestro equipo le
            orienta en la elección y le explica qué incluye cada servicio.
          </Typography>
        </Box>

        {/* Filtros */}
        <Stack
          direction="row"
          sx={{ flexWrap: "wrap", gap: 1, mb: 2.5 }}
          role="tablist"
          aria-label="Categorías"
        >
          {CATEGORIAS.map((c) => (
            <Chip
              key={c.valor}
              role="tab"
              aria-selected={categoria === c.valor}
              label={c.texto}
              clickable
              onClick={() => setCategoria(c.valor)}
              sx={{
                px: 0.5,
                height: 36,
                fontSize: "0.9rem",
                borderRadius: 18,
                bgcolor: categoria === c.valor ? colores.vino : "#fff",
                color: categoria === c.valor ? "#fff" : colores.tinta,
                border: `1px solid ${categoria === c.valor ? colores.vino : colores.linea}`,
                "&:hover": {
                  bgcolor:
                    categoria === c.valor
                      ? colores.vinoOscuro
                      : colores.vinoTenue,
                },
              }}
            />
          ))}
        </Stack>
        <Box
          sx={{
            display: "grid",
            gap: 2,
            gridTemplateColumns: {
              xs: "1fr",
              sm: "1fr 1fr",
              lg: "2fr 1fr 1fr 1.4fr",
            },
            alignItems: "center",
            mb: 4,
            p: 2,
            bgcolor: "#fff",
            borderRadius: 3,
            border: `1px solid ${colores.linea}`,
          }}
        >
          <TextField
            value={texto}
            onChange={(e) => setTexto(e.target.value)}
            placeholder="Buscar por nombre o material"
            slotProps={{
              input: {
                startAdornment: (
                  <InputAdornment position="start">
                    <SearchRoundedIcon fontSize="small" />
                  </InputAdornment>
                ),
              },
              htmlInput: { "aria-label": "Buscar en el catálogo" },
            }}
          />
          <TextField
            select
            label="Material"
            value={material}
            onChange={(e) => setMaterial(e.target.value)}
          >
            <MenuItem value="">Todos</MenuItem>
            {filtros.data?.materiales.map((m) => (
              <MenuItem key={m} value={m}>
                {m}
              </MenuItem>
            ))}
          </TextField>
          <TextField
            select
            label="Ordenar por"
            value={orden}
            onChange={(e) => setOrden(e.target.value)}
          >
            {ORDENES.map((o) => (
              <MenuItem key={o.valor} value={o.valor}>
                {o.texto}
              </MenuItem>
            ))}
          </TextField>
          <Box sx={{ px: 1 }}>
            <Typography variant="caption" color="text.secondary">
              Precio: {pesos((precio ?? rango)[0])} a{" "}
              {pesos((precio ?? rango)[1])}
            </Typography>
            {filtros.data && (
              <Slider
                size="small"
                min={rango[0]}
                max={rango[1]}
                step={10000}
                value={precio ?? rango}
                onChange={(_, v) => setPrecio(v as number[])}
                getAriaValueText={(v) => pesos(v)}
                getAriaLabel={(i) =>
                  i === 0 ? "Precio mínimo" : "Precio máximo"
                }
                sx={{ color: colores.vino, py: 1 }}
              />
            )}
          </Box>
        </Box>

        <Stack
          direction="row"
          sx={{ justifyContent: "space-between", alignItems: "center", mb: 2 }}
        >
          <Typography variant="body2" color="text.secondary">
            {productos.data
              ? `${productos.data.length} ${productos.data.length === 1 ? "modelo" : "modelos"}`
              : " "}
          </Typography>
          {hayFiltros && (
            <Button size="small" onClick={limpiar}>
              Quitar filtros
            </Button>
          )}
        </Stack>

        {/* Productos */}
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
          {productos.isLoading &&
            Array.from({ length: 6 }, (_, i) => (
              <Skeleton key={i} variant="rounded" height={340} />
            ))}
          {productos.data?.map((p) => (
            <TarjetaProducto
              key={p.id}
              producto={p}
              onClick={() => setDetalle(p)}
            />
          ))}
        </Box>
        {productos.data?.length === 0 && (
          <Box sx={{ textAlign: "center", py: 8 }}>
            <Typography sx={{ fontFamily: fuenteTitulos, fontSize: "1.6rem" }}>
              No hay modelos con esos filtros
            </Typography>
            <Button sx={{ mt: 1 }} onClick={limpiar}>
              Ver todo el catálogo
            </Button>
          </Box>
        )}
      </Box>

      <DetalleProducto producto={detalle} onClose={() => setDetalle(null)} />
    </MarcoPublico>
  );
}
