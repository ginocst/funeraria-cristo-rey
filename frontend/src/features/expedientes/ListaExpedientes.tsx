import AddRoundedIcon from "@mui/icons-material/AddRounded";
import {
  Button,
  Chip,
  MenuItem,
  Stack,
  TextField,
  Typography,
} from "@mui/material";
import { keepPreviousData, useQuery } from "@tanstack/react-query";
import {
  Link as RouterLink,
  useNavigate,
  useSearchParams,
} from "react-router-dom";
import { api, type Pagina } from "../../api/cliente";
import type { FilaExpediente } from "../../api/tipos";
import { useSesion } from "../../auth/Sesion";
import { Buscador } from "../../components/Buscador";
import { Encabezado } from "../../components/Encabezado";
import {
  ESTADOS_EXPEDIENTE,
  EstadoExpedienteChip,
} from "../../components/Estados";
import { Seccion } from "../../components/Seccion";
import { DosLineas, Tabla } from "../../components/Tabla";
import { fecha, pesos } from "../../lib/formato";
import { formatearRut } from "../../lib/rut";

export default function ListaExpedientes() {
  const { puede } = useSesion();
  const navegar = useNavigate();
  const [params, setParams] = useSearchParams();
  const q = params.get("q") ?? "";
  const estado = params.get("estado") ?? "";
  const conSaldo = params.get("con_saldo") === "1";
  const pagina = Number(params.get("pagina") ?? 1);
  const cambiar = (c: Record<string, string>) => {
    const p = new URLSearchParams(params);
    Object.entries(c).forEach(([k, v]) => (v ? p.set(k, v) : p.delete(k)));
    if (!("pagina" in c)) p.delete("pagina");
    setParams(p, { replace: true });
  };

  const { data, isFetching } = useQuery({
    queryKey: ["expedientes", { q, estado, conSaldo, pagina }],
    queryFn: async () =>
      (
        await api.get<Pagina<FilaExpediente>>("/expedientes", {
          params: {
            q: q || undefined,
            estado: estado || undefined,
            con_saldo: conSaldo ? 1 : undefined,
            page: pagina,
          },
        })
      ).data,
    placeholderData: keepPreviousData,
  });

  return (
    <>
      <Encabezado
        titulo="Expedientes"
        descripcion="Un expediente reúne todo el servicio de una familia: contratante, fallecido, actividades, solicitudes, pagos y documentos."
        acciones={
          puede("expedientes.crear") && (
            <Button
              variant="contained"
              startIcon={<AddRoundedIcon />}
              component={RouterLink}
              to="/expedientes/nuevo"
            >
              Nuevo expediente
            </Button>
          )
        }
      />
      <Seccion sinRelleno>
        <Stack
          direction={{ xs: "column", md: "row" }}
          spacing={1.5}
          sx={{ p: 2, alignItems: { md: "center" } }}
        >
          <Buscador
            valor={q}
            alCambiar={(v) => cambiar({ q: v })}
            placeholder="Código, fallecido, contratante o RUT"
            ancho={380}
          />
          <TextField
            select
            label="Estado"
            value={estado}
            onChange={(e) => cambiar({ estado: e.target.value })}
            sx={{ width: 200 }}
          >
            <MenuItem value="">Todos</MenuItem>
            {Object.entries(ESTADOS_EXPEDIENTE).map(([k, v]) => (
              <MenuItem key={k} value={k}>
                {v.etiqueta}
              </MenuItem>
            ))}
          </TextField>
          <Chip
            label="Con saldo pendiente"
            clickable
            color={conSaldo ? "primary" : "default"}
            variant={conSaldo ? "filled" : "outlined"}
            onClick={() => cambiar({ con_saldo: conSaldo ? "" : "1" })}
          />
        </Stack>
        <Tabla
          filas={data?.data}
          clave={(f) => f.id}
          cargando={isFetching}
          alClic={(f) => navegar(`/expedientes/${f.id}`)}
          paginacion={
            data
              ? {
                  pagina,
                  total: data.total,
                  porPagina: data.per_page,
                  alCambiar: (p) => cambiar({ pagina: String(p) }),
                }
              : undefined
          }
          columnas={[
            {
              id: "codigo",
              titulo: "Expediente",
              render: (f) => (
                <DosLineas
                  principal={f.codigo}
                  secundaria={fecha(f.created_at)}
                />
              ),
            },
            {
              id: "difunto",
              titulo: "Fallecido",
              render: (f) => (
                <Typography variant="body2" sx={{ fontWeight: 600 }}>
                  {f.difunto}
                </Typography>
              ),
            },
            {
              id: "cliente",
              titulo: "Contratante",
              ocultarEnMovil: true,
              render: (f) => (
                <DosLineas
                  principal={f.cliente}
                  secundaria={formatearRut(f.cliente_rut)}
                />
              ),
            },
            {
              id: "sucursal",
              titulo: "Sucursal",
              ocultarEnMovil: true,
              render: (f) => f.sucursal,
            },
            {
              id: "estado",
              titulo: "Estado",
              render: (f) => <EstadoExpedienteChip estado={f.estado} />,
            },
            {
              id: "total",
              titulo: "Total",
              alinear: "right",
              ocultarEnMovil: true,
              render: (f) => pesos(f.total),
            },
            {
              id: "saldo",
              titulo: "Saldo",
              alinear: "right",
              render: (f) => (
                <Typography
                  variant="body2"
                  sx={{
                    fontWeight: 600,
                    color:
                      Number(f.saldo) > 0 ? "warning.main" : "text.secondary",
                  }}
                >
                  {pesos(f.saldo)}
                </Typography>
              ),
            },
          ]}
        />
      </Seccion>
    </>
  );
}
