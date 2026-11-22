import PhoneRoundedIcon from "@mui/icons-material/PhoneRounded";
import { Box, Button, Stack, Typography } from "@mui/material";
import { useQuery } from "@tanstack/react-query";
import { useNavigate } from "react-router-dom";
import { api } from "../../api/cliente";
import type { FilaCobranza } from "../../api/tipos";
import { colores } from "../../app/tema";
import { Encabezado } from "../../components/Encabezado";
import { EstadoCuotaChip } from "../../components/Estados";
import { Seccion } from "../../components/Seccion";
import { DosLineas, Tabla } from "../../components/Tabla";
import { fecha, pesos, relativo } from "../../lib/formato";

/** Seguimiento de cuotas atrasadas y de las que vencen en los próximos 15 días */
export default function Cobranza() {
  const navegar = useNavigate();
  const { data, isFetching } = useQuery({
    queryKey: ["cobranza"],
    queryFn: async () => (await api.get<FilaCobranza[]>("/cobranza")).data,
  });
  const atrasadas = data?.filter((c) => c.estado === "atrasada") ?? [];
  const porVencer = data?.filter((c) => c.estado === "pendiente") ?? [];
  const deuda = atrasadas.reduce((a, c) => a + c.monto - Number(c.pagado), 0);

  return (
    <>
      <Encabezado
        titulo="Cobranza"
        descripcion="Cuotas atrasadas y por vencer en los próximos 15 días. Cada día a las 07:00 el sistema avisa las que vencieron sin pagarse."
      />
      <Box
        sx={{
          display: "grid",
          gap: 3,
          gridTemplateColumns: { xs: "1fr", md: "1fr 1fr 1fr" },
          mb: 3,
        }}
      >
        <Resumen
          titulo="Monto atrasado"
          valor={pesos(deuda)}
          critico={deuda > 0}
        />
        <Resumen
          titulo="Cuotas atrasadas"
          valor={String(atrasadas.length)}
          critico={atrasadas.length > 0}
        />
        <Resumen titulo="Vencen en 15 días" valor={String(porVencer.length)} />
      </Box>
      <Seccion sinRelleno>
        <Tabla
          filas={data}
          clave={(c) => c.id}
          cargando={isFetching}
          alClic={(c) =>
            navegar(`/expedientes/${c.expediente_id}?pestana=pagos`)
          }
          vacio="No hay cuotas atrasadas ni por vencer."
          columnas={[
            {
              id: "cli",
              titulo: "Contratante",
              render: (c) => (
                <DosLineas principal={c.cliente} secundaria={c.codigo} />
              ),
            },
            { id: "cuota", titulo: "Cuota", render: (c) => `N.º ${c.numero}` },
            {
              id: "venc",
              titulo: "Vencimiento",
              render: (c) => (
                <DosLineas
                  principal={fecha(c.vencimiento)}
                  secundaria={relativo(`${c.vencimiento}T12:00:00Z`)}
                />
              ),
            },
            {
              id: "monto",
              titulo: "Por pagar",
              alinear: "right",
              render: (c) => (
                <Typography variant="body2" sx={{ fontWeight: 600 }}>
                  {pesos(c.monto - Number(c.pagado))}
                </Typography>
              ),
            },
            {
              id: "estado",
              titulo: "Estado",
              render: (c) => <EstadoCuotaChip estado={c.estado} />,
            },
            {
              id: "tel",
              titulo: "",
              alinear: "right",
              render: (c) =>
                c.telefono ? (
                  <Button
                    size="small"
                    startIcon={<PhoneRoundedIcon />}
                    href={`tel:${c.telefono.replace(/\s/g, "")}`}
                    onClick={(e) => e.stopPropagation()}
                  >
                    {c.telefono}
                  </Button>
                ) : null,
            },
          ]}
        />
      </Seccion>
    </>
  );
}

function Resumen({
  titulo,
  valor,
  critico,
}: {
  titulo: string;
  valor: string;
  critico?: boolean;
}) {
  return (
    <Stack
      sx={{
        p: 2.5,
        borderRadius: 2.5,
        border: `1px solid ${critico ? "#F1C7C2" : colores.linea}`,
        bgcolor: critico ? "#FDF3F2" : "background.paper",
      }}
    >
      <Typography variant="body2" color="text.secondary">
        {titulo}
      </Typography>
      <Typography
        sx={{
          fontSize: "1.6rem",
          fontWeight: 600,
          color: critico ? colores.critico : undefined,
        }}
      >
        {valor}
      </Typography>
    </Stack>
  );
}
