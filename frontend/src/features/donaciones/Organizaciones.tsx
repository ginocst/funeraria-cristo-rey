import HistoryRoundedIcon from "@mui/icons-material/HistoryRounded";
import { Chip, IconButton, Tooltip } from "@mui/material";
import { useState } from "react";
import { z } from "zod";
import type { Organizacion } from "../../api/tipos";
import { P } from "../../auth/permisos";
import { PantallaCrud } from "../../components/Crud";
import { DosLineas } from "../../components/Tabla";
import { pesos } from "../../lib/formato";
import { useCategorias } from "./consultas";
import { Historial } from "./Historial";

const opcional = (maximo: number) =>
  z
    .string()
    .trim()
    .max(maximo)
    .transform((v) => v || null);

const esquema = z.object({
  nombre: z.string().trim().min(3, "Indique el nombre").max(120),
  categoria_id: z.number({ message: "Seleccione la categoría" }),
  contacto: opcional(120),
  telefono: opcional(30),
  email: z
    .union([z.literal(""), z.email("Correo no válido").max(150)])
    .transform((v) => v || null),
  notas: opcional(250),
  activo: z.boolean(),
});

/** Organizaciones que reciben donaciones; al elegir una se ve su historial */
export function Organizaciones() {
  const categorias = useCategorias();
  const [historial, setHistorial] = useState<Organizacion | null>(null);

  return (
    <>
      <PantallaCrud<Organizacion, typeof esquema>
        titulo="Organizaciones"
        singular="Organización"
        femenino
        sinEncabezado
        recurso="organizaciones"
        invalidar={["donaciones"]}
        permisos={{
          crear: P.donaciones,
          editar: P.donaciones,
          eliminar: P.donaciones,
        }}
        alClicFila={setHistorial}
        accionesFila={(o) => (
          <Tooltip title="Historial de donaciones">
            <IconButton
              size="small"
              onClick={() => setHistorial(o)}
              aria-label={`Historial de ${o.nombre}`}
            >
              <HistoryRoundedIcon fontSize="small" />
            </IconButton>
          </Tooltip>
        )}
        esquema={esquema}
        nuevo={{
          nombre: "",
          categoria_id: undefined,
          contacto: "",
          telefono: "",
          email: "",
          notas: "",
          activo: true,
        }}
        aFormulario={(o) => ({
          nombre: o.nombre,
          categoria_id: o.categoria_id,
          contacto: o.contacto ?? "",
          telefono: o.telefono ?? "",
          email: o.email ?? "",
          notas: o.notas ?? "",
          activo: o.activo,
        })}
        campos={[
          { nombre: "nombre", etiqueta: "Nombre" },
          {
            nombre: "categoria_id",
            etiqueta: "Categoría",
            tipo: "seleccion",
            opciones: (categorias.data ?? []).map((c) => ({
              valor: c.id,
              etiqueta: c.nombre,
            })),
          },
          {
            nombre: "contacto",
            etiqueta: "Persona de contacto (opcional)",
          },
          {
            nombre: "telefono",
            etiqueta: "Teléfono (opcional)",
            ancho: "medio",
          },
          {
            nombre: "email",
            etiqueta: "Correo (opcional)",
            tipo: "email",
            ancho: "medio",
          },
          { nombre: "notas", etiqueta: "Notas (opcional)", tipo: "area" },
          { nombre: "activo", etiqueta: "Activa", tipo: "interruptor" },
        ]}
        columnas={[
          {
            id: "nombre",
            titulo: "Organización",
            render: (o) => (
              <DosLineas principal={o.nombre} secundaria={o.contacto} />
            ),
          },
          {
            id: "categoria",
            titulo: "Categoría",
            ocultarEnMovil: true,
            render: (o) => o.categoria?.nombre,
          },
          {
            id: "cantidad",
            titulo: "Donaciones",
            alinear: "right",
            ocultarEnMovil: true,
            render: (o) => o.donaciones_count,
          },
          {
            id: "total",
            titulo: "Total donado",
            alinear: "right",
            render: (o) => pesos(o.total_donado),
          },
          {
            id: "estado",
            titulo: "",
            render: (o) =>
              !o.activo && (
                <Chip size="small" variant="outlined" label="Inactiva" />
              ),
          },
        ]}
      />
      {historial && (
        <Historial
          organizacion={historial}
          alCerrar={() => setHistorial(null)}
        />
      )}
    </>
  );
}
