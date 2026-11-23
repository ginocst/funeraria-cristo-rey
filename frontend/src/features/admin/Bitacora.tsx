import {
  Box,
  Collapse,
  IconButton,
  MenuItem,
  Stack,
  TextField,
  Typography,
} from "@mui/material";
import ExpandMoreRoundedIcon from "@mui/icons-material/ExpandMoreRounded";
import { keepPreviousData, useQuery } from "@tanstack/react-query";
import { useState } from "react";
import { api, type Pagina } from "../../api/cliente";
import type { Auditoria } from "../../api/tipos";
import { Encabezado } from "../../components/Encabezado";
import { Seccion } from "../../components/Seccion";
import { DosLineas, Tabla } from "../../components/Tabla";
import { fechaHora } from "../../lib/formato";

const ACCIONES: Record<string, string> = {
  crear: "Creó",
  editar: "Editó",
  modificar: "Modificó",
  eliminar: "Eliminó",
  aceptar: "Aceptó",
  observar: "Observó",
  no_aplica: "Marcó no aplica",
  cancelar: "Canceló",
  ejecutar: "Marcó ejecutada",
  imprimir_orden: "Imprimió la orden de servicio",
  registrar_pago: "Registró pago",
  anular_pago: "Anuló pago",
  pactar_cuotas: "Pactó cuotas",
  inicio_sesion: "Inició sesión",
  cierre_sesion: "Cerró sesión",
  inicio_sesion_fallido: "Intento de acceso fallido",
  bloqueo_por_intentos: "Bloqueo por intentos",
  ajuste_stock: "Ajustó stock",
  ingreso_mercaderia: "Ingresó mercadería",
  foto_producto: "Cambió foto",
  quitar_foto_producto: "Quitó foto",
  merma_stock: "Registró merma",
  asociar_proveedor: "Asoció proveedor",
  desasociar_proveedor: "Desasoció proveedor",
  subir_documento: "Subió documento",
  ver_documento: "Vio documento",
  otorgar_suplencia: "Otorgó suplencia",
  revocar_suplencia: "Revocó suplencia",
  restablecer_mfa: "Restableció la verificación en dos pasos",
  editar_parametros: "Cambió parámetros",
  reporte_mensual_pdf: "Generó el reporte en PDF",
  reporte_mensual_excel: "Generó el reporte en Excel",
};

function Detalle({ a }: { a: Auditoria }) {
  const [abierto, setAbierto] = useState(false);
  if (!a.antes && !a.despues && !a.motivo) return null;
  return (
    <Box>
      <IconButton
        size="small"
        onClick={() => setAbierto(!abierto)}
        aria-label="Ver detalle"
        sx={{ transform: abierto ? "rotate(180deg)" : undefined }}
      >
        <ExpandMoreRoundedIcon fontSize="small" />
      </IconButton>
      <Collapse in={abierto}>
        <Box
          sx={{
            mt: 1,
            p: 1.5,
            bgcolor: "background.default",
            borderRadius: 1.5,
            fontSize: "0.78rem",
            fontFamily: "ui-monospace, monospace",
            whiteSpace: "pre-wrap",
            maxWidth: 520,
          }}
        >
          {a.motivo && <div>Motivo: {a.motivo}</div>}
          {a.antes && <div>Antes: {JSON.stringify(a.antes)}</div>}
          {a.despues && <div>Después: {JSON.stringify(a.despues)}</div>}
        </Box>
      </Collapse>
    </Box>
  );
}

/** Bitácora inmutable: quién hizo qué, cuándo y desde dónde (no se puede editar ni borrar) */
export default function Bitacora() {
  const [pagina, setPagina] = useState(1);
  const [accion, setAccion] = useState("");
  const [entidad, setEntidad] = useState("");
  const { data, isFetching } = useQuery({
    queryKey: ["auditorias", { pagina, accion, entidad }],
    queryFn: async () =>
      (
        await api.get<Pagina<Auditoria>>("/auditorias", {
          params: {
            page: pagina,
            accion: accion || undefined,
            entidad: entidad || undefined,
          },
        })
      ).data,
    placeholderData: keepPreviousData,
  });

  return (
    <>
      <Encabezado
        titulo="Bitácora"
        descripcion="Registro de las acciones relevantes. Nadie, ni siquiera el administrador, puede modificarlo o borrarlo."
      />
      <Seccion sinRelleno>
        <Stack
          direction="row"
          spacing={1.5}
          sx={{ p: 2, flexWrap: "wrap", gap: 1.5 }}
        >
          <TextField
            select
            label="Acción"
            value={accion}
            onChange={(e) => {
              setAccion(e.target.value);
              setPagina(1);
            }}
            sx={{ width: 240 }}
          >
            <MenuItem value="">Todas</MenuItem>
            {Object.entries(ACCIONES).map(([k, v]) => (
              <MenuItem key={k} value={k}>
                {v}
              </MenuItem>
            ))}
          </TextField>
          <TextField
            select
            label="Registro"
            value={entidad}
            onChange={(e) => {
              setEntidad(e.target.value);
              setPagina(1);
            }}
            sx={{ width: 200 }}
          >
            <MenuItem value="">Todos</MenuItem>
            {[
              "solicitudes",
              "pagos",
              "expedientes",
              "actividades",
              "clientes",
              "convenios",
              "productos",
              "proveedores",
              "vehiculos",
              "ausencias",
              "gastos",
              "usuarios",
              "archivos",
              "parametros",
              "reportes",
            ].map((e) => (
              <MenuItem key={e} value={e}>
                {e}
              </MenuItem>
            ))}
          </TextField>
        </Stack>
        <Tabla
          filas={data?.data}
          clave={(a) => a.id}
          cargando={isFetching}
          densa
          paginacion={
            data
              ? {
                  pagina,
                  total: data.total,
                  porPagina: data.per_page,
                  alCambiar: setPagina,
                }
              : undefined
          }
          columnas={[
            { id: "f", titulo: "Fecha", render: (a) => fechaHora(a.fecha_utc) },
            {
              id: "u",
              titulo: "Usuario",
              render: (a) => (
                <DosLineas
                  principal={a.usuario ?? "Sin sesión"}
                  secundaria={a.ip ?? undefined}
                />
              ),
            },
            {
              id: "a",
              titulo: "Acción",
              render: (a) => (
                <Typography variant="body2" sx={{ fontWeight: 600 }}>
                  {ACCIONES[a.accion] ?? a.accion}
                </Typography>
              ),
            },
            {
              id: "e",
              titulo: "Registro",
              render: (a) =>
                `${a.entidad}${a.entidad_id ? ` #${a.entidad_id}` : ""}`,
            },
            { id: "d", titulo: "Detalle", render: (a) => <Detalle a={a} /> },
          ]}
        />
      </Seccion>
    </>
  );
}
