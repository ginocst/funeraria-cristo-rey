import AddRoundedIcon from "@mui/icons-material/AddRounded";
import DeleteOutlineRoundedIcon from "@mui/icons-material/DeleteOutlineRounded";
import EditRoundedIcon from "@mui/icons-material/EditRounded";
import { Box, Button, IconButton, Stack, Tooltip } from "@mui/material";
import {
  keepPreviousData,
  useMutation,
  useQuery,
  useQueryClient,
} from "@tanstack/react-query";
import { useState, type ReactNode } from "react";
import type { z } from "zod";
import { api, leerError, type Pagina } from "../api/cliente";
import { useSesion } from "../auth/Sesion";
import { Buscador } from "./Buscador";
import { Confirmar } from "./Confirmar";
import { Encabezado } from "./Encabezado";
import { FormularioDialogo, type Campo } from "./Formulario";
import { useAvisar } from "./Notificador";
import { Seccion } from "./Seccion";
import { Tabla, type Columna } from "./Tabla";

type ConId = { id: number };

type Props<T extends ConId, S extends z.ZodType> = {
  titulo: string;
  descripcion?: ReactNode;
  singular: string;
  recurso: string;
  columnas: Columna<T>[];
  esquema: S;
  campos: Campo<z.input<S>>[];
  nuevo: Partial<z.input<S>>;
  aFormulario: (fila: T) => Partial<z.input<S>>;
  permisos: { crear?: string; editar?: string; eliminar?: string };
  filtros?: ReactNode;
  parametros?: Record<string, unknown>;
  buscar?: string;
  alClicFila?: (fila: T) => void;
  accionesExtra?: ReactNode;
  accionesFila?: (fila: T) => ReactNode;
  sinEncabezado?: boolean;
  /** El singular es femenino: «Nueva categoría», «Categoría creada» */
  femenino?: boolean;
  /** Otras consultas que muestran estos datos y deben actualizarse al cambiarlos */
  invalidar?: string[];
};

/**
 * Pantalla completa de mantenedor (listar, buscar, crear, editar y eliminar
 * con borrado lógico). La usan convenios, proveedores, vehículos, gastos,
 * sucursales y medios de pago.
 */
