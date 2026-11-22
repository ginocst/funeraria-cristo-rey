import AddRoundedIcon from "@mui/icons-material/AddRounded";
import EditRoundedIcon from "@mui/icons-material/EditRounded";
import LockResetRoundedIcon from "@mui/icons-material/LockResetRounded";
import ShieldRoundedIcon from "@mui/icons-material/ShieldRounded";
import SupervisorAccountRoundedIcon from "@mui/icons-material/SupervisorAccountRounded";
import {
  Button,
  Chip,
  IconButton,
  Stack,
  Tooltip,
  Typography,
} from "@mui/material";
import { useQuery, useQueryClient } from "@tanstack/react-query";
import { useState } from "react";
import { z } from "zod";
import { api, leerError, type Pagina } from "../../api/cliente";
import type { Sucursal, Usuario } from "../../api/tipos";
import { colores } from "../../app/tema";
import { useSesion } from "../../auth/Sesion";
import { Buscador } from "../../components/Buscador";
import { Confirmar } from "../../components/Confirmar";
import { Encabezado } from "../../components/Encabezado";
import { FormularioDialogo } from "../../components/Formulario";
import { useAvisar } from "../../components/Notificador";
import { Seccion } from "../../components/Seccion";
import { DosLineas, Tabla } from "../../components/Tabla";
import {
  ahoraLocal,
  desdeInputLocal,
  fecha,
  relativo,
} from "../../lib/formato";
import {
  formatearRut,
  MENSAJE_RUT,
  normalizarRut,
  rutValido,
} from "../../lib/rut";

const contrasena = z
  .string()
  .refine(
    (v) => !v || (v.length >= 12 && /[a-zA-Z]/.test(v) && /\d/.test(v)),
    "Mínimo 12 caracteres, con letras y números",
  );

const esquema = z
  .object({
    nombre: z.string().trim().min(3, "Indique el nombre").max(120),
    email: z.string().trim().email("Correo no válido"),
    rut: z
      .string()
      .trim()
      .refine(rutValido, MENSAJE_RUT)
      .transform(normalizarRut),
    telefono: z.string().max(30),
    sucursal_id: z.number({ message: "Seleccione la sucursal" }),
    rol: z.enum(["Administrador", "Coordinador", "Operario"]),
    activo: z.boolean(),
    password: contrasena,
    nuevo: z.boolean(),
  })
  .refine((d) => !d.nuevo || !!d.password, {
    path: ["password"],
    message: "Defina una contraseña inicial",
  })
  .transform(({ nuevo, ...d }) => ({
    ...d,
    password: d.password || undefined,
    telefono: d.telefono || null,
    _nuevo: nuevo,
  }));

const esquemaSuplencia = z.object({
  hasta: z.string().min(1, "Indique hasta cuándo"),
});

/**
 * Gestión de cuentas (solo Administrador). La suplencia da al coordinador los
 * permisos del administrador hasta una fecha, salvo la gestión de usuarios
 */
