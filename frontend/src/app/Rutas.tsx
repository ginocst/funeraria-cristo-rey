import { Box, Button, CircularProgress, Typography } from "@mui/material";
import { lazy, Suspense, type ReactNode } from "react";
import {
  createBrowserRouter,
  Navigate,
  Outlet,
  RouterProvider,
  useLocation,
  Link as RouterLink,
} from "react-router-dom";
import { P } from "../auth/permisos";
import { useSesion } from "../auth/Sesion";
import { Estructura } from "./Estructura";

const Panel = lazy(() => import("../features/panel/Panel"));
const IniciarSesion = lazy(() => import("../features/acceso/IniciarSesion"));
const ConfigurarMfa = lazy(() => import("../features/acceso/ConfigurarMfa"));
const Recuperar = lazy(() =>
  import("../features/acceso/Recuperar").then((m) => ({
    default: m.Recuperar,
  })),
);
const Restablecer = lazy(() =>
  import("../features/acceso/Recuperar").then((m) => ({
    default: m.Restablecer,
  })),
);
const ListaSolicitudes = lazy(
  () => import("../features/solicitudes/ListaSolicitudes"),
);
const DetalleSolicitud = lazy(
  () => import("../features/solicitudes/DetalleSolicitud"),
);
const FormSolicitud = lazy(
  () => import("../features/solicitudes/FormSolicitud"),
);
const ListaExpedientes = lazy(
  () => import("../features/expedientes/ListaExpedientes"),
);
const NuevoExpediente = lazy(
  () => import("../features/expedientes/NuevoExpediente"),
);
const DetalleExpediente = lazy(
  () => import("../features/expedientes/DetalleExpediente"),
);
const Calendario = lazy(() => import("../features/calendario/Calendario"));
const Clientes = lazy(() => import("../features/clientes/Clientes"));
const FichaCliente = lazy(() =>
  import("../features/clientes/Clientes").then((m) => ({
    default: m.FichaCliente,
  })),
);
const Convenios = lazy(() => import("../features/clientes/Convenios"));
const Inventario = lazy(() => import("../features/inventario/Inventario"));
const Proveedores = lazy(() => import("../features/inventario/Proveedores"));
const Vehiculos = lazy(() => import("../features/flota/Vehiculos"));
const Ausencias = lazy(() => import("../features/flota/Ausencias"));
const DiasLibres = lazy(() => import("../features/flota/DiasLibres"));
const Itinerario = lazy(() => import("../features/flota/Itinerario"));
const Pagos = lazy(() => import("../features/finanzas/Pagos"));
const Cobranza = lazy(() => import("../features/finanzas/Cobranza"));
const Gastos = lazy(() => import("../features/finanzas/Gastos"));
const Salas = lazy(() => import("../features/salas/Salas"));
const Donaciones = lazy(() => import("../features/donaciones/Donaciones"));
const Reportes = lazy(() => import("../features/reportes/Reportes"));
const Usuarios = lazy(() => import("../features/admin/Usuarios"));
const Configuracion = lazy(() => import("../features/admin/Configuracion"));
const Bitacora = lazy(() => import("../features/admin/Bitacora"));
const Catalogo = lazy(() => import("../features/publico/Catalogo"));
const Portada = lazy(() => import("../features/publico/Portada"));

function Cargando() {
  return (
    <Box sx={{ display: "grid", placeItems: "center", minHeight: "50vh" }}>
      <CircularProgress />
    </Box>
  );
}

/** Exige sesión iniciada y, para Administrador y Coordinador, el MFA configurado */
function Protegido() {
  const { sesion, cargando } = useSesion();
  const ubicacion = useLocation();
  if (cargando) return <Cargando />;
  if (!sesion)
    return (
      <Navigate
        to="/ingresar"
        replace
        state={{ desde: ubicacion.pathname + ubicacion.search }}
      />
    );
  if (sesion.mfa_requerido && !sesion.mfa_activo)
    return (
      <Suspense fallback={<Cargando />}>
        <ConfigurarMfa />
      </Suspense>
    );
  return <Estructura />;
}

