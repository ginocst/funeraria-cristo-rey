import { zodResolver } from "@hookform/resolvers/zod";
import {
  Alert,
  Autocomplete,
  Box,
  Button,
  MenuItem,
  Stack,
  Tab,
  Tabs,
  TextField,
  Typography,
} from "@mui/material";
import { useQuery, useQueryClient } from "@tanstack/react-query";
import { useState } from "react";
import { Controller, useForm, useWatch } from "react-hook-form";
import { useNavigate } from "react-router-dom";
import { z } from "zod";
import { api, leerError, type Pagina } from "../../api/cliente";
import type { Cliente, Convenio, Expediente, Sucursal } from "../../api/tipos";
import { useSesion } from "../../auth/Sesion";
import { Encabezado } from "../../components/Encabezado";
import { useAvisar } from "../../components/Notificador";
import { Seccion } from "../../components/Seccion";
import { ahoraLocal } from "../../lib/formato";
import {
  formatearRut,
  MENSAJE_RUT,
  normalizarRut,
  rutValido,
} from "../../lib/rut";

const rutOpcional = z
  .string()
  .trim()
  .refine((v) => !v || rutValido(v), MENSAJE_RUT);

const esquema = z
  .object({
    modo: z.enum(["existente", "nuevo"]),
    cliente_id: z.number().optional(),
    cliente: z.object({
      rut: z.string().trim(),
      nombre: z.string().trim(),
      telefono: z.string().trim().max(30),
      email: z
        .string()
        .trim()
        .max(160)
        .refine((v) => !v || /.+@.+\..+/.test(v), "Correo no válido"),
      direccion: z.string().trim().max(200),
      comuna: z.string().trim().max(80),
    }),
    difunto: z.object({
      nombre: z.string().trim().min(3, "Indique el nombre completo").max(160),
      rut: rutOpcional,
      fecha_nacimiento: z.string(),
      fecha_fallecimiento: z
        .string()
        .min(1, "Indique la fecha de fallecimiento"),
    }),
    sucursal_id: z.number({ message: "Seleccione la sucursal" }),
    convenio_id: z.number().nullable(),
    observaciones: z.string().max(2000),
  })
  .superRefine((d, ctx) => {
    if (d.modo === "existente" && !d.cliente_id)
      ctx.addIssue({
        code: "custom",
        path: ["cliente_id"],
        message: "Busque y seleccione al contratante",
      });
    if (d.modo === "nuevo") {
      if (!rutValido(d.cliente.rut))
        ctx.addIssue({
          code: "custom",
          path: ["cliente", "rut"],
          message: MENSAJE_RUT,
        });
      if (d.cliente.nombre.length < 3)
        ctx.addIssue({
          code: "custom",
          path: ["cliente", "nombre"],
          message: "Indique el nombre completo",
        });
      if (d.cliente.telefono.length < 8)
        ctx.addIssue({
          code: "custom",
          path: ["cliente", "telefono"],
          message: "Indique un teléfono de contacto",
        });
    }
    if (
      d.difunto.fecha_nacimiento &&
      d.difunto.fecha_nacimiento > d.difunto.fecha_fallecimiento
    ) {
      ctx.addIssue({
        code: "custom",
        path: ["difunto", "fecha_nacimiento"],
        message: "Debe ser anterior a la fecha de fallecimiento",
      });
    }
  });

type Datos = z.infer<typeof esquema>;

