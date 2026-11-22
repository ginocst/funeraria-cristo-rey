import CloseRoundedIcon from "@mui/icons-material/CloseRounded";
import LinkOffRoundedIcon from "@mui/icons-material/LinkOffRounded";
import {
  Autocomplete,
  Box,
  Drawer,
  IconButton,
  List,
  ListItem,
  ListItemText,
  Stack,
  TextField,
  Tooltip,
  Typography,
} from "@mui/material";
import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { useState } from "react";
import { z } from "zod";
import { api, leerError, type Pagina } from "../../api/cliente";
import type { Producto, Proveedor } from "../../api/tipos";
import { useSesion } from "../../auth/Sesion";
import { P } from "../../auth/permisos";
import { PantallaCrud } from "../../components/Crud";
import { TIPOS_PRODUCTO } from "../../components/Estados";
import { useAvisar } from "../../components/Notificador";
import { Dato } from "../../components/Seccion";
import { DosLineas } from "../../components/Tabla";
import { pesos } from "../../lib/formato";
import {
  formatearRut,
  MENSAJE_RUT,
  normalizarRut,
  rutValido,
} from "../../lib/rut";

const RUBROS = [
  { valor: "urnas", etiqueta: "Urnas y ataúdes" },
  { valor: "flores", etiqueta: "Flores" },
  { valor: "insumos", etiqueta: "Insumos" },
  { valor: "transporte", etiqueta: "Transporte" },
  { valor: "otros", etiqueta: "Otros" },
];

const esquema = z.object({
  nombre: z.string().trim().min(3, "Indique el nombre").max(160),
  rut: z
    .string()
    .trim()
    .refine((v) => !v || rutValido(v), MENSAJE_RUT)
    .transform((v) => (v ? normalizarRut(v) : null)),
  rubro: z.string().min(1),
  contacto: z.string().max(120),
  telefono: z.string().max(30),
  email: z
    .string()
    .max(160)
    .refine((v) => !v || /.+@.+\..+/.test(v), "Correo no válido"),
  direccion: z.string().max(200),
  notas: z.string().max(2000),
  activo: z.boolean(),
});

function FichaProveedor({
  id,
  alCerrar,
}: {
  id: number;
  alCerrar: () => void;
}) {
  const { puede } = useSesion();
  const qc = useQueryClient();
  const avisar = useAvisar();
  const { data: p } = useQuery({
    queryKey: ["proveedor", id],
    queryFn: async () => (await api.get<Proveedor>(`/proveedores/${id}`)).data,
  });
  const productos = useQuery({
    queryKey: ["productos", "todos"],
    queryFn: async () =>
      (
        await api.get<Pagina<Producto>>("/productos", {
          params: { por_pagina: 200 },
        })
      ).data.data,
  });
  const cambio = useMutation({
    mutationFn: async ({
      productoId,
      quitar,
    }: {
      productoId: number;
      quitar?: boolean;
    }) =>
      quitar
        ? api.delete(`/proveedores/${id}/productos/${productoId}`)
        : api.post(`/proveedores/${id}/productos`, { producto_id: productoId }),
    onSuccess: async (_, v) => {
      await Promise.all(
        ["proveedor", "proveedores", "productos"].map((k) =>
          qc.invalidateQueries({ queryKey: [k] }),
        ),
      );
      avisar(
        v.quitar ? "Producto desasociado" : "Producto asociado al proveedor",
      );
    },
    onError: (e) => avisar(leerError(e).mensaje, "error"),
  });
  const gestionar = puede(P.proveedoresGestionar);

  return (
    <Box sx={{ width: { xs: "100vw", sm: 460 }, p: 3 }}>
      <Stack
        direction="row"
        sx={{ justifyContent: "space-between", alignItems: "flex-start" }}
      >
        <Typography variant="h2">{p?.nombre ?? "…"}</Typography>
        <IconButton onClick={alCerrar} aria-label="Cerrar">
          <CloseRoundedIcon />
        </IconButton>
      </Stack>
      {p && (
        <>
          <Box
            sx={{
              display: "grid",
              gridTemplateColumns: "1fr 1fr",
              gap: 2,
              my: 2.5,
            }}
          >
            <Dato etiqueta="RUT">{p.rut ? formatearRut(p.rut) : null}</Dato>
            <Dato etiqueta="Rubro">
              {RUBROS.find((r) => r.valor === p.rubro)?.etiqueta ?? p.rubro}
            </Dato>
            <Dato etiqueta="Contacto">{p.contacto}</Dato>
            <Dato etiqueta="Teléfono">{p.telefono}</Dato>
            <Dato etiqueta="Correo">{p.email}</Dato>
            <Dato etiqueta="Dirección">{p.direccion}</Dato>
          </Box>
          {p.notas && (
            <Typography variant="body2" color="text.secondary" sx={{ mb: 2 }}>
              {p.notas}
            </Typography>
          )}
          <Typography variant="h3" sx={{ mt: 3, mb: 1 }}>
            Productos que provee
          </Typography>
          <List dense disablePadding>
            {p.productos?.length === 0 && (
              <Typography variant="body2" color="text.secondary">
                Aún no tiene productos asociados.
              </Typography>
            )}
            {p.productos?.map((x) => (
              <ListItem
                key={x.id}
                disableGutters
                secondaryAction={
                  gestionar && (
                    <Tooltip title="Desasociar">
                      <IconButton
                        size="small"
                        onClick={() =>
                          cambio.mutate({ productoId: x.id, quitar: true })
                        }
                        aria-label="Desasociar producto"
                      >
                        <LinkOffRoundedIcon fontSize="small" />
                      </IconButton>
                    </Tooltip>
                  )
                }
              >
                <ListItemText
                  primary={x.nombre}
                  secondary={`${x.codigo}, ${TIPOS_PRODUCTO[x.tipo]}, ${pesos(x.precio)}, stock ${x.stock}`}
                />
              </ListItem>
            ))}
          </List>
          {gestionar && (
            <Autocomplete
              sx={{ mt: 2 }}
              options={(productos.data ?? []).filter(
                (x) => x.proveedor_id !== id,
              )}
              getOptionLabel={(x) =>
                `${x.nombre} (${x.codigo})${x.proveedor ? `, hoy de ${x.proveedor.nombre}` : ""}`
              }
              value={null}
              onChange={(_, x) => x && cambio.mutate({ productoId: x.id })}
              renderInput={(params) => (
                <TextField
                  {...params}
                  label="Asociar un producto del inventario"
                />
              )}
            />
          )}
        </>
      )}
    </Box>
  );
}

