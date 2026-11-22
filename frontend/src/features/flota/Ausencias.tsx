import AddRoundedIcon from "@mui/icons-material/AddRounded";
import DeleteOutlineRoundedIcon from "@mui/icons-material/DeleteOutlineRounded";
import EditRoundedIcon from "@mui/icons-material/EditRounded";
import { Button, Chip, IconButton, Stack } from "@mui/material";
import { useQuery, useQueryClient } from "@tanstack/react-query";
import { useState } from "react";
import { z } from "zod";
import { api, leerError } from "../../api/cliente";
import type { Ausencia, Usuario } from "../../api/tipos";
import { useSesion } from "../../auth/Sesion";
import { P } from "../../auth/permisos";
import { Confirmar } from "../../components/Confirmar";
import { Encabezado } from "../../components/Encabezado";
import { FormularioDialogo } from "../../components/Formulario";
import { useAvisar } from "../../components/Notificador";
import { Seccion } from "../../components/Seccion";
import { Tabla } from "../../components/Tabla";
import { ahoraLocal, fecha } from "../../lib/formato";

const TIPOS = {
  vacaciones: "Vacaciones",
  licencia: "Licencia médica",
  permiso: "Permiso",
  otra: "Otra",
};

const esquema = z
  .object({
    usuario_id: z.number({ message: "Seleccione a la persona" }),
    tipo: z.enum(["vacaciones", "licencia", "permiso", "otra"]),
    desde: z.string().min(1, "Indique la fecha"),
    hasta: z.string().min(1, "Indique la fecha"),
    motivo: z.string().max(250),
  })
  .refine((d) => d.hasta >= d.desde, {
    path: ["hasta"],
    message: "Debe ser igual o posterior al inicio",
  });

/** Ausencias del personal. Solo el administrador las registra */
export default function Ausencias() {
  const { puede } = useSesion();
  const qc = useQueryClient();
  const avisar = useAvisar();
  const [editando, setEditando] = useState<Ausencia | "nueva" | null>(null);
  const [eliminar, setEliminar] = useState<Ausencia | null>(null);
  const gestionar = puede(P.ausenciasGestionar);
  const hoy = ahoraLocal().format("YYYY-MM-DD");

  const { data, isFetching } = useQuery({
    queryKey: ["ausencias"],
    queryFn: async () => (await api.get<Ausencia[]>("/ausencias")).data,
  });
  const usuarios = useQuery({
    queryKey: ["usuarios"],
    queryFn: async () => (await api.get<Usuario[]>("/usuarios")).data,
    enabled: gestionar,
  });

  return (
    <>
      <Encabezado
        titulo="Ausencias del personal"
        descripcion="Vacaciones, licencias y permisos. Una persona ausente no puede asignarse a un servicio en esas fechas."
        acciones={
          gestionar && (
            <Button
              variant="contained"
              startIcon={<AddRoundedIcon />}
              onClick={() => setEditando("nueva")}
            >
              Registrar ausencia
            </Button>
          )
        }
      />
      <Seccion sinRelleno>
        <Tabla
          filas={data}
          clave={(a) => a.id}
          cargando={isFetching}
          vacio="No hay ausencias registradas."
          columnas={[
            {
              id: "persona",
              titulo: "Persona",
              render: (a) => a.usuario?.nombre,
            },
            { id: "tipo", titulo: "Tipo", render: (a) => TIPOS[a.tipo] },
            { id: "desde", titulo: "Desde", render: (a) => fecha(a.desde) },
            { id: "hasta", titulo: "Hasta", render: (a) => fecha(a.hasta) },
            {
              id: "estado",
              titulo: "",
              render: (a) =>
                a.desde <= hoy && a.hasta >= hoy ? (
                  <Chip size="small" color="warning" label="Ausente hoy" />
                ) : a.desde > hoy ? (
                  <Chip size="small" variant="outlined" label="Programada" />
                ) : null,
            },
            {
              id: "motivo",
              titulo: "Motivo",
              ocultarEnMovil: true,
              render: (a) => a.motivo ?? "—",
            },
            ...(gestionar
              ? [
                  {
                    id: "acc",
                    titulo: "",
                    alinear: "right" as const,
                    render: (a: Ausencia) => (
                      <Stack
                        direction="row"
                        sx={{ justifyContent: "flex-end" }}
                      >
                        <IconButton
                          size="small"
                          onClick={() => setEditando(a)}
                          aria-label="Editar"
                        >
                          <EditRoundedIcon fontSize="small" />
                        </IconButton>
                        <IconButton
                          size="small"
                          onClick={() => setEliminar(a)}
                          aria-label="Eliminar"
                        >
                          <DeleteOutlineRoundedIcon fontSize="small" />
                        </IconButton>
                      </Stack>
                    ),
                  },
                ]
              : []),
          ]}
        />
      </Seccion>
      <FormularioDialogo
        abierto={editando !== null}
        titulo={editando === "nueva" ? "Registrar ausencia" : "Editar ausencia"}
        esquema={esquema}
        campos={[
          {
            nombre: "usuario_id",
            etiqueta: "Persona",
            tipo: "seleccion",
            opciones: (usuarios.data ?? [])
              .filter((u) => u.activo)
              .map((u) => ({
                valor: u.id,
                etiqueta: `${u.nombre} (${u.rol})`,
              })),
          },
          {
            nombre: "tipo",
            etiqueta: "Tipo",
            tipo: "seleccion",
            opciones: Object.entries(TIPOS).map(([valor, etiqueta]) => ({
              valor,
              etiqueta,
            })),
          },
          { nombre: "desde", etiqueta: "Desde", tipo: "fecha", ancho: "medio" },
          { nombre: "hasta", etiqueta: "Hasta", tipo: "fecha", ancho: "medio" },
          { nombre: "motivo", etiqueta: "Motivo (opcional)" },
        ]}
        valoresIniciales={
          editando && editando !== "nueva"
            ? {
                usuario_id: editando.usuario_id,
                tipo: editando.tipo,
                desde: editando.desde,
                hasta: editando.hasta,
                motivo: editando.motivo ?? "",
              }
            : {
                usuario_id: undefined as unknown as number,
                tipo: "vacaciones",
                desde: hoy,
                hasta: hoy,
                motivo: "",
              }
        }
        alCerrar={() => setEditando(null)}
        alGuardar={async (d) => {
          const cuerpo = { ...d, motivo: d.motivo || null };
          if (editando === "nueva") await api.post("/ausencias", cuerpo);
          else if (editando) await api.put(`/ausencias/${editando.id}`, cuerpo);
          await qc.invalidateQueries({ queryKey: ["ausencias"] });
          avisar("Ausencia guardada");
          setEditando(null);
        }}
      />
      <Confirmar
        abierto={!!eliminar}
        titulo="Eliminar ausencia"
        mensaje="La persona volverá a quedar disponible en esas fechas."
        textoConfirmar="Eliminar"
        peligro
        alCerrar={() => setEliminar(null)}
        alConfirmar={async () => {
          try {
            await api.delete(`/ausencias/${eliminar!.id}`);
            await qc.invalidateQueries({ queryKey: ["ausencias"] });
            avisar("Ausencia eliminada");
          } catch (e) {
            avisar(leerError(e).mensaje, "error");
          }
          setEliminar(null);
        }}
      />
    </>
  );
}
