import AddPhotoAlternateRoundedIcon from "@mui/icons-material/AddPhotoAlternateRounded";
import MoveToInboxRoundedIcon from "@mui/icons-material/MoveToInboxRounded";
import PublicRoundedIcon from "@mui/icons-material/PublicRounded";
import TuneRoundedIcon from "@mui/icons-material/TuneRounded";
import {
  Box,
  Chip,
  IconButton,
  MenuItem,
  Stack,
  Tab,
  Tabs,
  TextField,
  Tooltip,
  Typography,
} from "@mui/material";
import {
  keepPreviousData,
  useQuery,
  useQueryClient,
} from "@tanstack/react-query";
import { useRef, useState } from "react";
import { useSearchParams } from "react-router-dom";
import { z } from "zod";
import { api, leerError, type Pagina } from "../../api/cliente";
import type { MovimientoStock, Producto, Proveedor } from "../../api/tipos";
import { colores } from "../../app/tema";
import { useSesion } from "../../auth/Sesion";
import { P } from "../../auth/permisos";
import { PantallaCrud } from "../../components/Crud";
import { Encabezado } from "../../components/Encabezado";
import { StockChip, TIPOS_PRODUCTO } from "../../components/Estados";
import { FormularioDialogo } from "../../components/Formulario";
import { useAvisar } from "../../components/Notificador";
import { Seccion } from "../../components/Seccion";
import { DosLineas, Tabla } from "../../components/Tabla";
import { fechaHora, pesos } from "../../lib/formato";

const esquemaProducto = z.object({
  codigo: z.string().trim().min(2, "Indique el código").max(30),
  nombre: z.string().trim().min(3, "Indique el nombre").max(160),
  tipo: z.enum(["ataud", "urna", "urna_cremacion", "elemento"]),
  proveedor_id: z
    .union([z.number(), z.literal("")])
    .transform((v) => (v === "" ? null : v)),
  material: z.string().max(60),
  color: z.string().max(60),
  medidas: z.string().max(80),
  descripcion: z.string().max(2000),
  precio: z.number({ message: "Indique el precio" }).int().min(0),
  umbral_minimo: z.number({ message: "Indique el mínimo" }).int().min(0),
  stock_inicial: z.number().int().min(0).optional(),
  infantil: z.boolean(),
  publico: z.boolean(),
});

const esquemaIngreso = z.object({
  cantidad: z.number({ message: "Indique la cantidad" }).int().min(1).max(1000),
  proveedor_id: z
    .union([z.number(), z.literal("")])
    .transform((v) => (v === "" ? null : v)),
  documento: z.string().max(60),
  motivo: z.string().max(250),
});

const esquemaAjuste = z.object({
  stock_contado: z.number({ message: "Indique el stock contado" }).int().min(0),
  motivo: z.string().trim().min(5, "Explique el motivo del ajuste").max(250),
});

const TIPOS_MOV: Record<
  MovimientoStock["tipo"],
  { texto: string; color: string }
> = {
  ingreso: { texto: "Ingreso", color: colores.ok },
  salida: { texto: "Salida por servicio", color: colores.vino },
  reposicion: { texto: "Reposición", color: colores.info },
  ajuste: { texto: "Ajuste", color: colores.alerta },
};

function Movimientos() {
  const [pagina, setPagina] = useState(1);
  const [tipo, setTipo] = useState("");
  const { data, isFetching } = useQuery({
    queryKey: ["movimientos", { pagina, tipo }],
    queryFn: async () =>
      (
        await api.get<Pagina<MovimientoStock>>("/movimientos-stock", {
          params: { page: pagina, tipo: tipo || undefined },
        })
      ).data,
    placeholderData: keepPreviousData,
  });
  return (
    <Seccion sinRelleno>
      <Stack direction="row" spacing={1.5} sx={{ p: 2, alignItems: "center" }}>
        <Typography variant="body2" color="text.secondary" sx={{ flex: 1 }}>
          Libro de movimientos: no se edita ni se borra; los errores se corrigen
          con un ajuste.
        </Typography>
        <TextField
          select
          label="Tipo"
          value={tipo}
          onChange={(e) => {
            setTipo(e.target.value);
            setPagina(1);
          }}
          sx={{ width: 200 }}
        >
          <MenuItem value="">Todos</MenuItem>
          {Object.entries(TIPOS_MOV).map(([k, v]) => (
            <MenuItem key={k} value={k}>
              {v.texto}
            </MenuItem>
          ))}
        </TextField>
      </Stack>
      <Tabla
        filas={data?.data}
        clave={(m) => m.id}
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
          {
            id: "fecha",
            titulo: "Fecha",
            render: (m) => fechaHora(m.created_at),
          },
          {
            id: "prod",
            titulo: "Producto",
            render: (m) => (
              <DosLineas principal={m.producto} secundaria={m.codigo} />
            ),
          },
          {
            id: "tipo",
            titulo: "Movimiento",
            render: (m) => (
              <Typography
                variant="body2"
                sx={{ color: TIPOS_MOV[m.tipo].color, fontWeight: 600 }}
              >
                {TIPOS_MOV[m.tipo].texto}
              </Typography>
            ),
          },
          {
            id: "cant",
            titulo: "Cantidad",
            alinear: "right",
            render: (m) => (
              <Typography variant="body2" sx={{ fontWeight: 600 }}>
                {m.cantidad > 0 ? `+${m.cantidad}` : m.cantidad}
              </Typography>
            ),
          },
          {
            id: "res",
            titulo: "Stock",
            alinear: "right",
            render: (m) => m.stock_resultante,
          },
          {
            id: "mot",
            titulo: "Detalle",
            ocultarEnMovil: true,
            render: (m) => (
              <DosLineas
                principal={m.motivo}
                secundaria={
                  [m.documento, m.proveedor].filter(Boolean).join(", ") ||
                  undefined
                }
              />
            ),
          },
          {
            id: "usr",
            titulo: "Registró",
            ocultarEnMovil: true,
            render: (m) => m.usuario ?? "—",
          },
        ]}
      />
    </Seccion>
  );
}

