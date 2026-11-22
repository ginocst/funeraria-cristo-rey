import BlockRoundedIcon from "@mui/icons-material/BlockRounded";
import CheckCircleRoundedIcon from "@mui/icons-material/CheckCircleRounded";
import DoneAllRoundedIcon from "@mui/icons-material/DoneAllRounded";
import ErrorOutlineRoundedIcon from "@mui/icons-material/ErrorOutlineRounded";
import FolderOpenRoundedIcon from "@mui/icons-material/FolderOpenRounded";
import HighlightOffRoundedIcon from "@mui/icons-material/HighlightOffRounded";
import HourglassTopRoundedIcon from "@mui/icons-material/HourglassTopRounded";
import Inventory2RoundedIcon from "@mui/icons-material/Inventory2Rounded";
import LocalShippingRoundedIcon from "@mui/icons-material/LocalShippingRounded";
import RateReviewRoundedIcon from "@mui/icons-material/RateReviewRounded";
import ScheduleRoundedIcon from "@mui/icons-material/ScheduleRounded";
import WarningAmberRoundedIcon from "@mui/icons-material/WarningAmberRounded";
import { Chip, type ChipProps } from "@mui/material";
import type { ReactElement } from "react";
import type { Cuota, EstadoExpediente, EstadoSolicitud } from "../api/tipos";
import { colores } from "../app/tema";

type Estilo = {
  etiqueta: string;
  color: string;
  fondo: string;
  icono: ReactElement;
};

const tono = (color: string, fondo: string) => ({ color, fondo });

export const ESTADOS_SOLICITUD: Record<EstadoSolicitud, Estilo> = {
  en_revision: {
    etiqueta: "En revisión",
    ...tono(colores.info, "#E4F1F5"),
    icono: <HourglassTopRoundedIcon />,
  },
  con_observaciones: {
    etiqueta: "Con observaciones",
    ...tono(colores.alerta, "#FBF0DC"),
    icono: <RateReviewRoundedIcon />,
  },
  aceptada: {
    etiqueta: "Aceptada",
    ...tono(colores.ok, "#E3F0E8"),
    icono: <CheckCircleRoundedIcon />,
  },
  ejecutada: {
    etiqueta: "Ejecutada",
    ...tono(colores.piedra, "#EFECEE"),
    icono: <DoneAllRoundedIcon />,
  },
  no_aplica: {
    etiqueta: "No aplica",
    ...tono(colores.critico, "#FBE7E5"),
    icono: <HighlightOffRoundedIcon />,
  },
  cancelada: {
    etiqueta: "Cancelada",
    ...tono(colores.piedra, "#EFECEE"),
    icono: <BlockRoundedIcon />,
  },
};

export const ESTADOS_EXPEDIENTE: Record<EstadoExpediente, Estilo> = {
  abierto: {
    etiqueta: "Abierto",
    ...tono(colores.info, "#E4F1F5"),
    icono: <FolderOpenRoundedIcon />,
  },
  en_servicio: {
    etiqueta: "En servicio",
    ...tono(colores.vino, colores.vinoTenue),
    icono: <LocalShippingRoundedIcon />,
  },
  cerrado: {
    etiqueta: "Cerrado",
    ...tono(colores.piedra, "#EFECEE"),
    icono: <DoneAllRoundedIcon />,
  },
  anulado: {
    etiqueta: "Anulado",
    ...tono(colores.piedra, "#EFECEE"),
    icono: <BlockRoundedIcon />,
  },
};

export const ESTADOS_CUOTA: Record<Cuota["estado"], Estilo> = {
  pagada: {
    etiqueta: "Pagada",
    ...tono(colores.ok, "#E3F0E8"),
    icono: <CheckCircleRoundedIcon />,
  },
  pendiente: {
    etiqueta: "Por vencer",
    ...tono(colores.info, "#E4F1F5"),
    icono: <ScheduleRoundedIcon />,
  },
  atrasada: {
    etiqueta: "Atrasada",
    ...tono(colores.critico, "#FBE7E5"),
    icono: <ErrorOutlineRoundedIcon />,
  },
};

function Insignia({
  estilo,
  ...props
}: { estilo: Estilo } & Omit<ChipProps, "label" | "icon">) {
  return (
    <Chip
      size="small"
      icon={estilo.icono}
      label={estilo.etiqueta}
      {...props}
      sx={{
        color: estilo.color,
        bgcolor: estilo.fondo,
        "& .MuiChip-icon": { color: estilo.color, fontSize: 16 },
        ...props.sx,
      }}
    />
  );
}

export const EstadoSolicitudChip = ({
  estado,
}: {
  estado: EstadoSolicitud;
}) => <Insignia estilo={ESTADOS_SOLICITUD[estado]} />;
export const EstadoExpedienteChip = ({
  estado,
}: {
  estado: EstadoExpediente;
}) => <Insignia estilo={ESTADOS_EXPEDIENTE[estado]} />;
export const EstadoCuotaChip = ({ estado }: { estado: Cuota["estado"] }) => (
  <Insignia estilo={ESTADOS_CUOTA[estado]} />
);

export function StockChip({
  stock,
  umbral,
}: {
  stock: number;
  umbral: number;
}) {
  const critico = stock <= umbral;
  return (
    <Insignia
      estilo={{
        etiqueta: `${stock} ${stock === 1 ? "unidad" : "unidades"}`,
        ...(stock === 0
          ? tono(colores.critico, "#FBE7E5")
          : critico
            ? tono(colores.alerta, "#FBF0DC")
            : tono(colores.ok, "#E3F0E8")),
        icono: critico ? (
          <WarningAmberRoundedIcon />
        ) : (
          <Inventory2RoundedIcon />
        ),
      }}
    />
  );
}

export const TIPOS_PRODUCTO: Record<string, string> = {
  ataud: "Ataúd",
  urna: "Urna",
  urna_cremacion: "Urna de cremación",
  elemento: "Elemento",
};

export const TIPOS_ACTIVIDAD: Record<string, string> = {
  retiro: "Retiro",
  velatorio: "Velatorio",
  misa: "Misa o responso",
  traslado: "Traslado",
  sepultacion: "Sepultación",
  otra: "Otra",
};

export const TIPOS_DOCUMENTO: Record<string, string> = {
  certificado_defuncion: "Certificado de defunción",
  carnet_difunto: "Cédula del fallecido",
  carnet_contratante: "Cédula del contratante",
  contrato: "Contrato firmado",
  otro: "Otro documento",
};