export function PantallaCrud<T extends ConId, S extends z.ZodType>(
  p: Props<T, S>,
) {
  const { puede } = useSesion();
  const qc = useQueryClient();
  const avisar = useAvisar();
  const [q, setQ] = useState("");
  const [pagina, setPagina] = useState(1);
  const [editando, setEditando] = useState<T | "nuevo" | null>(null);
  const [eliminando, setEliminando] = useState<T | null>(null);

  const consulta = useQuery({
    queryKey: [p.recurso, { q, pagina, ...p.parametros }],
    queryFn: async () =>
      (
        await api.get<Pagina<T>>(`/${p.recurso}`, {
          params: {
            q: q || undefined,
            page: pagina,
            por_pagina: 25,
            ...p.parametros,
          },
        })
      ).data,
    placeholderData: keepPreviousData,
  });

  const nuevoTexto = `${p.femenino ? "Nueva" : "Nuevo"} ${p.singular.toLowerCase()}`;
  const refrescar = () =>
    Promise.all(
      [p.recurso, ...(p.invalidar ?? [])].map((clave) =>
        qc.invalidateQueries({ queryKey: [clave] }),
      ),
    );

  const guardar = async (datos: z.output<S>) => {
    if (editando === "nuevo") await api.post(`/${p.recurso}`, datos);
    else if (editando) await api.put(`/${p.recurso}/${editando.id}`, datos);
    await refrescar();
    avisar(
      editando === "nuevo"
        ? `${p.singular} ${p.femenino ? "creada" : "creado"}`
        : "Cambios guardados",
    );
    setEditando(null);
  };

  const eliminar = useMutation({
    mutationFn: async (fila: T) => api.delete(`/${p.recurso}/${fila.id}`),
    onSuccess: async () => {
      await refrescar();
      avisar(`${p.singular} ${p.femenino ? "eliminada" : "eliminado"}`);
      setEliminando(null);
    },
    onError: (e) => {
      avisar(leerError(e).mensaje, "error");
      setEliminando(null);
    },
  });

  const puedeEditar = !!p.permisos.editar && puede(p.permisos.editar);
  const puedeEliminar = !!p.permisos.eliminar && puede(p.permisos.eliminar);
  const columnas: Columna<T>[] = [
    ...p.columnas,
    ...(puedeEditar || puedeEliminar || p.accionesFila
      ? [
          {
            id: "acciones",
            titulo: "",
            alinear: "right" as const,
            ancho: 120,
            render: (f: T) => (
              <Stack
                direction="row"
                spacing={0.5}
                sx={{ justifyContent: "flex-end" }}
                onClick={(e) => e.stopPropagation()}
              >
                {p.accionesFila?.(f)}
                {puedeEditar && (
                  <Tooltip title="Editar">
                    <IconButton
                      size="small"
                      onClick={() => setEditando(f)}
                      aria-label="Editar"
                    >
                      <EditRoundedIcon fontSize="small" />
                    </IconButton>
                  </Tooltip>
                )}
                {puedeEliminar && (
                  <Tooltip title="Eliminar">
                    <IconButton
                      size="small"
                      onClick={() => setEliminando(f)}
                      aria-label="Eliminar"
                    >
                      <DeleteOutlineRoundedIcon fontSize="small" />
                    </IconButton>
                  </Tooltip>
                )}
              </Stack>
            ),
          },
        ]
      : []),
  ];

  return (
    <>
      {!p.sinEncabezado && (
        <Encabezado
          titulo={p.titulo}
          descripcion={p.descripcion}
          acciones={
            <>
              {p.accionesExtra}
              {p.permisos.crear && puede(p.permisos.crear) && (
                <Button
                  variant="contained"
                  startIcon={<AddRoundedIcon />}
                  onClick={() => setEditando("nuevo")}
                >
                  {nuevoTexto}
                </Button>
              )}
            </>
          }
        />
      )}
      <Seccion sinRelleno>
        <Stack
          direction={{ xs: "column", sm: "row" }}
          spacing={1.5}
          sx={{ p: 2, alignItems: { sm: "center" } }}
        >
          <Buscador
            valor={q}
            alCambiar={(v) => {
              setQ(v);
              setPagina(1);
            }}
            placeholder={p.buscar ?? "Buscar por nombre"}
          />
          <Box sx={{ flex: 1 }} />
          {p.filtros}
          {p.sinEncabezado && p.permisos.crear && puede(p.permisos.crear) && (
            <Button
              variant="contained"
              startIcon={<AddRoundedIcon />}
              onClick={() => setEditando("nuevo")}
            >
              {nuevoTexto}
            </Button>
          )}
        </Stack>
        <Tabla
          columnas={columnas}
          filas={consulta.data?.data}
          clave={(f) => f.id}
          cargando={consulta.isFetching}
          alClic={p.alClicFila ?? (puedeEditar ? setEditando : undefined)}
          paginacion={
            consulta.data
              ? {
                  pagina,
                  total: consulta.data.total,
                  porPagina: consulta.data.per_page,
                  alCambiar: setPagina,
                }
              : undefined
          }
        />
      </Seccion>

      <FormularioDialogo
        abierto={editando !== null}
        titulo={
          editando === "nuevo"
            ? nuevoTexto
            : `Editar ${p.singular.toLowerCase()}`
        }
        esquema={p.esquema}
        campos={p.campos}
        valoresIniciales={
          editando && editando !== "nuevo" ? p.aFormulario(editando) : p.nuevo
        }
        alCerrar={() => setEditando(null)}
        alGuardar={guardar}
      />
      <Confirmar
        abierto={!!eliminando}
        titulo={`Eliminar ${p.singular.toLowerCase()}`}
        mensaje="El registro dejará de aparecer en las listas. Su historial se conserva para la auditoría."
        textoConfirmar="Eliminar"
        peligro
        cargando={eliminar.isPending}
        alCerrar={() => setEliminando(null)}
        alConfirmar={() => eliminando && eliminar.mutate(eliminando)}
      />
    </>
  );
}