/** Inventario de urnas, ataúdes y elementos, con stock, proveedor, foto y movimientos */
export default function Inventario() {
  const { puede } = useSesion();
  const qc = useQueryClient();
  const avisar = useAvisar();
  const [params, setParams] = useSearchParams();
  const pestana = params.get("pestana") ?? "productos";
  const [tipo, setTipo] = useState("");
  const [proveedor, setProveedor] = useState("");
  const criticos = params.get("criticos") === "1";
  const [ingreso, setIngreso] = useState<Producto | null>(null);
  const [ajuste, setAjuste] = useState<Producto | null>(null);
  const archivo = useRef<HTMLInputElement>(null);
  const [fotoDe, setFotoDe] = useState<Producto | null>(null);

  const proveedores = useQuery({
    queryKey: ["proveedores", "todos"],
    queryFn: async () =>
      (
        await api.get<Pagina<Proveedor>>("/proveedores", {
          params: { por_pagina: 100 },
        })
      ).data.data,
    enabled: puede(P.proveedoresVer),
  });
  const opcionesProveedor = [
    { valor: "", etiqueta: "Sin proveedor" },
    ...(proveedores.data ?? []).map((p) => ({
      valor: p.id,
      etiqueta: p.nombre,
    })),
  ];

  const refrescar = () =>
    Promise.all(
      ["productos", "movimientos", "panel"].map((k) =>
        qc.invalidateQueries({ queryKey: [k] }),
      ),
    );

  const subirFoto = async (f: File) => {
    if (!fotoDe) return;
    const datos = new FormData();
    datos.append("foto", f);
    try {
      await api.post(`/productos/${fotoDe.id}/foto`, datos);
      await refrescar();
      avisar(
        "Foto actualizada. Se publica en el catálogo si el producto es público.",
      );
    } catch (e) {
      avisar(leerError(e).mensaje, "error");
    }
  };

  return (
    <>
      <Encabezado
        titulo="Inventario"
        descripcion="Stock de urnas, ataúdes y elementos. El stock se descuenta al aceptar una solicitud y se repone si se cancela."
      />
      <Tabs
        value={pestana}
        onChange={(_, v) => setParams({ pestana: v }, { replace: true })}
        sx={{ mb: 2.5, borderBottom: 1, borderColor: "divider" }}
      >
        <Tab value="productos" label="Productos" />
        <Tab value="movimientos" label="Movimientos" />
      </Tabs>

      {pestana === "movimientos" ? (
        <Movimientos />
      ) : (
        <PantallaCrud<Producto, typeof esquemaProducto>
          sinEncabezado
          titulo="Productos"
          singular="Producto"
          recurso="productos"
          buscar="Buscar por nombre o código"
          permisos={{
            crear: P.productosCrear,
            editar: P.productosEditar,
            eliminar: P.productosEliminar,
          }}
          parametros={{
            tipo: tipo || undefined,
            proveedor_id: proveedor || undefined,
            criticos: criticos ? 1 : undefined,
          }}
          filtros={
            <Stack
              direction="row"
              spacing={1}
              sx={{ alignItems: "center", flexWrap: "wrap", gap: 1 }}
            >
              <Chip
                label="Stock crítico"
                clickable
                color={criticos ? "primary" : "default"}
                variant={criticos ? "filled" : "outlined"}
                onClick={() =>
                  setParams(criticos ? {} : { criticos: "1" }, {
                    replace: true,
                  })
                }
              />
              <TextField
                select
                label="Tipo"
                value={tipo}
                onChange={(e) => setTipo(e.target.value)}
                sx={{ width: 180 }}
              >
                <MenuItem value="">Todos</MenuItem>
                {Object.entries(TIPOS_PRODUCTO).map(([k, v]) => (
                  <MenuItem key={k} value={k}>
                    {v}
                  </MenuItem>
                ))}
              </TextField>
              {puede(P.proveedoresVer) && (
                <TextField
                  select
                  label="Proveedor"
                  value={proveedor}
                  onChange={(e) => setProveedor(e.target.value)}
                  sx={{ width: 200 }}
                >
                  <MenuItem value="">Todos</MenuItem>
                  {proveedores.data?.map((p) => (
                    <MenuItem key={p.id} value={p.id}>
                      {p.nombre}
                    </MenuItem>
                  ))}
                </TextField>
              )}
            </Stack>
          }
          esquema={esquemaProducto}
          nuevo={{
            codigo: "",
            nombre: "",
            tipo: "ataud",
            proveedor_id: "",
            material: "",
            color: "",
            medidas: "",
            descripcion: "",
            precio: 0,
            umbral_minimo: 3,
            stock_inicial: 0,
            infantil: false,
            publico: true,
          }}
          aFormulario={(p) => ({
            codigo: p.codigo,
            nombre: p.nombre,
            tipo: p.tipo,
            proveedor_id: p.proveedor_id ?? "",
            material: p.material ?? "",
            color: p.color ?? "",
            medidas: p.medidas ?? "",
            descripcion: p.descripcion ?? "",
            precio: p.precio,
            umbral_minimo: p.umbral_minimo,
            stock_inicial: undefined,
            infantil: p.infantil,
            publico: p.publico,
          })}
          campos={[
            { nombre: "codigo", etiqueta: "Código", ancho: "tercio" },
            { nombre: "nombre", etiqueta: "Nombre", ancho: "completo" },
            {
              nombre: "tipo",
              etiqueta: "Tipo",
              tipo: "seleccion",
              ancho: "medio",
              opciones: Object.entries(TIPOS_PRODUCTO).map(
                ([valor, etiqueta]) => ({ valor, etiqueta }),
              ),
            },
            {
              nombre: "proveedor_id",
              etiqueta: "Proveedor",
              tipo: "seleccion",
              ancho: "medio",
              opciones: opcionesProveedor,
            },
            { nombre: "material", etiqueta: "Material", ancho: "tercio" },
            { nombre: "color", etiqueta: "Color", ancho: "tercio" },
            { nombre: "medidas", etiqueta: "Medidas", ancho: "tercio" },
            {
              nombre: "precio",
              etiqueta: "Precio",
              tipo: "pesos",
              ancho: "tercio",
            },
            {
              nombre: "umbral_minimo",
              etiqueta: "Stock mínimo",
              tipo: "numero",
              ancho: "tercio",
              ayuda: "Bajo este número se avisa",
            },
            {
              nombre: "stock_inicial",
              etiqueta: "Stock inicial",
              tipo: "numero",
              ancho: "tercio",
              mostrar: (v) => v.stock_inicial !== undefined,
            },
            {
              nombre: "descripcion",
              etiqueta: "Descripción para el catálogo",
              tipo: "area",
            },
            {
              nombre: "infantil",
              etiqueta: "Modelo infantil",
              tipo: "interruptor",
              ancho: "medio",
            },
            {
              nombre: "publico",
              etiqueta: "Mostrar en el catálogo público",
              tipo: "interruptor",
              ancho: "medio",
            },
          ]}
          accionesFila={(p) => (
            <>
              {puede(P.mercaderia) && (
                <Tooltip title="Ingresar mercadería">
                  <IconButton
                    size="small"
                    onClick={() => setIngreso(p)}
                    aria-label="Ingresar mercadería"
                  >
                    <MoveToInboxRoundedIcon fontSize="small" />
                  </IconButton>
                </Tooltip>
              )}
              {puede(P.productosEditar) && (
                <Tooltip title="Ajustar stock">
                  <IconButton
                    size="small"
                    onClick={() => setAjuste(p)}
                    aria-label="Ajustar stock"
                  >
                    <TuneRoundedIcon fontSize="small" />
                  </IconButton>
                </Tooltip>
              )}
              {puede(P.productosEditar) && (
                <Tooltip title="Cambiar foto">
                  <IconButton
                    size="small"
                    onClick={() => {
                      setFotoDe(p);
                      archivo.current?.click();
                    }}
                    aria-label="Cambiar foto"
                  >
                    <AddPhotoAlternateRoundedIcon fontSize="small" />
                  </IconButton>
                </Tooltip>
              )}
            </>
          )}
          columnas={[
            {
              id: "prod",
              titulo: "Producto",
              render: (p) => (
                <Stack
                  direction="row"
                  spacing={1.5}
                  sx={{ alignItems: "center" }}
                >
                  {p.foto_url ? (
                    <Box
                      component="img"
                      src={p.foto_url}
                      alt=""
                      loading="lazy"
                      sx={{
                        width: 56,
                        height: 42,
                        objectFit: "cover",
                        borderRadius: 1,
                        bgcolor: "background.default",
                      }}
                    />
                  ) : (
                    <Box
                      sx={{
                        width: 56,
                        height: 42,
                        borderRadius: 1,
                        bgcolor: "background.default",
                      }}
                    />
                  )}
                  <DosLineas
                    principal={p.nombre}
                    secundaria={`${p.codigo}${p.infantil ? ", infantil" : ""}`}
                  />
                </Stack>
              ),
            },
            {
              id: "tipo",
              titulo: "Tipo",
              ocultarEnMovil: true,
              render: (p) => TIPOS_PRODUCTO[p.tipo],
            },
            {
              id: "prov",
              titulo: "Proveedor",
              ocultarEnMovil: true,
              render: (p) => p.proveedor?.nombre ?? "—",
            },
            {
              id: "precio",
              titulo: "Precio",
              alinear: "right",
              render: (p) => pesos(p.precio),
            },
            {
              id: "stock",
              titulo: "Stock",
              render: (p) => (
                <StockChip stock={p.stock} umbral={p.umbral_minimo} />
              ),
            },
            {
              id: "pub",
              titulo: "",
              ancho: 40,
              render: (p) =>
                p.publico ? (
                  <Tooltip title="Visible en el catálogo público">
                    <PublicRoundedIcon
                      sx={{ fontSize: 18, color: "text.secondary" }}
                    />
                  </Tooltip>
                ) : null,
            },
          ]}
        />
      )}

      <input
        ref={archivo}
        type="file"
        hidden
        accept="image/jpeg,image/png,image/webp"
        onChange={(e) => {
          const f = e.target.files?.[0];
          if (f) subirFoto(f);
          e.target.value = "";
        }}
      />

      <FormularioDialogo
        abierto={!!ingreso}
        titulo="Ingresar mercadería"
        descripcion={
          ingreso
            ? `${ingreso.nombre}. Stock actual: ${ingreso.stock}.`
            : undefined
        }
        esquema={esquemaIngreso}
        campos={[
          {
            nombre: "cantidad",
            etiqueta: "Unidades recibidas",
            tipo: "numero",
            ancho: "medio",
          },
          { nombre: "documento", etiqueta: "Factura o guía", ancho: "medio" },
          ...(puede(P.proveedoresVer)
            ? [
                {
                  nombre: "proveedor_id" as const,
                  etiqueta: "Proveedor",
                  tipo: "seleccion" as const,
                  opciones: opcionesProveedor,
                },
              ]
            : []),
          { nombre: "motivo", etiqueta: "Observación (opcional)" },
        ]}
        valoresIniciales={{
          cantidad: 1,
          proveedor_id: ingreso?.proveedor_id ?? "",
          documento: "",
          motivo: "",
        }}
        textoGuardar="Registrar ingreso"
        alCerrar={() => setIngreso(null)}
        alGuardar={async (d) => {
          await api.post(`/productos/${ingreso!.id}/ingresos`, {
            ...d,
            documento: d.documento || null,
            motivo: d.motivo || null,
          });
          await refrescar();
          avisar("Ingreso registrado");
          setIngreso(null);
        }}
      />
      <FormularioDialogo
        abierto={!!ajuste}
        titulo="Ajustar stock"
        descripcion={
          ajuste
            ? `${ajuste.nombre}. El sistema registra ${ajuste.stock}. Indique lo que contó físicamente; la diferencia queda como movimiento de ajuste.`
            : undefined
        }
        esquema={esquemaAjuste}
        campos={[
          {
            nombre: "stock_contado",
            etiqueta: "Stock contado",
            tipo: "numero",
          },
          { nombre: "motivo", etiqueta: "Motivo", tipo: "area" },
        ]}
        valoresIniciales={{ stock_contado: ajuste?.stock ?? 0, motivo: "" }}
        textoGuardar="Ajustar"
        alCerrar={() => setAjuste(null)}
        alGuardar={async (d) => {
          await api.post(`/productos/${ajuste!.id}/ajuste`, d);
          await refrescar();
          avisar("Stock ajustado");
          setAjuste(null);
        }}
      />
    </>
  );
}
