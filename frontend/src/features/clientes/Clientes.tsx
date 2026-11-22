import { Chip, Skeleton, Stack, Typography } from "@mui/material";
import { useQuery } from "@tanstack/react-query";
import { useNavigate, useParams } from "react-router-dom";
import { z } from "zod";
import { api, type Pagina } from "../../api/cliente";
import type { Cliente, Convenio, EstadoExpediente } from "../../api/tipos";
import { P } from "../../auth/permisos";
import { PantallaCrud } from "../../components/Crud";
import { Encabezado } from "../../components/Encabezado";
import { EstadoExpedienteChip } from "../../components/Estados";
import { Dato, Seccion } from "../../components/Seccion";
import { DosLineas, Tabla } from "../../components/Tabla";
import { fecha, pesos } from "../../lib/formato";
import {
  formatearRut,
  MENSAJE_RUT,
  normalizarRut,
  rutValido,
} from "../../lib/rut";

type FilaCliente = Cliente & { expedientes_count?: number };

const esquema = z.object({
  rut: z
    .string()
    .trim()
    .refine(rutValido, MENSAJE_RUT)
    .transform(normalizarRut),
  nombre: z.string().trim().min(3, "Indique el nombre completo").max(160),
  telefono: z.string().trim().max(30),
  email: z
    .string()
    .trim()
    .max(160)
    .refine((v) => !v || /.+@.+\..+/.test(v), "Correo no válido"),
  direccion: z.string().trim().max(200),
  comuna: z.string().trim().max(80),
  convenios: z.array(z.number()),
});

/** Clientes (contratantes) con sus convenios */
export default function Clientes() {
  const navegar = useNavigate();
  const convenios = useQuery({
    queryKey: ["convenios", "vigentes"],
    queryFn: async () =>
      (
        await api.get<Pagina<Convenio>>("/convenios", {
          params: { estado: "vigentes", por_pagina: 100 },
        })
      ).data.data,
  });

  return (
    <PantallaCrud<FilaCliente, typeof esquema>
      titulo="Clientes"
      descripcion="Personas que contratan servicios. Desde aquí se ve el historial de cada familia."
      singular="Cliente"
      recurso="clientes"
      buscar="Buscar por nombre, RUT o teléfono"
      permisos={{
        crear: P.clientesCrear,
        editar: P.clientesEditar,
        eliminar: P.clientesEliminar,
      }}
      alClicFila={(c) => navegar(`/clientes/${c.id}`)}
      esquema={esquema}
      nuevo={{
        rut: "",
        nombre: "",
        telefono: "",
        email: "",
        direccion: "",
        comuna: "Temuco",
        convenios: [],
      }}
      aFormulario={(c) => ({
        rut: formatearRut(c.rut),
        nombre: c.nombre,
        telefono: c.telefono ?? "",
        email: c.email ?? "",
        direccion: c.direccion ?? "",
        comuna: c.comuna ?? "",
        convenios: c.convenios?.map((x) => x.id) ?? [],
      })}
      campos={[
        {
          nombre: "rut",
          etiqueta: "RUT",
          ancho: "medio",
          ayuda: "Ej.: 12.345.678-9",
        },
        { nombre: "nombre", etiqueta: "Nombre completo", ancho: "medio" },
        { nombre: "telefono", etiqueta: "Teléfono", ancho: "medio" },
        { nombre: "email", etiqueta: "Correo", tipo: "email", ancho: "medio" },
        { nombre: "direccion", etiqueta: "Dirección", ancho: "medio" },
        { nombre: "comuna", etiqueta: "Comuna", ancho: "medio" },
        {
          nombre: "convenios",
          etiqueta: "Convenios",
          tipo: "multiseleccion",
          opciones: (convenios.data ?? []).map((x) => ({
            valor: x.id,
            etiqueta: x.nombre,
          })),
        },
      ]}
      columnas={[
        {
          id: "nombre",
          titulo: "Cliente",
          render: (c) => (
            <DosLineas principal={c.nombre} secundaria={formatearRut(c.rut)} />
          ),
        },
        {
          id: "contacto",
          titulo: "Contacto",
          ocultarEnMovil: true,
          render: (c) => (
            <DosLineas
              principal={c.telefono ?? "—"}
              secundaria={c.email ?? undefined}
            />
          ),
        },
        {
          id: "comuna",
          titulo: "Comuna",
          ocultarEnMovil: true,
          render: (c) => c.comuna ?? "—",
        },
        {
          id: "convenios",
          titulo: "Convenios",
          render: (c) => (
            <Stack direction="row" sx={{ flexWrap: "wrap", gap: 0.5 }}>
              {c.convenios?.length ? (
                c.convenios.map((x) => (
                  <Chip key={x.id} size="small" label={x.nombre} />
                ))
              ) : (
                <Typography variant="body2" color="text.secondary">
                  —
                </Typography>
              )}
            </Stack>
          ),
        },
        {
          id: "servicios",
          titulo: "Servicios",
          alinear: "right",
          render: (c) => c.expedientes_count ?? 0,
        },
      ]}
    />
  );
}

type DetalleCliente = Cliente & {
  expedientes: {
    id: number;
    codigo: string;
    estado: EstadoExpediente;
    created_at: string;
    difunto: string;
    total: number | null;
    saldo: number | null;
  }[];
};

export function FichaCliente() {
  const { id } = useParams();
  const navegar = useNavigate();
  const { data: c } = useQuery({
    queryKey: ["cliente", id],
    queryFn: async () =>
      (await api.get<DetalleCliente>(`/clientes/${id}`)).data,
  });
  if (!c) return <Skeleton height={300} />;

  return (
    <>
      <Encabezado
        volver={{ a: "/clientes", texto: "Clientes" }}
        titulo={c.nombre}
        descripcion={`RUT ${formatearRut(c.rut)}`}
      />
      <Stack spacing={3}>
        <Seccion titulo="Datos de contacto">
          <Stack direction={{ xs: "column", md: "row" }} spacing={4}>
            <Dato etiqueta="Teléfono">{c.telefono}</Dato>
            <Dato etiqueta="Correo">{c.email}</Dato>
            <Dato etiqueta="Dirección">
              {[c.direccion, c.comuna].filter(Boolean).join(", ")}
            </Dato>
            <Dato etiqueta="Convenios">
              {c.convenios?.map((x) => x.nombre).join(", ") || "Sin convenios"}
            </Dato>
          </Stack>
        </Seccion>
        <Seccion titulo="Historial de servicios" sinRelleno>
          <Tabla
            filas={c.expedientes}
            clave={(e) => e.id}
            alClic={(e) => navegar(`/expedientes/${e.id}`)}
            vacio="Este cliente aún no tiene servicios."
            columnas={[
              {
                id: "codigo",
                titulo: "Expediente",
                render: (e) => (
                  <DosLineas
                    principal={e.codigo}
                    secundaria={fecha(e.created_at)}
                  />
                ),
              },
              { id: "difunto", titulo: "Fallecido", render: (e) => e.difunto },
              {
                id: "estado",
                titulo: "Estado",
                render: (e) => <EstadoExpedienteChip estado={e.estado} />,
              },
              {
                id: "total",
                titulo: "Total",
                alinear: "right",
                render: (e) => pesos(e.total),
              },
              {
                id: "saldo",
                titulo: "Saldo",
                alinear: "right",
                render: (e) => pesos(e.saldo),
              },
            ]}
          />
        </Seccion>
      </Stack>
    </>
  );
}
