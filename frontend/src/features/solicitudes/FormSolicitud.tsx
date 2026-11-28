import AddRoundedIcon from "@mui/icons-material/AddRounded";
import DeleteOutlineRoundedIcon from "@mui/icons-material/DeleteOutlineRounded";
import { zodResolver } from "@hookform/resolvers/zod";
import {
  Alert,
  Autocomplete,
  Box,
  Button,
  Checkbox,
  Chip,
  FormControlLabel,
  IconButton,
  InputAdornment,
  MenuItem,
  Radio,
  Skeleton,
  Stack,
  TextField,
  Typography,
} from "@mui/material";
import { useQuery, useQueryClient } from "@tanstack/react-query";
import { useEffect, useMemo, useState } from "react";
import { Controller, useFieldArray, useForm, useWatch } from "react-hook-form";
import { useNavigate, useParams, useSearchParams } from "react-router-dom";
import { z } from "zod";
import { api, leerError, type Pagina } from "../../api/cliente";
import type {
  Disponibilidad,
  Expediente,
  FilaExpediente,
  Producto,
  SalaVelatoria,
  Solicitud,
  Sucursal,
} from "../../api/tipos";
import { colores } from "../../app/tema";
import { Encabezado } from "../../components/Encabezado";
import { TIPOS_PRODUCTO } from "../../components/Estados";
import { useAvisar } from "../../components/Notificador";
import { Seccion } from "../../components/Seccion";
import { aInputLocal, desdeInputLocal, hora, pesos } from "../../lib/formato";
import { buscarSala, ESTADOS_SALA } from "../salas/estados";

const CEMENTERIOS = [
  "Cementerio General de Temuco",
  "Cementerio de Padre Las Casas",
  "Cementerio Parque Temuco",
  "Cementerio de Labranza",
  "Crematorio de Temuco",
];

const crearEsquema = (esUrna: (productoId: number) => boolean) =>
  z
    .object({
      expediente_id: z
        .number({ message: "Seleccione el expediente" })
        .int()
        .positive("Seleccione el expediente"),
      sucursal_id: z
        .number({ message: "Seleccione la sucursal" })
        .int()
        .positive(),
      velatorio_inicio: z.string().min(1, "Indique el inicio del velatorio"),
      lugar_velatorio: z
        .string()
        .trim()
        .min(3, "Indique dónde será el velatorio")
        .max(200),
      traslado_at: z.string().min(1, "Indique la fecha y hora del traslado"),
      lugar_sepultura: z
        .string()
        .trim()
        .min(3, "Indique dónde será sepultado")
        .max(200),
      sepultura_detalle: z.string().max(200).optional(),
      mensaje: z.string().max(2000).optional(),
      valor_servicio: z
        .number({ message: "Indique el valor" })
        .int()
        .min(0)
        .max(100_000_000),
      descuento: z.number().int().min(0),
      productos: z
        .array(
          z.object({
            producto_id: z.number().int().positive(),
            cantidad: z.number().int().min(1).max(50),
          }),
        )
        .refine(
          (lista) => lista.some((it) => esUrna(it.producto_id)),
          "Debe asignar al menos un ataúd o urna",
        ),
      vehiculo_id: z
        .number({ message: "Seleccione el vehículo" })
        .int()
        .positive("Seleccione el vehículo"),
      equipo: z
        .array(z.number().int())
        .min(1, "Asigne al menos un operario")
        .max(6),
    })
    .refine(
      (d) =>
        !d.traslado_at ||
        !d.velatorio_inicio ||
        d.traslado_at > d.velatorio_inicio,
      {
        path: ["traslado_at"],
        message: "El traslado debe ser posterior al inicio del velatorio",
      },
    )
    .refine((d) => d.descuento <= d.valor_servicio, {
      path: ["descuento"],
      message: "El descuento no puede superar el valor del servicio",
    });

type Datos = z.infer<ReturnType<typeof crearEsquema>>;