/** Apertura del expediente: contratante (existente o nuevo), fallecido y convenio */
export default function NuevoExpediente() {
  const { sesion } = useSesion();
  const navegar = useNavigate();
  const qc = useQueryClient();
  const avisar = useAvisar();
  const [busqueda, setBusqueda] = useState("");
  const [error, setError] = useState<string | null>(null);
  // Si el cliente nuevo ya se creó y falló el paso siguiente, se reutiliza en el reintento (el RUT no puede repetirse)
  const [clienteCreado, setClienteCreado] = useState<number | null>(null);
  const hoy = ahoraLocal().format("YYYY-MM-DD");

  const {
    control,
    handleSubmit,
    setValue,
    formState: { errors, isSubmitting },
  } = useForm<Datos>({
    resolver: zodResolver(esquema),
    defaultValues: {
      modo: "existente",
      cliente: {
        rut: "",
        nombre: "",
        telefono: "",
        email: "",
        direccion: "",
        comuna: "Temuco",
      },
      difunto: {
        nombre: "",
        rut: "",
        fecha_nacimiento: "",
        fecha_fallecimiento: hoy,
      },
      sucursal_id: sesion?.sucursal?.id,
      convenio_id: null,
      observaciones: "",
    },
  });
  const modo = useWatch({ control, name: "modo" });

  const clientes = useQuery({
    queryKey: ["clientes", "buscar", busqueda],
    queryFn: async () =>
      (
        await api.get<Pagina<Cliente>>("/clientes", {
          params: { q: busqueda || undefined, por_pagina: 15 },
        })
      ).data.data,
    enabled: modo === "existente",
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
  const convenios = useQuery({
    queryKey: ["convenios", "vigentes"],
    queryFn: async () =>
      (
        await api.get<Pagina<Convenio>>("/convenios", {
          params: { estado: "vigentes", por_pagina: 100 },
        })
      ).data.data,
  });

  const guardar = handleSubmit(async (d) => {
    setError(null);
    try {
      let clienteId = d.cliente_id;
      if (d.modo === "nuevo") {
        if (clienteCreado === null) {
          const c = await api.post<Cliente>("/clientes", {
            ...d.cliente,
            rut: normalizarRut(d.cliente.rut),
            email: d.cliente.email || null,
          });
          setClienteCreado(c.data.id);
          clienteId = c.data.id;
        } else {
          clienteId = clienteCreado;
        }
      }
      const r = await api.post<Expediente>("/expedientes", {
        cliente_id: clienteId,
        sucursal_id: d.sucursal_id,
        convenio_id: d.convenio_id,
        observaciones: d.observaciones || null,
        difunto: {
          ...d.difunto,
          rut: d.difunto.rut ? normalizarRut(d.difunto.rut) : null,
          fecha_nacimiento: d.difunto.fecha_nacimiento || null,
        },
      });
      await qc.invalidateQueries({ queryKey: ["expedientes"] });
      avisar(`Expediente ${r.data.codigo} creado`);
      navegar(`/solicitudes/nueva?expediente=${r.data.id}`);
    } catch (e) {
      setError(leerError(e).mensaje);
    }
  });

  return (
    <Box component="form" onSubmit={guardar} noValidate>
      <Encabezado
        volver={{ a: "/expedientes", texto: "Expedientes" }}
        titulo="Nuevo servicio"
        descripcion="Primero se abre el expediente con los datos de la familia; a continuación se registra la solicitud de velatorio y traslado."
      />
      {error && (
        <Alert severity="error" sx={{ mb: 3 }}>
          {error}
        </Alert>
      )}
      <Box
        sx={{
          display: "grid",
          gap: 3,
          gridTemplateColumns: { xs: "1fr", lg: "1fr 1fr" },
          alignItems: "start",
        }}
      >
        <Seccion
          titulo="Contratante"
          descripcion="Quien contrata y responde por el pago del servicio."
        >
          <Controller
            name="modo"
            control={control}
            render={({ field }) => (
              <Tabs
                value={field.value}
                onChange={(_, v) => field.onChange(v)}
                sx={{ mb: 2, borderBottom: 1, borderColor: "divider" }}
              >
                <Tab value="existente" label="Cliente registrado" />
                <Tab value="nuevo" label="Cliente nuevo" />
              </Tabs>
            )}
          />
          {modo === "existente" ? (
            <Controller
              name="cliente_id"
              control={control}
              render={({ field }) => (
                <Autocomplete
                  options={clientes.data ?? []}
                  filterOptions={(x) => x}
                  getOptionLabel={(c) => `${c.nombre}, ${formatearRut(c.rut)}`}
                  onInputChange={(_, v) => setBusqueda(v)}
                  onChange={(_, c) => {
                    field.onChange(c?.id);
                    const conv = c?.convenios?.[0];
                    if (conv) setValue("convenio_id", conv.id);
                  }}
                  noOptionsText="No se encontró. Use la pestaña Cliente nuevo."
                  renderInput={(p) => (
                    <TextField
                      {...p}
                      label="Buscar por nombre o RUT"
                      error={!!errors.cliente_id}
                      helperText={errors.cliente_id?.message}
                    />
                  )}
                />
              )}
            />
          ) : (
            <Box
              sx={{
                display: "grid",
                gap: 2,
                gridTemplateColumns: { xs: "1fr", sm: "1fr 1fr" },
              }}
            >
              <Controller
                name="cliente.rut"
                control={control}
                render={({ field }) => (
                  <TextField
                    {...field}
                    label="RUT"
                    placeholder="12.345.678-9"
                    error={!!errors.cliente?.rut}
                    helperText={errors.cliente?.rut?.message}
                  />
                )}
              />
              <Controller
                name="cliente.nombre"
                control={control}
                render={({ field }) => (
                  <TextField
                    {...field}
                    label="Nombre completo"
                    error={!!errors.cliente?.nombre}
                    helperText={errors.cliente?.nombre?.message}
                  />
                )}
              />
              <Controller
                name="cliente.telefono"
                control={control}
                render={({ field }) => (
                  <TextField
                    {...field}
                    label="Teléfono"
                    placeholder="+56 9 1234 5678"
                    error={!!errors.cliente?.telefono}
                    helperText={errors.cliente?.telefono?.message}
                  />
                )}
              />
              <Controller
                name="cliente.email"
                control={control}
                render={({ field }) => (
                  <TextField
                    {...field}
                    label="Correo (opcional)"
                    error={!!errors.cliente?.email}
                    helperText={errors.cliente?.email?.message}
                  />
                )}
              />
              <Controller
                name="cliente.direccion"
                control={control}
                render={({ field }) => (
                  <TextField {...field} label="Dirección" />
                )}
              />
              <Controller
                name="cliente.comuna"
                control={control}
                render={({ field }) => <TextField {...field} label="Comuna" />}
              />
            </Box>
          )}
        </Seccion>

        <Seccion
          titulo="Fallecido"
          descripcion="Solo los datos necesarios para el servicio y los trámites."
        >
          <Box
            sx={{
              display: "grid",
              gap: 2,
              gridTemplateColumns: { xs: "1fr", sm: "1fr 1fr" },
            }}
          >
            <Box sx={{ gridColumn: { sm: "span 2" } }}>
              <Controller
                name="difunto.nombre"
                control={control}
                render={({ field }) => (
                  <TextField
                    {...field}
                    label="Nombre completo"
                    error={!!errors.difunto?.nombre}
                    helperText={errors.difunto?.nombre?.message}
                  />
                )}
              />
            </Box>
            <Controller
              name="difunto.rut"
              control={control}
              render={({ field }) => (
                <TextField
                  {...field}
                  label="RUT (opcional)"
                  error={!!errors.difunto?.rut}
                  helperText={errors.difunto?.rut?.message}
                />
              )}
            />
            <Controller
              name="difunto.fecha_fallecimiento"
              control={control}
              render={({ field }) => (
                <TextField
                  {...field}
                  type="date"
                  label="Fecha de fallecimiento"
                  slotProps={{
                    inputLabel: { shrink: true },
                    htmlInput: { max: hoy },
                  }}
                  error={!!errors.difunto?.fecha_fallecimiento}
                  helperText={errors.difunto?.fecha_fallecimiento?.message}
                />
              )}
            />
            <Controller
              name="difunto.fecha_nacimiento"
              control={control}
              render={({ field }) => (
                <TextField
                  {...field}
                  type="date"
                  label="Fecha de nacimiento (opcional)"
                  slotProps={{ inputLabel: { shrink: true } }}
                  error={!!errors.difunto?.fecha_nacimiento}
                  helperText={errors.difunto?.fecha_nacimiento?.message}
                />
              )}
            />
          </Box>
        </Seccion>

        <Seccion titulo="Servicio" sx={{ gridColumn: { lg: "span 2" } }}>
          <Box
            sx={{
              display: "grid",
              gap: 2,
              gridTemplateColumns: { xs: "1fr", md: "1fr 1fr 2fr" },
            }}
          >
            <Controller
              name="sucursal_id"
              control={control}
              render={({ field }) => (
                <TextField
                  select
                  label="Sucursal"
                  value={field.value ?? ""}
                  onChange={(e) => field.onChange(Number(e.target.value))}
                  error={!!errors.sucursal_id}
                  helperText={errors.sucursal_id?.message}
                >
                  {sucursales.data?.map((s) => (
                    <MenuItem key={s.id} value={s.id}>
                      {s.nombre}
                    </MenuItem>
                  ))}
                </TextField>
              )}
            />
            <Controller
              name="convenio_id"
              control={control}
              render={({ field }) => (
                <TextField
                  select
                  label="Convenio"
                  value={field.value ?? ""}
                  onChange={(e) =>
                    field.onChange(
                      e.target.value === "" ? null : Number(e.target.value),
                    )
                  }
                >
                  <MenuItem value="">Sin convenio</MenuItem>
                  {convenios.data?.map((c) => (
                    <MenuItem key={c.id} value={c.id}>
                      {c.nombre}
                      {c.descuento_pct > 0 ? ` (${c.descuento_pct} %)` : ""}
                    </MenuItem>
                  ))}
                </TextField>
              )}
            />
            <Controller
              name="observaciones"
              control={control}
              render={({ field }) => (
                <TextField {...field} label="Observaciones (opcional)" />
              )}
            />
          </Box>
          <Stack
            direction="row"
            sx={{
              mt: 3,
              justifyContent: "flex-end",
              alignItems: "center",
              gap: 2,
            }}
          >
            <Typography variant="body2" color="text.secondary">
              Al continuar se abre el formulario de la solicitud.
            </Typography>
            <Button
              type="submit"
              variant="contained"
              size="large"
              disabled={isSubmitting}
            >
              {isSubmitting ? "Creando…" : "Crear expediente y continuar"}
            </Button>
          </Stack>
        </Seccion>
      </Box>
    </Box>
  );
}
