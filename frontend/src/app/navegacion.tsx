import AssessmentRoundedIcon from "@mui/icons-material/AssessmentRounded";
import BadgeRoundedIcon from "@mui/icons-material/BadgeRounded";
import BeachAccessRoundedIcon from "@mui/icons-material/BeachAccessRounded";
import CalendarMonthRoundedIcon from "@mui/icons-material/CalendarMonthRounded";
import DashboardRoundedIcon from "@mui/icons-material/DashboardRounded";
import DirectionsCarRoundedIcon from "@mui/icons-material/DirectionsCarRounded";
import EventBusyRoundedIcon from "@mui/icons-material/EventBusyRounded";
import FactCheckRoundedIcon from "@mui/icons-material/FactCheckRounded";
import FolderSharedRoundedIcon from "@mui/icons-material/FolderSharedRounded";
import HandshakeRoundedIcon from "@mui/icons-material/HandshakeRounded";
import HistoryEduRoundedIcon from "@mui/icons-material/HistoryEduRounded";
import Inventory2RoundedIcon from "@mui/icons-material/Inventory2Rounded";
import LocalShippingRoundedIcon from "@mui/icons-material/LocalShippingRounded";
import MeetingRoomRoundedIcon from "@mui/icons-material/MeetingRoomRounded";
import PaymentsRoundedIcon from "@mui/icons-material/PaymentsRounded";
import PeopleAltRoundedIcon from "@mui/icons-material/PeopleAltRounded";
import ReceiptLongRoundedIcon from "@mui/icons-material/ReceiptLongRounded";
import RequestQuoteRoundedIcon from "@mui/icons-material/RequestQuoteRounded";
import RouteRoundedIcon from "@mui/icons-material/RouteRounded";
import SettingsRoundedIcon from "@mui/icons-material/SettingsRounded";
import StorefrontRoundedIcon from "@mui/icons-material/StorefrontRounded";
import VolunteerActivismRoundedIcon from "@mui/icons-material/VolunteerActivismRounded";
import type { ReactElement } from "react";
import { P } from "../auth/permisos";

export type ItemMenu = {
  a: string;
  texto: string;
  icono: ReactElement;
  permisos: string[];
};
export type GrupoMenu = { titulo?: string; items: ItemMenu[] };

/** Menú lateral: cada opción declara qué permisos la muestran (igual que la API) */
export const MENU: GrupoMenu[] = [
  {
    items: [
      {
        a: "/panel",
        texto: "Panel",
        icono: <DashboardRoundedIcon />,
        permisos: [P.reportesOperativos],
      },
      {
        a: "/itinerario",
        texto: "Mi itinerario",
        icono: <RouteRoundedIcon />,
        permisos: [P.itinerario],
      },
      {
        a: "/dias-libres",
        texto: "Días libres",
        icono: <BeachAccessRoundedIcon />,
        permisos: [P.diasLibresVer],
      },
    ],
  },
  {
    titulo: "Servicios",
    items: [
      {
        a: "/solicitudes",
        texto: "Solicitudes",
        icono: <FactCheckRoundedIcon />,
        permisos: [P.solicitudesVer],
      },
      {
        a: "/expedientes",
        texto: "Expedientes",
        icono: <FolderSharedRoundedIcon />,
        permisos: [P.expedientesVer],
      },
      {
        a: "/calendario",
        texto: "Calendario",
        icono: <CalendarMonthRoundedIcon />,
        permisos: [P.calendario],
      },
      {
        a: "/clientes",
        texto: "Clientes",
        icono: <PeopleAltRoundedIcon />,
        permisos: [P.clientesVer],
      },
      {
        a: "/convenios",
        texto: "Convenios",
        icono: <HandshakeRoundedIcon />,
        permisos: [P.conveniosVer],
      },
    ],
  },
  {
    titulo: "Inventario y flota",
    items: [
      {
        a: "/inventario",
        texto: "Inventario",
        icono: <Inventory2RoundedIcon />,
        permisos: [P.productosVer],
      },
      {
        a: "/proveedores",
        texto: "Proveedores",
        icono: <StorefrontRoundedIcon />,
        permisos: [P.proveedoresVer],
      },
      {
        a: "/vehiculos",
        texto: "Vehículos",
        icono: <DirectionsCarRoundedIcon />,
        permisos: [P.vehiculosVer],
      },
      {
        a: "/salas",
        texto: "Salas velatorias",
        icono: <MeetingRoomRoundedIcon />,
        permisos: [P.solicitudesVer],
      },
      {
        a: "/ausencias",
        texto: "Ausencias",
        icono: <EventBusyRoundedIcon />,
        permisos: [P.ausenciasVer],
      },
    ],
  },
  {
    titulo: "Finanzas",
    items: [
      {
        a: "/pagos",
        texto: "Pagos",
        icono: <PaymentsRoundedIcon />,
        permisos: [P.pagosVer],
      },
      {
        a: "/cobranza",
        texto: "Cobranza",
        icono: <RequestQuoteRoundedIcon />,
        permisos: [P.pagosVer],
      },
      {
        a: "/gastos",
        texto: "Gastos",
        icono: <ReceiptLongRoundedIcon />,
        permisos: [P.gastos],
      },
      {
        a: "/donaciones",
        texto: "Donaciones",
        icono: <VolunteerActivismRoundedIcon />,
        permisos: [P.donaciones],
      },
      {
        a: "/reportes",
        texto: "Reportes",
        icono: <AssessmentRoundedIcon />,
        permisos: [P.reportesFinancieros],
      },
    ],
  },
  {
    titulo: "Administración",
    items: [
      {
        a: "/usuarios",
        texto: "Usuarios",
        icono: <BadgeRoundedIcon />,
        permisos: [P.usuarios],
      },
      {
        a: "/configuracion",
        texto: "Configuración",
        icono: <SettingsRoundedIcon />,
        permisos: [P.configuracion],
      },
      {
        a: "/bitacora",
        texto: "Bitácora",
        icono: <HistoryEduRoundedIcon />,
        permisos: [P.bitacora],
      },
    ],
  },
];

export const ICONO_TRASLADO = LocalShippingRoundedIcon;
