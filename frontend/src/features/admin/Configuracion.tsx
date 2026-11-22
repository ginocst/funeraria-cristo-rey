import {
  Alert,
  Box,
  Button,
  InputAdornment,
  Skeleton,
  Stack,
  Tab,
  Tabs,
  TextField,
  Typography,
} from "@mui/material";
import { useQuery, useQueryClient } from "@tanstack/react-query";
import { useEffect, useState } from "react";
import { useSearchParams } from "react-router-dom";
import { z } from "zod";
import { api, leerError } from "../../api/cliente";
import type { MedioPago, Parametro, Sucursal } from "../../api/tipos";
import { P } from "../../auth/permisos";
import { PantallaCrud } from "../../components/Crud";
import { Encabezado } from "../../components/Encabezado";
import { useAvisar } from "../../components/Notificador";
import { Seccion } from "../../components/Seccion";

const UNIDADES: Record<string, string> = {
  anticipacion_minima_horas: "horas",
  abono_minimo_pct: "%",
  duracion_servicio_horas: "horas",
  duracion_traslado_horas: "horas",
  presentacion_previa_minutos: "minutos",
  umbral_stock: "unidades",
  umbral_stock_infantil: "unidades",
};

function Parametros() {
  const qc = useQueryClient();
  const avisar = useAvisar();
  const { data } = useQuery({
    queryKey: ["parametros"],
    queryFn: async () => (await api.get<Parametro[]>("/parametros")).data,
  });
  const [valores, setValores] = useState<Record<string, number>>({});
  const [error, setError] = useState<string | null>(null);
  useEffect(() => {
    if (data)
      setValores(Object.fromEntries(data.map((p) => [p.clave, p.valor])));
  }, [data]);

  if (!data) return <Skeleton height={300} />;
  const guardar = async () => {
    setError(null);
    try {
      await api.put("/parametros", valores);
      await qc.invalidateQueries({ queryKey: ["parametros"] });
      avisar("Parámetros guardados. Se aplican a las próximas operaciones.");
    } catch (e) {
      setError(leerError(e).mensaje);
    }
  };

  return (
    <Seccion
      titulo="Reglas del negocio"
      descripcion="Valores que el dueño puede ajustar sin cambiar el sistema. Cada cambio queda en la bitácora."
    >
      {error && (
        <Alert severity="error" sx={{ mb: 2 }}>
          {error}
        </Alert>
      )}
      <Box
        sx={{
          display: "grid",
          gap: 2.5,
          gridTemplateColumns: { xs: "1fr", md: "1fr 1fr" },
        }}
      >
        {data.map((p) => (
          <TextField
            key={p.clave}
            type="number"
            label={p.descripcion}
            value={valores[p.clave] ?? ""}
            onChange={(e) =>
              setValores({ ...valores, [p.clave]: Number(e.target.value) })
            }
            slotProps={{
              input: {
                endAdornment: (
                  <InputAdornment position="end">
                    {UNIDADES[p.clave]}
                  </InputAdornment>
                ),
              },
              htmlInput: { min: 0 },
            }}
          />
        ))}
      </Box>
      <Stack direction="row" sx={{ mt: 3, justifyContent: "flex-end" }}>
        <Button variant="contained" onClick={guardar}>
          Guardar parámetros
        </Button>
      </Stack>
    </Seccion>
  );
}

const esquemaSucursal = z.object({
  nombre: z.string().trim().min(2).max(120),
  direccion: z.string().max(200),
  telefono: z.string().max(30),
});
const esquemaMedio = z.object({
  nombre: z.string().trim().min(2).max(60),
  requiere_referencia: z.boolean(),
  activo: z.boolean(),
});

/** Configuración del negocio: parámetros, sucursales y medios de pago */
export default function Configuracion() {
  const [params, setParams] = useSearchParams();
  const pestana = params.get("pestana") ?? "parametros";
  return (
    <>
      <Encabezado
        titulo="Configuración"
        descripcion="Ajustes que dependen de decisiones del dueño."
      />
      <Tabs
        value={pestana}
        onChange={(_, v) => setParams({ pestana: v }, { replace: true })}
        sx={{ mb: 2.5, borderBottom: 1, borderColor: "divider" }}
      >
        <Tab value="parametros" label="Parámetros" />
        <Tab value="sucursales" label="Sucursales" />
        <Tab value="medios" label="Medios de pago" />
      </Tabs>
      {pestana === "parametros" && <Parametros />}
      {pestana === "sucursales" && (
        <PantallaCrud<Sucursal, typeof esquemaSucursal>
          sinEncabezado
          titulo="Sucursales"
          singular="Sucursal"
          recurso="sucursales"
          permisos={{
            crear: P.configuracion,
            editar: P.configuracion,
            eliminar: P.configuracion,
          }}
          esquema={esquemaSucursal}
          nuevo={{ nombre: "", direccion: "", telefono: "" }}
          aFormulario={(s) => ({
            nombre: s.nombre,
            direccion: s.direccion ?? "",
            telefono: s.telefono ?? "",
          })}
          campos={[
            { nombre: "nombre", etiqueta: "Nombre" },
            { nombre: "direccion", etiqueta: "Dirección" },
            { nombre: "telefono", etiqueta: "Teléfono" },
          ]}
          columnas={[
            {
              id: "n",
              titulo: "Sucursal",
              render: (s) => (
                <Typography variant="body2" sx={{ fontWeight: 600 }}>
                  {s.nombre}
                </Typography>
              ),
            },
            { id: "d", titulo: "Dirección", render: (s) => s.direccion ?? "—" },
            { id: "t", titulo: "Teléfono", render: (s) => s.telefono ?? "—" },
          ]}
        />
      )}
      {pestana === "medios" && (
        <PantallaCrud<MedioPago, typeof esquemaMedio>
          sinEncabezado
          titulo="Medios de pago"
          singular="Medio de pago"
          recurso="medios-pago"
          permisos={{
            crear: P.configuracion,
            editar: P.configuracion,
            eliminar: P.configuracion,
          }}
          esquema={esquemaMedio}
          nuevo={{ nombre: "", requiere_referencia: true, activo: true }}
          aFormulario={(m) => ({
            nombre: m.nombre,
            requiere_referencia: m.requiere_referencia,
            activo: m.activo,
          })}
          campos={[
            { nombre: "nombre", etiqueta: "Nombre" },
            {
              nombre: "requiere_referencia",
              etiqueta: "Exigir número de operación o voucher",
              tipo: "interruptor",
            },
            {
              nombre: "activo",
              etiqueta: "Disponible al registrar pagos",
              tipo: "interruptor",
            },
          ]}
          columnas={[
            { id: "n", titulo: "Medio", render: (m) => m.nombre },
            {
              id: "r",
              titulo: "Referencia",
              render: (m) => (m.requiere_referencia ? "Obligatoria" : "No"),
            },
            {
              id: "a",
              titulo: "Estado",
              render: (m) => (m.activo ? "Activo" : "Inactivo"),
            },
          ]}
        />
      )}
    </>
  );
}