/** Tipos que cuentan como el ataúd o urna del servicio (se exige al menos uno) */
const TIPOS_URNA: string[] = ["ataud", "urna", "urna_cremacion"];

/**
 * Registro y edición de la solicitud de servicio. Muestra en vivo qué vehículos
 * y operarios están libres para el horario del traslado; la validación final la
 * hace la base de datos al aceptar (restricción de exclusión)
 */
export default function FormSolicitud() {
  const { id } = useParams();
  const edicion = !!id;
  const [params] = useSearchParams();
  const navegar = useNavigate();
  const qc = useQueryClient();
  const avisar = useAvisar();
  const [error, setError] = useState<string | null>(null);
  const [busquedaExp, setBusquedaExp] = useState("");

  const solicitud = useQuery({
    queryKey: ["solicitud", id],
    queryFn: async () => (await api.get<Solicitud>(`/solicitudes/${id}`)).data,
    enabled: edicion,
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
  const productos = useQuery({
    queryKey: ["productos", "todos"],
    queryFn: async () =>
      (
        await api.get<Pagina<Producto>>("/productos", {
          params: { por_pagina: 200 },
        })
      ).data.data,
  });
  const expedientes = useQuery({
    queryKey: ["expedientes", "buscar", busquedaExp],
    queryFn: async () =>
      (
        await api.get<Pagina<FilaExpediente>>("/expedientes", {
          params: { q: busquedaExp || undefined, por_pagina: 15 },
        })
      ).data.data,
    enabled: !edicion && !params.get("expediente"),
  });

  const porId = useMemo(
    () => new Map((productos.data ?? []).map((p) => [p.id, p])),
    [productos.data],
  );
  const esUrna = (pid: number) =>
    TIPOS_URNA.includes(porId.get(pid)?.tipo ?? "");

  const {
    control,
    handleSubmit,
    reset,
    setValue,
    formState: { errors, isSubmitting },
  } = useForm<Datos>({
    // Mientras no cargue el catálogo no se puede saber el tipo: valida el servidor
    resolver: zodResolver(
      crearEsquema((pid) => !productos.data || esUrna(pid)),
    ),
    defaultValues: {
      expediente_id:
        Number(params.get("expediente")) || (undefined as unknown as number),
      sucursal_id: undefined as unknown as number,
      velatorio_inicio: "",
      lugar_velatorio: "",
      traslado_at: "",
      lugar_sepultura: "",
      sepultura_detalle: "",
      mensaje: "",
      valor_servicio: 890000,
      descuento: 0,
      productos: [],
      vehiculo_id: undefined as unknown as number,
      equipo: [],
    },
  });
  const lista = useFieldArray({ control, name: "productos" });
  const [expedienteId, traslado, items, valor, descuento, vehiculoId, equipo] =
    useWatch({
      control,
      name: [
        "expediente_id",
        "traslado_at",
        "productos",
        "valor_servicio",
        "descuento",
        "vehiculo_id",
        "equipo",
      ],
    });
  const [velatorio, lugarVelatorio] = useWatch({
    control,
    name: ["velatorio_inicio", "lugar_velatorio"],
  });
  const salas = useQuery({
    queryKey: ["salas-velatorias", "todas"],
    queryFn: async () =>
      (
        await api.get<Pagina<SalaVelatoria>>("/salas-velatorias", {
          params: { por_pagina: 100 },
        })
      ).data.data,
  });

  const expediente = useQuery({
    queryKey: ["expediente", String(expedienteId)],
    queryFn: async () =>
      (await api.get<Expediente>(`/expedientes/${expedienteId}`)).data,
    enabled: !!expedienteId,
  });

  // Al editar, se cargan los valores actuales
  useEffect(() => {
    const s = solicitud.data;
    if (!s) return;
    reset({
      expediente_id: s.expediente_id,
      sucursal_id: s.sucursal_id,
      velatorio_inicio: aInputLocal(s.velatorio_inicio),
      lugar_velatorio: s.lugar_velatorio,
      traslado_at: aInputLocal(s.traslado_at),
      lugar_sepultura: s.lugar_sepultura,
      sepultura_detalle: s.sepultura_detalle ?? "",
      mensaje: s.mensaje ?? "",
      valor_servicio: s.valor_servicio,
      descuento: s.descuento,
      productos: s.productos.map((p) => ({
        producto_id: p.id,
        cantidad: p.pivot?.cantidad ?? 1,
      })),
      vehiculo_id:
        s.asignacion?.vehiculo_id ?? (undefined as unknown as number),
      equipo: s.asignacion?.equipo?.map((m) => m.id) ?? [],
    });
  }, [solicitud.data, reset]);

  // Sucursal por defecto: la del expediente
  useEffect(() => {
    if (!edicion && expediente.data)
      setValue("sucursal_id", expediente.data.sucursal.id);
  }, [edicion, expediente.data, setValue]);

  const trasladoIso =
    traslado && traslado.length >= 16 ? desdeInputLocal(traslado) : null;
  const velatorioIso =
    velatorio && velatorio.length >= 16 ? desdeInputLocal(velatorio) : null;
  const disponibilidad = useQuery({
    queryKey: ["disponibilidad", velatorioIso, trasladoIso, id],
    queryFn: async () =>
      (
        await api.get<Disponibilidad>("/disponibilidad", {
          params: {
            velatorio_inicio: velatorioIso ?? undefined,
            traslado_at: trasladoIso,
            excluir_solicitud: id,
          },
        })
      ).data,
    enabled: !!trasladoIso,
  });
  /** Por qué no se puede usar esa sala en el horario elegido (vacío si se puede) */
  const impedimento = (lugar: string) => {
    const sala = buscarSala(salas.data, lugar);
    if (!sala) return "";
    if (sala.estado !== "disponible") return ESTADOS_SALA[sala.estado].nota;
    const ocupante = disponibilidad.data?.salas.find(
      (s) => s.id === sala.id,
    )?.ocupada_por;
    return ocupante
      ? `ocupada en ese horario por el expediente ${ocupante}`
      : "";
  };
  const problemaSala = impedimento(lugarVelatorio ?? "");

  const precioDe = (pid: number) => {
    const actual = solicitud.data?.productos.find((p) => p.id === pid);
    return actual?.pivot?.precio_unitario ?? porId.get(pid)?.precio ?? 0;
  };
  /** En una solicitud aceptada, las unidades que ya tiene reservadas siguen disponibles para ella */
  const disponibleDe = (pid: number) => {
    const reservado =
      solicitud.data?.estado === "aceptada"
        ? (solicitud.data.productos.find((p) => p.id === pid)?.pivot
            ?.cantidad ?? 0)
        : 0;
    return (porId.get(pid)?.stock ?? 0) + reservado;
  };
  /** Mismas reglas que valida el servidor; aquí se avisa antes de enviar */
  const problemaProductos = (lista: Datos["productos"]) => {
    if (!productos.data) return null;
    const falta = lista.find(
      (it) => it.cantidad > disponibleDe(it.producto_id),
    );
    if (falta)
      return `Stock insuficiente de ${porId.get(falta.producto_id)?.nombre}: quedan ${disponibleDe(falta.producto_id)} y se necesitan ${falta.cantidad}.`;
    return null;
  };
  const totalProductos = (items ?? []).reduce(
    (a, it) => a + precioDe(it.producto_id) * (it.cantidad || 0),
    0,
  );
  const total =
    (Number(valor) || 0) - (Number(descuento) || 0) + totalProductos;
  const convenio = expediente.data?.convenio;
  const descuentoSugerido =
    convenio && convenio.descuento_pct > 0
      ? Math.round(
          ((Number(valor) || 0) * convenio.descuento_pct) / 100 / 1000,
        ) * 1000
      : 0;

  const guardar = handleSubmit(async (d) => {
    setError(null);
    const problema = problemaProductos(d.productos);
    if (problema) {
      setError(problema);
      window.scrollTo({ top: 0, behavior: "smooth" });
      return;
    }
    const cuerpo = {
      ...d,
      velatorio_inicio: desdeInputLocal(d.velatorio_inicio),
      traslado_at: desdeInputLocal(d.traslado_at),
      sepultura_detalle: d.sepultura_detalle || null,
      mensaje: d.mensaje || null,
    };
    try {
      const r = edicion
        ? await api.put<Solicitud>(`/solicitudes/${id}`, {
            ...cuerpo,
            expediente_id: undefined,
          })
        : await api.post<Solicitud>("/solicitudes", cuerpo);
      await Promise.all(
        [
          "solicitudes",
          "solicitud",
          "expediente",
          "panel",
          "calendario",
          "salas-velatorias",
          "disponibilidad",
        ].map((k) => qc.invalidateQueries({ queryKey: [k] })),
      );
      avisar(
        edicion
          ? "Solicitud actualizada"
          : "Solicitud registrada. Quedó en revisión.",
      );
      navegar(`/solicitudes/${r.data.id}`);
    } catch (e) {
      setError(leerError(e).mensaje);
      window.scrollTo({ top: 0, behavior: "smooth" });
    }
  });

  const errorProductos =
    errors.productos?.root?.message ?? errors.productos?.message;

  if (edicion && solicitud.isLoading) return <Skeleton height={500} />;

  return (
    <Box component="form" onSubmit={guardar} noValidate>
      <Encabezado
        volver={
          edicion
            ? { a: `/solicitudes/${id}`, texto: "Volver a la solicitud" }
            : { a: "/solicitudes", texto: "Solicitudes" }
        }
        titulo={edicion ? "Editar solicitud" : "Nueva solicitud de servicio"}
        descripcion={
          expediente.data ? (
            <>
              Servicio para <strong>{expediente.data.difunto.nombre}</strong>,
              expediente {expediente.data.codigo}, contratante{" "}
              {expediente.data.cliente.nombre}.
            </>
          ) : (
            "Seleccione el expediente y complete los datos del velatorio y el traslado."
          )
        }
      />
      {solicitud.data?.estado === "aceptada" && (
        <Alert severity="info" sx={{ mb: 3 }}>
          La solicitud ya está aceptada: al guardar se recalculan el stock y la
          reserva del vehículo y el equipo en una sola operación
        </Alert>
      )}
      {error && (
        <Alert severity="error" sx={{ mb: 3 }}>
          {error}
        </Alert>
      )}

      <Box
        sx={{
          display: "grid",
          gap: 3,
          gridTemplateColumns: { xs: "1fr", lg: "2fr 1fr" },
          alignItems: "start",
        }}
      >
        <Stack spacing={3}>
          {!edicion && !params.get("expediente") && (
            <Seccion titulo="Expediente">
              <Controller
                name="expediente_id"
                control={control}
                render={({ field }) => (
                  <Autocomplete
                    options={expedientes.data ?? []}
                    getOptionLabel={(o) =>
                      `${o.codigo}, ${o.difunto} (contratante ${o.cliente})`
                    }
                    onInputChange={(_, v) => setBusquedaExp(v)}
                    onChange={(_, o) => field.onChange(o?.id)}
                    filterOptions={(x) => x}
                    noOptionsText="Sin resultados. Cree primero el expediente."
                    renderInput={(p) => (
                      <TextField
                        {...p}
                        label="Buscar expediente por código, fallecido o contratante"
                        error={!!errors.expediente_id}
                        helperText={errors.expediente_id?.message}
                      />
                    )}
                  />
                )}
              />
            </Seccion>
          )}

          <Seccion
            titulo="Velatorio y traslado"
            descripcion="Horario local. El vehículo y el equipo quedan comprometidos desde la presentación previa hasta el término estimado del traslado."
          >
            <Box
              sx={{
                display: "grid",
                gap: 2,
                gridTemplateColumns: { xs: "1fr", sm: "1fr 1fr" },
              }}
            >
              <Controller
                name="velatorio_inicio"
                control={control}
                render={({ field }) => (
                  <TextField
                    {...field}
                    type="datetime-local"
                    label="Inicio del velatorio"
                    slotProps={{ inputLabel: { shrink: true } }}
                    error={!!errors.velatorio_inicio}
                    helperText={errors.velatorio_inicio?.message}
                  />
                )}
              />
              <Controller
                name="lugar_velatorio"
                control={control}
                render={({ field }) => (
                  <Autocomplete
                    freeSolo
                    // Solo las salas de la funeraria: cualquier otro lugar se escribe a mano
                    options={salas.data?.map((s) => s.lugar) ?? []}
                    filterOptions={(opciones) => opciones}
                    getOptionDisabled={(lugar) => !!impedimento(lugar)}
                    renderOption={({ key, ...props }, lugar) => (
                      <li key={key} {...props}>
                        {lugar}
                        {impedimento(lugar) ? ` (${impedimento(lugar)})` : ""}
                      </li>
                    )}
                    value={field.value}
                    onInputChange={(_, v) => field.onChange(v)}
                    renderInput={(p) => (
                      <TextField
                        {...p}
                        label="Lugar del velatorio"
                        error={!!errors.lugar_velatorio || !!problemaSala}
                        helperText={
                          errors.lugar_velatorio?.message ??
                          (problemaSala
                            ? `Sala ${problemaSala}`
                            : !field.value
                              ? "Elija una sala de la funeraria o escriba otro lugar"
                              : buscarSala(salas.data, field.value)
                                ? "Sala de la funeraria"
                                : "Lugar externo: no se controla su disponibilidad")
                        }
                      />
                    )}
                  />
                )}
              />
              <Controller
                name="traslado_at"
                control={control}
                render={({ field }) => (
                  <TextField
                    {...field}
                    type="datetime-local"
                    label="Fecha y hora del traslado"
                    slotProps={{ inputLabel: { shrink: true } }}
                    error={!!errors.traslado_at}
                    helperText={
                      errors.traslado_at?.message ??
                      (disponibilidad.data
                        ? `Compromiso estimado: ${hora(disponibilidad.data.inicio)} a ${hora(disponibilidad.data.fin)}`
                        : " ")
                    }
                  />
                )}
              />
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
            </Box>
          </Seccion>

          <Seccion titulo="Sepultura">
            <Box
              sx={{
                display: "grid",
                gap: 2,
                gridTemplateColumns: { xs: "1fr", sm: "1fr 1fr" },
              }}
            >
              <Controller
                name="lugar_sepultura"
                control={control}
                render={({ field }) => (
                  <Autocomplete
                    freeSolo
                    options={CEMENTERIOS}
                    value={field.value}
                    onInputChange={(_, v) => field.onChange(v)}
                    renderInput={(p) => (
                      <TextField
                        {...p}
                        label="Dónde será sepultado"
                        error={!!errors.lugar_sepultura}
                        helperText={errors.lugar_sepultura?.message}
                      />
                    )}
                  />
                )}
              />
              <Controller
                name="sepultura_detalle"
                control={control}
                render={({ field }) => (
                  <TextField
                    {...field}
                    label="Ubicación en el cementerio (opcional)"
                    placeholder="Patio, nicho o sepultura"
                  />
                )}
              />
              <Box sx={{ gridColumn: { sm: "span 2" } }}>
                <Controller
                  name="mensaje"
                  control={control}
                  render={({ field }) => (
                    <TextField
                      {...field}
                      multiline
                      minRows={2}
                      label="Mensaje para la cinta o tarjeta (opcional)"
                    />
                  )}
                />
              </Box>
            </Box>
          </Seccion>

          <Seccion
            titulo="Urna y elementos"
            descripcion="Incluya al menos un ataúd o urna. El stock se descuenta recién cuando la solicitud se acepta."
          >
            {errorProductos && (
              <Alert severity="error" sx={{ mb: 2 }}>
                {errorProductos}
              </Alert>
            )}
            <Stack spacing={1.5}>
              {lista.fields.map((f, i) => {
                const p = porId.get(items?.[i]?.producto_id);
                const sinStock =
                  !!p && (items?.[i]?.cantidad ?? 1) > disponibleDe(p.id);
                return (
                  <Stack
                    key={f.id}
                    direction="row"
                    spacing={1.5}
                    sx={{
                      alignItems: "center",
                      p: 1.5,
                      border: `1px solid ${colores.linea}`,
                      borderRadius: 2,
                    }}
                  >
                    {p?.foto_url ? (
                      <Box
                        component="img"
                        src={p.foto_url}
                        alt=""
                        sx={{
                          width: 64,
                          height: 48,
                          objectFit: "cover",
                          borderRadius: 1,
                        }}
                      />
                    ) : (
                      <Box
                        sx={{
                          width: 64,
                          height: 48,
                          borderRadius: 1,
                          bgcolor: "background.default",
                        }}
                      />
                    )}
                    <Box sx={{ flex: 1, minWidth: 0 }}>
                      <Typography variant="body2" sx={{ fontWeight: 600 }}>
                        {p?.nombre}
                      </Typography>
                      <Typography
                        variant="caption"
                        color={sinStock ? "error" : "text.secondary"}
                      >
                        {p
                          ? `${TIPOS_PRODUCTO[p.tipo]}, ${pesos(precioDe(p.id))}, stock ${p.stock}${sinStock ? " (no alcanza)" : ""}`
                          : ""}
                      </Typography>
                    </Box>
                    <Controller
                      name={`productos.${i}.cantidad`}
                      control={control}
                      render={({ field }) => (
                        <TextField
                          {...field}
                          type="number"
                          label="Cant."
                          sx={{ width: 80 }}
                          error={sinStock}
                          onChange={(e) =>
                            field.onChange(Number(e.target.value))
                          }
                          slotProps={{
                            htmlInput: {
                              min: 1,
                              max: Math.min(50, p ? disponibleDe(p.id) : 50),
                            },
                          }}
                        />
                      )}
                    />
                    <IconButton
                      onClick={() => lista.remove(i)}
                      aria-label="Quitar producto"
                    >
                      <DeleteOutlineRoundedIcon />
                    </IconButton>
                  </Stack>
                );
              })}
              <Autocomplete
                options={(productos.data ?? []).filter(
                  (p) => !items?.some((it) => it.producto_id === p.id),
                )}
                getOptionDisabled={(p) => disponibleDe(p.id) === 0}
                groupBy={(p) => TIPOS_PRODUCTO[p.tipo]}
                getOptionLabel={(p) => `${p.nombre} (${p.codigo})`}
                renderOption={({ key, ...props }, p) => (
                  <li key={key} {...props}>
                    <Stack
                      direction="row"
                      sx={{
                        width: "100%",
                        justifyContent: "space-between",
                        gap: 2,
                      }}
                    >
                      <span>
                        {p.nombre}
                        {p.infantil ? " (infantil)" : ""}
                      </span>
                      <Typography
                        variant="body2"
                        color={p.stock === 0 ? "error" : "text.secondary"}
                      >
                        {pesos(p.precio)}, stock {p.stock}
                      </Typography>
                    </Stack>
                  </li>
                )}
                value={null}
                blurOnSelect
                clearOnBlur
                onChange={(_, p) =>
                  p && lista.append({ producto_id: p.id, cantidad: 1 })
                }
                renderInput={(p) => (
                  <TextField
                    {...p}
                    label="Agregar urna, ataúd o elemento"
                    slotProps={{
                      ...p.slotProps,
                      input: {
                        ...p.slotProps.input,
                        startAdornment: (
                          <AddRoundedIcon
                            sx={{ color: "text.secondary", mr: 0.5 }}
                          />
                        ),
                      },
                    }}
                  />
                )}
              />
            </Stack>
          </Seccion>

          <Seccion
            titulo="Vehículo y equipo"
            descripcion={
              trasladoIso
                ? "Disponibilidad calculada para el horario del traslado."
                : "Indique la fecha y hora del traslado para ver qué vehículos y operarios están libres."
            }
          >
            {errors.vehiculo_id?.message && (
              <Alert severity="error" sx={{ mb: 2 }}>
                {errors.vehiculo_id.message}
              </Alert>
            )}
            {disponibilidad.isFetching && <Skeleton height={120} />}
            {disponibilidad.data && (
              <Box
                sx={{
                  display: "grid",
                  gap: 3,
                  gridTemplateColumns: { xs: "1fr", md: "1fr 1fr" },
                }}
              >
                <Box>
                  <Typography variant="h5" sx={{ mb: 1 }}>
                    Vehículo
                  </Typography>
                  <Stack spacing={1}>
                    {disponibilidad.data.vehiculos.map((v) => (
                      <Box
                        key={v.id}
                        component="label"
                        sx={{
                          display: "flex",
                          alignItems: "center",
                          gap: 1,
                          p: 1,
                          pr: 1.5,
                          borderRadius: 2,
                          cursor: v.ocupado ? "not-allowed" : "pointer",
                          border: `1px solid ${vehiculoId === v.id ? colores.vino : colores.linea}`,
                          bgcolor:
                            vehiculoId === v.id ? colores.vinoTenue : undefined,
                          opacity: v.ocupado ? 0.6 : 1,
                        }}
                      >
                        <Radio
                          size="small"
                          checked={vehiculoId === v.id}
                          disabled={v.ocupado}
                          onChange={() =>
                            setValue("vehiculo_id", v.id, {
                              shouldValidate: true,
                            })
                          }
                        />
                        <Box sx={{ flex: 1 }}>
                          <Typography variant="body2" sx={{ fontWeight: 600 }}>
                            {v.nombre}
                          </Typography>
                          <Typography variant="caption" color="text.secondary">
                            {v.origen === "externo"
                              ? `Arriendo: ${v.proveedor_externo}`
                              : `${v.tipo === "carroza" ? "Carroza" : v.tipo === "instalacion" ? "Instalación" : "Apoyo"}${v.patente ? `, ${v.patente}` : ""}`}
                          </Typography>
                        </Box>
                        {v.ocupado && (
                          <Chip
                            size="small"
                            color="warning"
                            variant="outlined"
                            label="Ocupado"
                          />
                        )}
                      </Box>
                    ))}
                  </Stack>
                </Box>
                <Box>
                  <Typography variant="h5" sx={{ mb: 1 }}>
                    Equipo
                  </Typography>
                  {errors.equipo?.message && (
                    <Typography
                      variant="caption"
                      color="error"
                      component="div"
                      sx={{ mb: 1 }}
                    >
                      {errors.equipo.message}
                    </Typography>
                  )}
                  <Stack spacing={0.5}>
                    {disponibilidad.data.operarios.map((o) => {
                      const marcado = equipo?.includes(o.id) ?? false;
                      const bloqueado = (o.ocupado || o.ausente) && !marcado;
                      return (
                        <FormControlLabel
                          key={o.id}
                          disabled={bloqueado}
                          control={
                            <Checkbox
                              size="small"
                              checked={marcado}
                              onChange={(e) =>
                                setValue(
                                  "equipo",
                                  e.target.checked
                                    ? [...(equipo ?? []), o.id]
                                    : (equipo ?? []).filter((x) => x !== o.id),
                                  { shouldValidate: true },
                                )
                              }
                            />
                          }
                          label={
                            <Stack
                              direction="row"
                              spacing={1}
                              sx={{ alignItems: "center" }}
                            >
                              <span>{o.nombre}</span>
                              {marcado && equipo?.[0] === o.id && (
                                <Chip size="small" label="Conductor" />
                              )}
                              {o.ocupado && (
                                <Chip
                                  size="small"
                                  color="warning"
                                  variant="outlined"
                                  label="En otro servicio"
                                />
                              )}
                              {o.ausente && (
                                <Chip
                                  size="small"
                                  color="warning"
                                  variant="outlined"
                                  label="Con ausencia"
                                />
                              )}
                            </Stack>
                          }
                        />
                      );
                    })}
                  </Stack>
                  <Typography variant="caption" color="text.secondary">
                    El primer operario marcado será el conductor.
                  </Typography>
                </Box>
              </Box>
            )}
          </Seccion>
        </Stack>

        <Box sx={{ position: { lg: "sticky" }, top: { lg: 88 } }}>
          <Seccion titulo="Valor del servicio">
            <Stack spacing={2}>
              <Controller
                name="valor_servicio"
                control={control}
                render={({ field }) => (
                  <TextField
                    {...field}
                    type="number"
                    label="Valor del servicio (sin productos)"
                    onChange={(e) => field.onChange(Number(e.target.value))}
                    error={!!errors.valor_servicio}
                    helperText={errors.valor_servicio?.message}
                    slotProps={{
                      input: {
                        startAdornment: (
                          <InputAdornment position="start">$</InputAdornment>
                        ),
                      },
                    }}
                  />
                )}
              />
              <Controller
                name="descuento"
                control={control}
                render={({ field }) => (
                  <TextField
                    {...field}
                    type="number"
                    label="Descuento"
                    onChange={(e) => field.onChange(Number(e.target.value))}
                    error={!!errors.descuento}
                    helperText={
                      errors.descuento?.message ??
                      (convenio
                        ? `Convenio: ${convenio.nombre}`
                        : "Sin convenio en el expediente")
                    }
                    slotProps={{
                      input: {
                        startAdornment: (
                          <InputAdornment position="start">$</InputAdornment>
                        ),
                      },
                    }}
                  />
                )}
              />
              {descuentoSugerido > 0 && descuentoSugerido !== descuento && (
                <Button
                  size="small"
                  variant="outlined"
                  onClick={() => setValue("descuento", descuentoSugerido)}
                >
                  Aplicar {convenio!.descuento_pct} % del convenio (
                  {pesos(descuentoSugerido)})
                </Button>
              )}
              <Box sx={{ borderTop: `1px solid ${colores.linea}`, pt: 2 }}>
                <Linea texto="Productos" valor={pesos(totalProductos)} />
                <Linea texto="Servicio" valor={pesos(valor)} />
                {!!descuento && (
                  <Linea texto="Descuento" valor={`−${pesos(descuento)}`} />
                )}
                <Stack
                  direction="row"
                  sx={{ justifyContent: "space-between", mt: 1.5 }}
                >
                  <Typography sx={{ fontWeight: 700 }}>Total</Typography>
                  <Typography sx={{ fontWeight: 700, fontSize: "1.25rem" }}>
                    {pesos(total)}
                  </Typography>
                </Stack>
                {expediente.data && (
                  <Typography variant="caption" color="text.secondary">
                    Pagado hasta ahora: {pesos(expediente.data.resumen.pagado)}.
                    Para aceptarla se exige el abono mínimo configurado.
                  </Typography>
                )}
              </Box>
              <Button
                type="submit"
                variant="contained"
                size="large"
                disabled={isSubmitting}
              >
                {isSubmitting
                  ? "Guardando…"
                  : edicion
                    ? "Guardar cambios"
                    : "Registrar solicitud"}
              </Button>
            </Stack>
          </Seccion>
        </Box>
      </Box>
    </Box>
  );
}

function Linea({ texto, valor }: { texto: string; valor: string }) {
  return (
    <Stack direction="row" sx={{ justifyContent: "space-between", py: 0.25 }}>
      <Typography variant="body2" color="text.secondary">
        {texto}
      </Typography>
      <Typography variant="body2">{valor}</Typography>
    </Stack>
  );
}
