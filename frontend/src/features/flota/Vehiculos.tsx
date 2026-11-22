import { Box, Chip, Stack, Tab, Tabs, Typography } from "@mui/material";
import { useQuery } from "@tanstack/react-query";
import { Link as RouterLink, useSearchParams } from "react-router-dom";
import { z } from "zod";
import { api } from "../../api/cliente";
import type { EstadoSolicitud, Vehiculo } from "../../api/tipos";
import { colores } from "../../app/tema";
import { P } from "../../auth/permisos";
import { PantallaCrud } from "../../components/Crud";
import { Encabezado } from "../../components/Encabezado";
import { EstadoSolicitudChip } from "../../components/Estados";
import { Seccion } from "../../components/Seccion";
import { DosLineas } from "../../components/Tabla";
import { aLocal, capitalizar, hora } from "../../lib/formato";

const TIPOS = {
  carroza: "Carroza",
  instalacion: "Instalación",
  apoyo: "Apoyo",
};

const esquema = z
  .object({
    nombre: z.string().trim().min(3, "Indique un nombre").max(80),
    tipo: z.enum(["carroza", "instalacion", "apoyo"]),
    origen: z.enum(["propio", "externo"]),
    patente: z
      .string()
      .trim()
      .toUpperCase()
      .max(10)
      .refine(
        (v) => !v || /^[A-Z]{2,4}\d{2,4}$/.test(v.replace(/[-\s]/g, "")),
        "Patente no válida (ej.: KXTR21)",
      ),
    marca: z.string().max(60),
    modelo: z.string().max(60),
    anio: z
      .union([z.number().int().min(1950).max(2100), z.literal("")])
      .transform((v) => (v === "" ? null : v)),
    proveedor_externo: z.string().max(160),
    notas: z.string().max(1000),
    activo: z.boolean(),
  })
  .refine(
    (d) => d.origen === "propio" || d.proveedor_externo.trim().length > 2,
    { path: ["proveedor_externo"], message: "Indique a quién se arrienda" },
  )
  .transform((d) => ({
    ...d,
    patente: d.patente.replace(/[-\s]/g, "") || null,
    proveedor_externo: d.proveedor_externo || null,
    marca: d.marca || null,
    modelo: d.modelo || null,
    notas: d.notas || null,
  }));

type FilaAgenda = {
  id: number;
  vehiculo_id: number;
  vehiculo: string;
  origen: string;
  inicio: string;
  fin: string;
  solicitud_id: number;
  estado: EstadoSolicitud;
  codigo: string;
  lugar_sepultura: string;
};

function Agenda() {
  const { data } = useQuery({
    queryKey: ["agenda-vehiculos"],
    queryFn: async () =>
      (await api.get<FilaAgenda[]>("/vehiculos/agenda")).data,
  });
  const porVehiculo = new Map<string, FilaAgenda[]>();
  data?.forEach((f) =>
    porVehiculo.set(f.vehiculo, [...(porVehiculo.get(f.vehiculo) ?? []), f]),
  );

  return (
    <Stack spacing={3}>
      {data?.length === 0 && (
        <Seccion>
          <Typography color="text.secondary">
            No hay vehículos comprometidos en las próximas dos semanas.
          </Typography>
        </Seccion>
      )}
      {[...porVehiculo.entries()].map(([vehiculo, filas]) => (
        <Seccion
          key={vehiculo}
          titulo={vehiculo}
          descripcion={
            filas[0].origen === "externo"
              ? "Arrendado: no se controla el traslape"
              : `${filas.length} compromisos`
          }
        >
          <Stack spacing={1.25}>
            {filas.map((f) => (
              <Stack
                key={f.id}
                direction={{ xs: "column", sm: "row" }}
                spacing={2}
                component={RouterLink}
                to={`/solicitudes/${f.solicitud_id}`}
                sx={{
                  alignItems: { sm: "center" },
                  p: 1.5,
                  borderRadius: 2,
                  border: `1px solid ${colores.linea}`,
                  textDecoration: "none",
                  color: "inherit",
                  "&:hover": {
                    borderColor: colores.rosaCeniza,
                    bgcolor: colores.vinoTenue,
                  },
                }}
              >
                <Box sx={{ width: 220 }}>
                  <Typography variant="body2" sx={{ fontWeight: 600 }}>
                    {capitalizar(aLocal(f.inicio)!.format("dddd D [de] MMMM"))}
                  </Typography>
                  <Typography variant="body2" color="text.secondary">
                    {hora(f.inicio)} a {hora(f.fin)}
                  </Typography>
                </Box>
                <Box sx={{ flex: 1 }}>
                  <DosLineas
                    principal={f.codigo}
                    secundaria={`Hacia ${f.lugar_sepultura}`}
                  />
                </Box>
                <EstadoSolicitudChip estado={f.estado} />
              </Stack>
            ))}
          </Stack>
        </Seccion>
      ))}
    </Stack>
  );
}

