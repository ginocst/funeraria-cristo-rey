import { z } from "zod";
import type { CategoriaOrganizacion } from "../../api/tipos";
import { P } from "../../auth/permisos";
import { PantallaCrud } from "../../components/Crud";

const esquema = z.object({
  nombre: z.string().trim().min(3, "Indique el nombre").max(80),
  descripcion: z
    .string()
    .trim()
    .max(250)
    .transform((v) => v || null),
});

/** Categorías de las organizaciones (deportiva, benéfica, etc.) */
export function Categorias() {
  return (
    <PantallaCrud<CategoriaOrganizacion, typeof esquema>
      titulo="Categorías"
      singular="Categoría"
      femenino
      sinEncabezado
      recurso="categorias-organizacion"
      invalidar={["organizaciones", "donaciones"]}
      permisos={{
        crear: P.donaciones,
        editar: P.donaciones,
        eliminar: P.donaciones,
      }}
      esquema={esquema}
      nuevo={{ nombre: "", descripcion: "" }}
      aFormulario={(c) => ({
        nombre: c.nombre,
        descripcion: c.descripcion ?? "",
      })}
      campos={[
        { nombre: "nombre", etiqueta: "Nombre" },
        { nombre: "descripcion", etiqueta: "Descripción (opcional)" },
      ]}
      columnas={[
        { id: "nombre", titulo: "Categoría", render: (c) => c.nombre },
        {
          id: "descripcion",
          titulo: "Descripción",
          ocultarEnMovil: true,
          render: (c) => c.descripcion ?? "—",
        },
        {
          id: "organizaciones",
          titulo: "Organizaciones",
          alinear: "right",
          render: (c) => c.organizaciones_count,
        },
      ]}
    />
  );
}