/** Oculta la pantalla si el perfil no tiene el permiso (la API también lo rechaza) */
function Requiere({
  permisos,
  children,
}: {
  permisos: string[];
  children: ReactNode;
}) {
  const { puede } = useSesion();
  if (!puede(...permisos)) {
    return (
      <Box sx={{ py: 8, textAlign: "center" }}>
        <Typography variant="h2">Sin acceso</Typography>
        <Typography color="text.secondary" sx={{ mt: 1 }}>
          Su perfil no tiene permiso para ver esta sección.
        </Typography>
        <Button component={RouterLink} to="/panel" sx={{ mt: 2 }}>
          Ir al panel
        </Button>
      </Box>
    );
  }
  return <>{children}</>;
}

function Inicio() {
  const { puede } = useSesion();
  return puede(P.reportesOperativos) ? (
    <Panel />
  ) : (
    <Navigate to="/itinerario" replace />
  );
}

const r = (permisos: string[], elemento: ReactNode) => (
  <Requiere permisos={permisos}>{elemento}</Requiere>
);

const enrutador = createBrowserRouter([
  {
    path: "/",
    element: (
      <Suspense fallback={<Cargando />}>
        <Portada />
      </Suspense>
    ),
  },
  {
    path: "/catalogo",
    element: (
      <Suspense fallback={<Cargando />}>
        <Catalogo />
      </Suspense>
    ),
  },
  {
    element: (
      <Suspense fallback={<Cargando />}>
        <Outlet />
      </Suspense>
    ),
    children: [
      { path: "/ingresar", element: <IniciarSesion /> },
      { path: "/recuperar", element: <Recuperar /> },
      { path: "/restablecer", element: <Restablecer /> },
    ],
  },
  {
    element: <Protegido />,
    children: [
      {
        element: (
          <Suspense fallback={<Cargando />}>
            <Outlet />
          </Suspense>
        ),
        children: [
          { path: "panel", element: <Inicio /> },
          {
            path: "solicitudes",
            element: r([P.solicitudesVer], <ListaSolicitudes />),
          },
          {
            path: "solicitudes/nueva",
            element: r([P.solicitudesCrear], <FormSolicitud />),
          },
          {
            path: "solicitudes/:id",
            element: r([P.solicitudesVer], <DetalleSolicitud />),
          },
          {
            path: "solicitudes/:id/editar",
            element: r([P.solicitudesEditar], <FormSolicitud />),
          },
          {
            path: "expedientes",
            element: r([P.expedientesVer], <ListaExpedientes />),
          },
          {
            path: "expedientes/nuevo",
            element: r([P.expedientesCrear], <NuevoExpediente />),
          },
          {
            path: "expedientes/:id",
            element: r([P.expedientesVer], <DetalleExpediente />),
          },
          { path: "calendario", element: r([P.calendario], <Calendario />) },
          { path: "clientes", element: r([P.clientesVer], <Clientes />) },
          {
            path: "clientes/:id",
            element: r([P.clientesVer], <FichaCliente />),
          },
          { path: "convenios", element: r([P.conveniosVer], <Convenios />) },
          { path: "inventario", element: r([P.productosVer], <Inventario />) },
          {
            path: "proveedores",
            element: r([P.proveedoresVer], <Proveedores />),
          },
          { path: "vehiculos", element: r([P.vehiculosVer], <Vehiculos />) },
          { path: "ausencias", element: r([P.ausenciasVer], <Ausencias />) },
          { path: "salas", element: r([P.solicitudesVer], <Salas />) },
          { path: "itinerario", element: r([P.itinerario], <Itinerario />) },
          {
            path: "dias-libres",
            element: r([P.diasLibresVer], <DiasLibres />),
          },
          { path: "pagos", element: r([P.pagosVer], <Pagos />) },
          { path: "cobranza", element: r([P.pagosVer], <Cobranza />) },
          { path: "gastos", element: r([P.gastos], <Gastos />) },
          { path: "donaciones", element: r([P.donaciones], <Donaciones />) },
          {
            path: "reportes",
            element: r([P.reportesFinancieros], <Reportes />),
          },
          { path: "usuarios", element: r([P.usuarios], <Usuarios />) },
          {
            path: "configuracion",
            element: r([P.configuracion], <Configuracion />),
          },
          { path: "bitacora", element: r([P.bitacora], <Bitacora />) },
        ],
      },
    ],
  },
  // Cualquier otra dirección va a la portada (fuera del grupo protegido,
  // para que no pase primero por el control de sesión y termine en /ingresar)
  { path: "*", element: <Navigate to="/" replace /> },
]);

export function Rutas() {
  return <RouterProvider router={enrutador} />;
}
