import AddRoundedIcon from "@mui/icons-material/AddRounded";
import { Box, Button, Chip, Stack, Typography } from "@mui/material";
import { useQuery, useQueryClient } from "@tanstack/react-query";
import { useState } from "react";
import { z } from "zod";
import { api, leerError } from "../../api/cliente";
import type { SolicitudDiasLibres } from "../../api/tipos";
import { useSesion } from "../../auth/Sesion";
import { P } from "../../auth/permisos";
import { Confirmar } from "../../components/Confirmar";
import { Encabezado } from "../../components/Encabezado";
import { FormularioDialogo } from "../../components/Formulario";
import { useAvisar } from "../../components/Notificador";
import { Seccion } from "../../components/Seccion";
import { DosLineas, Tabla, type Columna } from "../../components/Tabla";
import { ahoraLocal, fecha } from "../../lib/formato";

const ESTADOS = {
  pendiente: { etiqueta: "Pendiente", color: "warning" },
  aprobada: { etiqueta: "Aprobada", color: "success" },
  rechazada: { etiqueta: "Rechazada", color: "error" },
} as const;

const cuantos = (n: number) => (n === 1 ? "1 día" : `${n} días`);
/** Cada fecha en una sola línea: en el teléfono el período se corta solo entre ambas */
const entera = (f: string) => (
  <Box component="span" sx={{ whiteSpace: "nowrap" }}>
    {fecha(f)}
  </Box>
);
const periodo = (s: SolicitudDiasLibres) =>
  s.desde === s.hasta ? (
    entera(s.desde)
  ) : (
    <>
      {entera(s.desde)} al {entera(s.hasta)}
    </>
  );

const numeroDeDias = (maximo: number) =>
  z
    .number({ message: "Indique cuántos días" })
    .int("Debe ser un número entero")
    .min(1, "Mínimo 1 día")
    .max(maximo, `Máximo ${cuantos(maximo)}`);

const esquemaPedido = (hoy: string) =>
  z.object({
    desde: z
      .string()
      .min(1, "Indique la fecha")
      .refine((d) => d >= hoy, "La fecha no puede ser anterior a hoy"),
    dias: numeroDeDias(30),
    motivo: z
      .string()
      .trim()
      .min(5, "Explique el motivo (mínimo 5 caracteres)")
      .max(250, "Máximo 250 caracteres"),
  });

/**
 * Días libres: el operario y el coordinador los piden con su motivo; el
 * administrador aprueba la cantidad de días o los rechaza explicando por qué
 */