export default function Usuarios() {
  const { sesion } = useSesion();
  const qc = useQueryClient();
  const avisar = useAvisar();
  const [q, setQ] = useState("");
  const [editando, setEditando] = useState<Usuario | "nuevo" | null>(null);
  const [suplencia, setSuplencia] = useState<Usuario | null>(null);
  const [mfa, setMfa] = useState<Usuario | null>(null);

  const { data, isFetching } = useQuery({
    queryKey: ["usuarios", q],
    queryFn: async () =>
      (await api.get<Usuario[]>("/usuarios", { params: { q: q || undefined } }))
        .data,
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
  const refrescar = () => qc.invalidateQueries({ queryKey: ["usuarios"] });

  const accion = async (fn: () => Promise<unknown>, mensaje: string) => {
    try {
      await fn();
      await refrescar();
      avisar(mensaje);
    } catch (e) {
      avisar(leerError(e).mensaje, "error");
    }
  };

  return (
    <>
      <Encabezado
        titulo="Usuarios"
        descripcion="Cuentas del personal y su perfil. Administrador y coordinador deben usar verificación en dos pasos."
        acciones={
          <Button
            variant="contained"
            startIcon={<AddRoundedIcon />}
            onClick={() => setEditando("nuevo")}
          >
            Nuevo usuario
          </Button>
        }
      />
      <Seccion sinRelleno>
        <Stack sx={{ p: 2 }}>
          <Buscador
            valor={q}
            alCambiar={setQ}
            placeholder="Buscar por nombre o correo"
          />
        </Stack>
        <Tabla
          filas={data}
          clave={(u) => u.id}
          cargando={isFetching}
          alClic={setEditando}
          columnas={[
            {
              id: "nombre",
              titulo: "Persona",
              render: (u) => (
                <DosLineas principal={u.nombre} secundaria={u.email} />
              ),
            },
            {
              id: "rol",
              titulo: "Perfil",
              render: (u) => (
                <Stack spacing={0.5} sx={{ alignItems: "flex-start" }}>
                  <Chip
                    size="small"
                    label={u.rol}
                    sx={{
                      bgcolor:
                        u.rol === "Administrador"
                          ? colores.vinoSuave
                          : "#EFECEE",
                      color:
                        u.rol === "Administrador"
                          ? colores.vino
                          : colores.tinta,
                    }}
                  />
                  {u.suplencia_hasta && (
                    <Typography variant="caption" color="primary">
                      Suplencia hasta {fecha(u.suplencia_hasta)}
                    </Typography>
                  )}
                </Stack>
              ),
            },
            {
              id: "suc",
              titulo: "Sucursal",
              ocultarEnMovil: true,
              render: (u) => u.sucursal ?? "—",
            },
            {
              id: "mfa",
              titulo: "Dos pasos",
              ocultarEnMovil: true,
              render: (u) =>
                u.mfa_activo ? (
                  <Chip
                    size="small"
                    icon={<ShieldRoundedIcon />}
                    label="Activa"
                    sx={{
                      bgcolor: "#E3F0E8",
                      color: colores.ok,
                      "& .MuiChip-icon": { color: colores.ok },
                    }}
                  />
                ) : (
                  <Typography
                    variant="body2"
                    color={
                      u.rol === "Operario" ? "text.secondary" : "warning.main"
                    }
                  >
                    {u.rol === "Operario" ? "Opcional" : "Pendiente"}
                  </Typography>
                ),
            },
            {
              id: "acc",
              titulo: "Último acceso",
              ocultarEnMovil: true,
              render: (u) =>
                u.ultimo_acceso_at ? relativo(u.ultimo_acceso_at) : "Nunca",
            },
            {
              id: "estado",
              titulo: "Estado",
              render: (u) => (
                <Chip
                  size="small"
                  variant={u.activo ? "filled" : "outlined"}
                  label={u.activo ? "Activa" : "Desactivada"}
                />
              ),
            },
            {
              id: "acciones",
              titulo: "",
              alinear: "right",
              render: (u) => (
                <Stack
                  direction="row"
                  sx={{ justifyContent: "flex-end" }}
                  onClick={(e) => e.stopPropagation()}
                >
                  {u.rol === "Coordinador" &&
                    (u.suplencia_hasta ? (
                      <Tooltip title="Revocar suplencia">
                        <IconButton
                          size="small"
                          onClick={() =>
                            accion(
                              () => api.delete(`/usuarios/${u.id}/suplencia`),
                              "Suplencia revocada",
                            )
                          }
                          aria-label="Revocar suplencia"
                        >
                          <SupervisorAccountRoundedIcon
                            fontSize="small"
                            color="primary"
                          />
                        </IconButton>
                      </Tooltip>
                    ) : (
                      <Tooltip title="Otorgar suplencia de administrador">
                        <IconButton
                          size="small"
                          onClick={() => setSuplencia(u)}
                          aria-label="Otorgar suplencia"
                        >
                          <SupervisorAccountRoundedIcon fontSize="small" />
                        </IconButton>
                      </Tooltip>
                    ))}
                  {u.mfa_activo && u.id !== sesion?.id && (
                    <Tooltip title="Restablecer verificación en dos pasos">
                      <IconButton
                        size="small"
                        onClick={() => setMfa(u)}
                        aria-label="Restablecer verificación"
                      >
                        <LockResetRoundedIcon fontSize="small" />
                      </IconButton>
                    </Tooltip>
                  )}
                  <Tooltip title="Editar">
                    <IconButton
                      size="small"
                      onClick={() => setEditando(u)}
                      aria-label="Editar"
                    >
                      <EditRoundedIcon fontSize="small" />
                    </IconButton>
                  </Tooltip>
                </Stack>
              ),
            },
          ]}
        />
      </Seccion>

      <FormularioDialogo
        abierto={editando !== null}
        titulo={editando === "nuevo" ? "Nuevo usuario" : "Editar usuario"}
        descripcion={
          editando === "nuevo"
            ? "Entregue la contraseña inicial a la persona por un canal seguro; podrá cambiarla con «Olvidé mi contraseña»."
            : undefined
        }
        esquema={esquema}
        campos={[
          { nombre: "nombre", etiqueta: "Nombre completo", ancho: "medio" },
          {
            nombre: "email",
            etiqueta: "Correo",
            tipo: "email",
            ancho: "medio",
          },
          { nombre: "rut", etiqueta: "RUT", ancho: "medio" },
          { nombre: "telefono", etiqueta: "Teléfono", ancho: "medio" },
          {
            nombre: "rol",
            etiqueta: "Perfil",
            tipo: "seleccion",
            ancho: "medio",
            opciones: ["Administrador", "Coordinador", "Operario"].map((r) => ({
              valor: r,
              etiqueta: r,
            })),
          },
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
            nombre: "password",
            etiqueta:
              editando === "nuevo"
                ? "Contraseña inicial"
                : "Nueva contraseña (opcional)",
            tipo: "contrasena",
            ayuda: "Mínimo 12 caracteres, con letras y números",
          },
          {
            nombre: "activo",
            etiqueta: "Cuenta activa (al desactivarla se cierra su sesión)",
            tipo: "interruptor",
          },
        ]}
        valoresIniciales={
          editando && editando !== "nuevo"
            ? {
                nombre: editando.nombre,
                email: editando.email,
                rut: formatearRut(editando.rut),
                telefono: editando.telefono ?? "",
                sucursal_id: editando.sucursal_id,
                rol: editando.rol,
                activo: editando.activo,
                password: "",
                nuevo: false,
              }
            : {
                nombre: "",
                email: "",
                rut: "",
                telefono: "",
                sucursal_id: sesion?.sucursal?.id,
                rol: "Operario",
                activo: true,
                password: "",
                nuevo: true,
              }
        }
        alCerrar={() => setEditando(null)}
        alGuardar={async ({ _nuevo, ...d }) => {
          if (_nuevo) await api.post("/usuarios", d);
          else if (editando && editando !== "nuevo")
            await api.put(`/usuarios/${editando.id}`, d);
          await refrescar();
          avisar(_nuevo ? "Usuario creado" : "Usuario actualizado");
          setEditando(null);
        }}
      />
      <FormularioDialogo
        abierto={!!suplencia}
        titulo="Suplencia de administrador"
        descripcion={`${suplencia?.nombre} podrá evaluar solicitudes, anular pagos y ver finanzas hasta la fecha indicada (máximo 90 días). No podrá gestionar usuarios. Vence sola.`}
        esquema={esquemaSuplencia}
        campos={[{ nombre: "hasta", etiqueta: "Hasta", tipo: "fecha" }]}
        valoresIniciales={{
          hasta: ahoraLocal().add(7, "day").format("YYYY-MM-DD"),
        }}
        textoGuardar="Otorgar suplencia"
        alCerrar={() => setSuplencia(null)}
        alGuardar={async (d) => {
          await api.post(`/usuarios/${suplencia!.id}/suplencia`, {
            hasta: desdeInputLocal(`${d.hasta}T23:59`),
          });
          await refrescar();
          avisar("Suplencia otorgada");
          setSuplencia(null);
        }}
      />
      <Confirmar
        abierto={!!mfa}
        titulo="Restablecer verificación en dos pasos"
        mensaje={`${mfa?.nombre} deberá configurar de nuevo su aplicación de autenticación en el próximo inicio de sesión. Úselo si perdió su teléfono.`}
        textoConfirmar="Restablecer"
        alCerrar={() => setMfa(null)}
        alConfirmar={() => {
          const u = mfa!;
          setMfa(null);
          accion(
            () => api.post(`/usuarios/${u.id}/restablecer-mfa`),
            "Verificación restablecida",
          );
        }}
      />
    </>
  );
}