/** Proveedores de urnas, flores e insumos; cada producto del inventario puede asociarse a su proveedor habitual */
export default function Proveedores() {
  const [abierto, setAbierto] = useState<number | null>(null);
  return (
    <>
      <PantallaCrud<Proveedor, typeof esquema>
        titulo="Proveedores"
        descripcion="Empresas a las que se compran urnas, flores e insumos. Abra un proveedor para ver y asociar sus productos."
        singular="Proveedor"
        recurso="proveedores"
        permisos={{
          crear: P.proveedoresGestionar,
          editar: P.proveedoresGestionar,
          eliminar: P.proveedoresGestionar,
        }}
        alClicFila={(p) => setAbierto(p.id)}
        esquema={esquema}
        nuevo={{
          nombre: "",
          rut: "",
          rubro: "urnas",
          contacto: "",
          telefono: "",
          email: "",
          direccion: "",
          notas: "",
          activo: true,
        }}
        aFormulario={(p) => ({
          nombre: p.nombre,
          rut: p.rut ? formatearRut(p.rut) : "",
          rubro: p.rubro,
          contacto: p.contacto ?? "",
          telefono: p.telefono ?? "",
          email: p.email ?? "",
          direccion: p.direccion ?? "",
          notas: p.notas ?? "",
          activo: p.activo,
        })}
        campos={[
          {
            nombre: "nombre",
            etiqueta: "Razón social o nombre",
            ancho: "medio",
          },
          { nombre: "rut", etiqueta: "RUT (opcional)", ancho: "medio" },
          {
            nombre: "rubro",
            etiqueta: "Rubro",
            tipo: "seleccion",
            ancho: "medio",
            opciones: RUBROS,
          },
          {
            nombre: "contacto",
            etiqueta: "Persona de contacto",
            ancho: "medio",
          },
          { nombre: "telefono", etiqueta: "Teléfono", ancho: "medio" },
          {
            nombre: "email",
            etiqueta: "Correo",
            tipo: "email",
            ancho: "medio",
          },
          { nombre: "direccion", etiqueta: "Dirección" },
          {
            nombre: "notas",
            etiqueta: "Condiciones de compra y notas",
            tipo: "area",
          },
          {
            nombre: "activo",
            etiqueta: "Proveedor activo",
            tipo: "interruptor",
          },
        ]}
        columnas={[
          {
            id: "nombre",
            titulo: "Proveedor",
            render: (p) => (
              <DosLineas
                principal={p.nombre}
                secundaria={p.rut ? formatearRut(p.rut) : undefined}
              />
            ),
          },
          {
            id: "rubro",
            titulo: "Rubro",
            render: (p) =>
              RUBROS.find((r) => r.valor === p.rubro)?.etiqueta ?? p.rubro,
          },
          {
            id: "contacto",
            titulo: "Contacto",
            ocultarEnMovil: true,
            render: (p) => (
              <DosLineas
                principal={p.contacto ?? "—"}
                secundaria={p.telefono ?? undefined}
              />
            ),
          },
          {
            id: "productos",
            titulo: "Productos",
            alinear: "right",
            render: (p) => p.productos_count ?? 0,
          },
        ]}
      />
      <Drawer
        anchor="right"
        open={abierto !== null}
        onClose={() => setAbierto(null)}
      >
        {abierto !== null && (
          <FichaProveedor id={abierto} alCerrar={() => setAbierto(null)} />
        )}
      </Drawer>
    </>
  );
}