export default function DiasLibres() {
  const { sesion, puede } = useSesion();
  const qc = useQueryClient();
  const avisar = useAvisar();
  const [pidiendo, setPidiendo] = useState(false);
  const [aprobar, setAprobar] = useState<SolicitudDiasLibres | null>(null);
  const [rechazar, setRechazar] = useState<SolicitudDiasLibres | null>(null);
  const [enviando, setEnviando] = useState(false);
  const solicitar = puede(P.diasLibresSolicitar);
  const evaluar = puede(P.diasLibresEvaluar);
  const hoy = ahoraLocal().format("YYYY-MM-DD");

  const { data, isFetching } = useQuery({
    queryKey: ["dias-libres"],
    queryFn: async () =>
      (await api.get<SolicitudDiasLibres[]>("/dias-libres")).data,
    refetchInterval: 60_000,
  });
  const refrescar = () =>
    Promise.all([
      qc.invalidateQueries({ queryKey: ["dias-libres"] }),
      qc.invalidateQueries({ queryKey: ["ausencias"] }),
    ]);

  const columnas = (conPersona: boolean): Columna<SolicitudDiasLibres>[] => [
    ...(conPersona
      ? [
          {
            id: "persona",
            titulo: "Persona",
            render: (s: SolicitudDiasLibres) => s.usuario?.nombre,
          },
        ]
      : []),
    {
      id: "fechas",
      titulo: "Fechas",
      render: (s) => (
        <DosLineas
          principal={periodo(s)}
          secundaria={
            s.dias_aprobados === null
              ? `Pide ${cuantos(s.dias)}`
              : `Aprobados ${s.dias_aprobados} de ${s.dias}`
          }
        />
      ),
    },
    {
      id: "motivo",
      titulo: "Motivo",
      render: (s) =>
        s.motivo_rechazo ? (
          <>
            <Typography variant="body2">{s.motivo}</Typography>
            <Typography variant="body2" color="error">
              Motivo del rechazo: {s.motivo_rechazo}
            </Typography>
          </>
        ) : (
          s.motivo
        ),
    },
    {
      id: "estado",
      titulo: "Estado",
      render: (s) => (
        <Chip
          size="small"
          variant="outlined"
          color={ESTADOS[s.estado].color}
          label={ESTADOS[s.estado].etiqueta}
        />
      ),
    },
  ];

  const pendientes = data?.filter((s) => s.estado === "pendiente");

  return (
    <>
      <Encabezado
        titulo="Días libres"
        descripcion={
          evaluar
            ? "Solicitudes del personal. Al aprobar se registra la ausencia y la persona no puede asignarse a servicios en esas fechas."
            : "Pida días libres indicando el motivo. La administración le responderá por los avisos del sistema."
        }
        acciones={
          solicitar && (
            <Button
              variant="contained"
              startIcon={<AddRoundedIcon />}
              onClick={() => setPidiendo(true)}
            >
              Pedir días libres
            </Button>
          )
        }
      />
      <Stack spacing={3}>
        {evaluar && (
          <Seccion titulo="Por resolver" sinRelleno>
            <Tabla
              filas={pendientes}
              clave={(s) => s.id}
              cargando={isFetching}
              vacio="No hay solicitudes pendientes."
              columnas={[
                ...columnas(true).filter((c) => c.id !== "estado"),
                {
                  id: "acciones",
                  titulo: "",
                  alinear: "right",
                  render: (s) =>
                    s.usuario_id === sesion?.id ? (
                      <Chip
                        size="small"
                        label="La resuelve otro administrador"
                      />
                    ) : (
                      <Stack
                        direction="row"
                        spacing={1}
                        sx={{ justifyContent: "flex-end" }}
                      >
                        <Button
                          size="small"
                          variant="contained"
                          onClick={() => setAprobar(s)}
                        >
                          Aprobar
                        </Button>
                        <Button
                          size="small"
                          color="error"
                          onClick={() => setRechazar(s)}
                        >
                          Rechazar
                        </Button>
                      </Stack>
                    ),
                },
              ]}
            />
          </Seccion>
        )}
        <Seccion titulo={evaluar ? "Resueltas" : "Mis solicitudes"} sinRelleno>
          <Tabla
            filas={
              evaluar ? data?.filter((s) => s.estado !== "pendiente") : data
            }
            clave={(s) => s.id}
            cargando={isFetching}
            vacio={
              evaluar
                ? "Todavía no se ha resuelto ninguna solicitud."
                : "Todavía no ha pedido días libres."
            }
            columnas={columnas(evaluar)}
          />
        </Seccion>
      </Stack>

      <FormularioDialogo
        abierto={pidiendo}
        titulo="Pedir días libres"
        descripcion="Los días se cuentan corridos desde la fecha de inicio."
        esquema={esquemaPedido(hoy)}
        campos={[
          { nombre: "desde", etiqueta: "Desde", tipo: "fecha", ancho: "medio" },
          {
            nombre: "dias",
            etiqueta: "Cantidad de días",
            tipo: "numero",
            ancho: "medio",
          },
          { nombre: "motivo", etiqueta: "Motivo", tipo: "area" },
        ]}
        valoresIniciales={{ desde: hoy, dias: 1, motivo: "" }}
        textoGuardar="Enviar solicitud"
        alCerrar={() => setPidiendo(false)}
        alGuardar={async (d) => {
          await api.post("/dias-libres", d);
          await refrescar();
          avisar("Solicitud enviada a la administración");
          setPidiendo(false);
        }}
      />
      <FormularioDialogo
        abierto={!!aprobar}
        titulo="Aprobar días libres"
        descripcion={
          aprobar &&
          `${aprobar.usuario?.nombre} pide ${cuantos(aprobar.dias)} desde el ${fecha(aprobar.desde)}. Motivo: ${aprobar.motivo}`
        }
        esquema={z.object({ dias: numeroDeDias(aprobar?.dias ?? 30) })}
        campos={[
          {
            nombre: "dias",
            etiqueta: "Días que aprueba",
            tipo: "numero",
            ayuda: "Puede aprobar todos los pedidos o solo una parte.",
          },
        ]}
        valoresIniciales={{ dias: aprobar?.dias }}
        textoGuardar="Aprobar"
        alCerrar={() => setAprobar(null)}
        alGuardar={async (d) => {
          await api.post(`/dias-libres/${aprobar!.id}/aprobar`, d);
          await refrescar();
          avisar("Días libres aprobados");
          setAprobar(null);
        }}
      />
      <Confirmar
        abierto={!!rechazar}
        titulo="Rechazar solicitud"
        mensaje={`${rechazar?.usuario?.nombre ?? "La persona"} recibirá un aviso con el motivo.`}
        textoConfirmar="Rechazar"
        peligro
        pedirMotivo={{ etiqueta: "Motivo del rechazo" }}
        cargando={enviando}
        alCerrar={() => setRechazar(null)}
        alConfirmar={async (motivo) => {
          setEnviando(true);
          try {
            await api.post(`/dias-libres/${rechazar!.id}/rechazar`, { motivo });
            avisar("Solicitud rechazada");
          } catch (e) {
            avisar(leerError(e).mensaje, "error");
          }
          await refrescar();
          setEnviando(false);
          setRechazar(null);
        }}
      />
    </>
  );
}
