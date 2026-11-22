import { MenuItem, TextField } from "@mui/material";
import { useQuery } from "@tanstack/react-query";
import { useState } from "react";
import { z } from "zod";
import { api, type Pagina } from "../../api/cliente";
import type { Gasto, Proveedor, Sucursal } from "../../api/tipos";
import { P } from "../../auth/permisos";
import { PantallaCrud } from "../../components/Crud";
import { DosLineas } from "../../components/Tabla";
import { ahoraLocal, fecha, mesLargo, pesos } from "../../lib/formato";

const esquema = z.object({
  sucursal_id: z.number({ message: "Seleccione la sucursal" }),
  proveedor_id: z
    .union([z.number(), z.literal("")])
    .transform((v) => (v === "" ? null : v)),
  categoria: z.string().min(1, "Seleccione la categoría"),
  descripcion: z.string().trim().min(3, "Describa el gasto").max(250),
  monto: z
    .number({ message: "Indique el monto" })
    .int()
    .min(1, "Debe ser mayor que cero"),
  fecha: z.string().min(1, "Indique la fecha"),
});

/** Gastos del negocio para el control mensual (resultado = ingresos − gastos) */
export default function Gastos() {
  const ahora = ahoraLocal();
  const meses = Array.from({ length: 13 }, (_, i) =>
    ahora.subtract(i, "month").format("YYYY-MM"),
  );
  const [mes, setMes] = useState(meses[0]);
  const categorias = useQuery({
    queryKey: ["gastos", "categorias"],
    queryFn: async () => (await api.get<string[]>("/gastos/categorias")).data,
  });
  const sucursales = useQuery({
    queryKey: ["sucursales", "todas"],
    queryFn: async () =>
      (
        await api.get<Pagina<Sucursal>>("/sucursales", {
          params: { por_pagina: 50 },
        })
      ).data.data,
  });
  const proveedores = useQuery({
    queryKey: ["proveedores", "todos"],
    queryFn: async () =>
      (
        await api.get<Pagina<Proveedor>>("/proveedores", {
          params: { por_pagina: 100 },
        })
      ).data.data,
  });

  return (
    <PantallaCrud<Gasto, typeof esquema>
      titulo="Gastos"
      descripcion="Combustible, mantenciones, compras, remuneraciones y otros. Se descuentan de los ingresos en el panel y en el reporte mensual."
      singular="Gasto"
      recurso="gastos"
      buscar="Buscar en la descripción"
      permisos={{ crear: P.gastos, editar: P.gastos, eliminar: P.gastos }}
      parametros={{ mes }}
      filtros={
        <TextField
          select
          label="Mes"
          value={mes}
          onChange={(e) => setMes(e.target.value)}
          sx={{ width: 220 }}
        >
          {meses.map((m) => (
            <MenuItem key={m} value={m}>
              {mesLargo(m)}
            </MenuItem>
          ))}
        </TextField>
      }
      esquema={esquema}
      nuevo={{
        sucursal_id: 1,
        proveedor_id: "",
        categoria: "Combustible",
        descripcion: "",
        monto: 0,
        fecha: ahora.format("YYYY-MM-DD"),
      }}
      aFormulario={(g) => ({
        sucursal_id: g.sucursal_id,
        proveedor_id: g.proveedor_id ?? "",
        categoria: g.categoria,
        descripcion: g.descripcion,
        monto: g.monto,
        fecha: g.fecha.slice(0, 10),
      })}
      campos={[
        {
          nombre: "categoria",
          etiqueta: "Categoría",
          tipo: "seleccion",
          ancho: "medio",
          opciones: (categorias.data ?? []).map((c) => ({
            valor: c,
            etiqueta: c,
          })),
        },
        { nombre: "fecha", etiqueta: "Fecha", tipo: "fecha", ancho: "medio" },
        { nombre: "descripcion", etiqueta: "Descripción" },
        { nombre: "monto", etiqueta: "Monto", tipo: "pesos", ancho: "medio" },
        {
          nombre: "sucursal_id",
          etiqueta: "Sucursal",
          tipo: "seleccion",
          ancho: "medio",
          opciones: (sucursales.data ?? []).map((s) => ({
            valor: s.id,
            etiqueta: s.nombre,
          })),
        },
        {
          nombre: "proveedor_id",
          etiqueta: "Proveedor (opcional)",
          tipo: "seleccion",
          opciones: [
            { valor: "", etiqueta: "Sin proveedor" },
            ...(proveedores.data ?? []).map((p) => ({
              valor: p.id,
              etiqueta: p.nombre,
            })),
          ],
        },
      ]}
      columnas={[
        {
          id: "fecha",
          titulo: "Fecha",
          render: (g) => fecha(g.fecha.slice(0, 10)),
        },
        { id: "cat", titulo: "Categoría", render: (g) => g.categoria },
        {
          id: "desc",
          titulo: "Descripción",
          render: (g) => (
            <DosLineas
              principal={g.descripcion}
              secundaria={g.proveedor?.nombre}
            />
          ),
        },
        {
          id: "suc",
          titulo: "Sucursal",
          ocultarEnMovil: true,
          render: (g) => g.sucursal?.nombre,
        },
        {
          id: "monto",
          titulo: "Monto",
          alinear: "right",
          render: (g) => pesos(g.monto),
        },
      ]}
    />
  );
}
