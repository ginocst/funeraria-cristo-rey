import { Chip, MenuItem, Stack, TextField, Typography } from "@mui/material";
import { useState } from "react";
import { z } from "zod";
import type { Convenio } from "../../api/tipos";
import { colores } from "../../app/tema";
import { P } from "../../auth/permisos";
import { PantallaCrud } from "../../components/Crud";
import { DosLineas } from "../../components/Tabla";
import { ahoraLocal, fecha } from "../../lib/formato";

export const TIPOS_CONVENIO: Record<Convenio["tipo"], string> = {
  descuento: "Descuento",
  cuota_mortuoria: "Cuota mortuoria",
  institucional: "Institucional",
};

const esquema = z
  .object({
    nombre: z.string().trim().min(3, "Indique el nombre").max(120),
    institucion: z.string().trim().min(3, "Indique la institución").max(160),
    tipo: z.enum(["descuento", "cuota_mortuoria", "institucional"]),
    descuento_pct: z
      .number({ message: "Indique el porcentaje" })
      .min(0)
      .max(100),
    vigencia_desde: z.string().min(1, "Indique la fecha"),
    vigencia_hasta: z.string(),
    descripcion: z.string().max(2000),
    contacto: z.string().max(160),
    activo: z.boolean(),
  })
  .refine((d) => !d.vigencia_hasta || d.vigencia_hasta >= d.vigencia_desde, {
    path: ["vigencia_hasta"],
    message: "Debe ser posterior al inicio",
  })
  .transform((d) => ({
    ...d,
    vigencia_hasta: d.vigencia_hasta || null,
    descripcion: d.descripcion || null,
    contacto: d.contacto || null,
  }));

/** Lista de convenios con su vigencia y la cantidad de clientes asociados */
export default function Convenios() {
  const [estado, setEstado] = useState("");
  const [tipo, setTipo] = useState("");

  return (
    <PantallaCrud<Convenio, typeof esquema>
      titulo="Convenios"
      descripcion="Acuerdos con instituciones: descuentos, cuotas mortuorias y convenios institucionales. Los vigentes se muestran en el catálogo público."
      singular="Convenio"
      recurso="convenios"
      buscar="Buscar convenio o institución"
      permisos={{
        crear: P.conveniosGestionar,
        editar: P.conveniosGestionar,
        eliminar: P.conveniosGestionar,
      }}
      parametros={{ estado: estado || undefined, tipo: tipo || undefined }}
      filtros={
        <Stack direction="row" spacing={1} sx={{ alignItems: "center" }}>
          <Chip
            label="Solo vigentes"
            clickable
            color={estado ? "primary" : "default"}
            variant={estado ? "filled" : "outlined"}
            onClick={() => setEstado(estado ? "" : "vigentes")}
          />
          <TextField
            select
            label="Tipo"
            value={tipo}
            onChange={(e) => setTipo(e.target.value)}
            sx={{ width: 190 }}
          >
            <MenuItem value="">Todos</MenuItem>
            {Object.entries(TIPOS_CONVENIO).map(([k, v]) => (
              <MenuItem key={k} value={k}>
                {v}
              </MenuItem>
            ))}
          </TextField>
        </Stack>
      }
      esquema={esquema}
      nuevo={{
        nombre: "",
        institucion: "",
        tipo: "descuento",
        descuento_pct: 10,
        vigencia_desde: ahoraLocal().format("YYYY-MM-DD"),
        vigencia_hasta: "",
        descripcion: "",
        contacto: "",
        activo: true,
      }}
      aFormulario={(c) => ({
        ...c,
        descuento_pct: Number(c.descuento_pct),
        vigencia_hasta: c.vigencia_hasta ?? "",
        descripcion: c.descripcion ?? "",
        contacto: c.contacto ?? "",
      })}
      campos={[
        { nombre: "nombre", etiqueta: "Nombre del convenio", ancho: "medio" },
        { nombre: "institucion", etiqueta: "Institución", ancho: "medio" },
        {
          nombre: "tipo",
          etiqueta: "Tipo",
          tipo: "seleccion",
          ancho: "medio",
          opciones: Object.entries(TIPOS_CONVENIO).map(([valor, etiqueta]) => ({
            valor,
            etiqueta,
          })),
        },
        {
          nombre: "descuento_pct",
          etiqueta: "Descuento",
          tipo: "porcentaje",
          ancho: "medio",
          ayuda:
            "Use 0 si el convenio no descuenta (por ejemplo, cuota mortuoria)",
        },
        {
          nombre: "vigencia_desde",
          etiqueta: "Vigente desde",
          tipo: "fecha",
          ancho: "medio",
        },
        {
          nombre: "vigencia_hasta",
          etiqueta: "Vigente hasta (opcional)",
          tipo: "fecha",
          ancho: "medio",
        },
        { nombre: "contacto", etiqueta: "Contacto en la institución" },
        {
          nombre: "descripcion",
          etiqueta: "Descripción y condiciones",
          tipo: "area",
        },
        { nombre: "activo", etiqueta: "Convenio activo", tipo: "interruptor" },
      ]}
      columnas={[
        {
          id: "nombre",
          titulo: "Convenio",
          render: (c) => (
            <DosLineas principal={c.nombre} secundaria={c.institucion} />
          ),
        },
        { id: "tipo", titulo: "Tipo", render: (c) => TIPOS_CONVENIO[c.tipo] },
        {
          id: "desc",
          titulo: "Descuento",
          alinear: "right",
          render: (c) =>
            Number(c.descuento_pct) > 0 ? `${Number(c.descuento_pct)} %` : "—",
        },
        {
          id: "vig",
          titulo: "Vigencia",
          ocultarEnMovil: true,
          render: (c) => (
            <DosLineas
              principal={`Desde ${fecha(c.vigencia_desde)}`}
              secundaria={
                c.vigencia_hasta
                  ? `Hasta ${fecha(c.vigencia_hasta)}`
                  : "Sin término"
              }
            />
          ),
        },
        {
          id: "estado",
          titulo: "Estado",
          render: (c) => (
            <Chip
              size="small"
              label={c.vigente ? "Vigente" : c.activo ? "Vencido" : "Inactivo"}
              sx={{
                bgcolor: c.vigente ? "#E3F0E8" : "#EFECEE",
                color: c.vigente ? colores.ok : colores.piedra,
              }}
            />
          ),
        },
        {
          id: "clientes",
          titulo: "Clientes",
          alinear: "right",
          render: (c) => (
            <Typography variant="body2">{c.clientes_count ?? 0}</Typography>
          ),
        },
      ]}
    />
  );
}