/** Flota propia y arrendada. La restricción de la base impide asignar un vehículo propio a dos servicios a la vez */
export default function Vehiculos() {
  const [params, setParams] = useSearchParams();
  const pestana = params.get("pestana") ?? "vehiculos";
  return (
    <>
      <Encabezado
        titulo="Vehículos"
        descripcion="Carrozas, furgones de instalación y vehículos de apoyo. Los arrendados se registran como externos."
      />
      <Tabs
        value={pestana}
        onChange={(_, v) => setParams({ pestana: v }, { replace: true })}
        sx={{ mb: 2.5, borderBottom: 1, borderColor: "divider" }}
      >
        <Tab value="vehiculos" label="Flota" />
        <Tab value="agenda" label="Agenda de 2 semanas" />
      </Tabs>
      {pestana === "agenda" ? (
        <Agenda />
      ) : (
        <PantallaCrud<Vehiculo, typeof esquema>
          sinEncabezado
          titulo="Vehículos"
          singular="Vehículo"
          recurso="vehiculos"
          buscar="Buscar por nombre o patente"
          permisos={{
            crear: P.vehiculosCrear,
            editar: P.vehiculosEditar,
            eliminar: P.vehiculosEliminar,
          }}
          esquema={esquema}
          nuevo={{
            nombre: "",
            tipo: "carroza",
            origen: "propio",
            patente: "",
            marca: "",
            modelo: "",
            anio: "",
            proveedor_externo: "",
            notas: "",
            activo: true,
          }}
          aFormulario={(v) => ({
            nombre: v.nombre,
            tipo: v.tipo,
            origen: v.origen,
            patente: v.patente ?? "",
            marca: v.marca ?? "",
            modelo: v.modelo ?? "",
            anio: v.anio ?? "",
            proveedor_externo: v.proveedor_externo ?? "",
            notas: v.notas ?? "",
            activo: v.activo,
          })}
          campos={[
            { nombre: "nombre", etiqueta: "Nombre interno", ancho: "medio" },
            {
              nombre: "tipo",
              etiqueta: "Tipo",
              tipo: "seleccion",
              ancho: "medio",
              opciones: Object.entries(TIPOS).map(([valor, etiqueta]) => ({
                valor,
                etiqueta,
              })),
            },
            {
              nombre: "origen",
              etiqueta: "Origen",
              tipo: "seleccion",
              ancho: "medio",
              opciones: [
                { valor: "propio", etiqueta: "Propio" },
                { valor: "externo", etiqueta: "Arrendado (externo)" },
              ],
            },
            {
              nombre: "proveedor_externo",
              etiqueta: "Se arrienda a",
              ancho: "medio",
              mostrar: (v) => v.origen === "externo",
            },
            {
              nombre: "patente",
              etiqueta: "Patente",
              ancho: "tercio",
              mostrar: (v) => v.origen === "propio",
            },
            { nombre: "marca", etiqueta: "Marca", ancho: "tercio" },
            { nombre: "modelo", etiqueta: "Modelo", ancho: "tercio" },
            {
              nombre: "anio",
              etiqueta: "Año",
              tipo: "numero",
              ancho: "tercio",
            },
            {
              nombre: "notas",
              etiqueta: "Notas (revisión técnica, mantenciones)",
              tipo: "area",
            },
            {
              nombre: "activo",
              etiqueta: "Disponible para asignar",
              tipo: "interruptor",
            },
          ]}
          columnas={[
            {
              id: "nombre",
              titulo: "Vehículo",
              render: (v) => (
                <DosLineas
                  principal={v.nombre}
                  secundaria={
                    [v.marca, v.modelo, v.anio].filter(Boolean).join(" ") ||
                    undefined
                  }
                />
              ),
            },
            { id: "tipo", titulo: "Tipo", render: (v) => TIPOS[v.tipo] },
            {
              id: "patente",
              titulo: "Patente",
              render: (v) => v.patente ?? "—",
            },
            {
              id: "origen",
              titulo: "Origen",
              render: (v) =>
                v.origen === "externo" ? (
                  <Chip
                    size="small"
                    variant="outlined"
                    label={`Arriendo: ${v.proveedor_externo}`}
                  />
                ) : (
                  "Propio"
                ),
            },
            {
              id: "activo",
              titulo: "Estado",
              render: (v) => (
                <Chip
                  size="small"
                  label={v.activo ? "Disponible" : "Fuera de servicio"}
                  sx={{
                    bgcolor: v.activo ? "#E3F0E8" : "#EFECEE",
                    color: v.activo ? colores.ok : colores.piedra,
                  }}
                />
              ),
            },
          ]}
        />
      )}
    </>
  );
}
