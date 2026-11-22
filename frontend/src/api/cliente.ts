import axios, { AxiosError } from "axios";

/**
 * Cliente HTTP de la API. La sesión viaja en una cookie HttpOnly (Sanctum SPA);
 * axios envía automáticamente el encabezado X-XSRF-TOKEN leído de
 * la cookie XSRF-TOKEN para protegerse de CSRF
 */
export const BASE_API = `${import.meta.env.VITE_API_URL ?? ""}`.replace(
  /\/$/,
  "",
);

export const api = axios.create({
  baseURL: `${BASE_API}/api`,
  withCredentials: true,
  withXSRFToken: true,
  headers: { Accept: "application/json", "X-Requested-With": "XMLHttpRequest" },
  timeout: 30000,
});

export async function obtenerCsrf(): Promise<void> {
  await api.get("/sanctum/csrf-cookie");
}

export type ErrorApi = {
  mensaje: string;
  codigo?: string;
  estado?: number;
  errores?: Record<string, string[]>;
};

/** Traduce cualquier error de la API a un mensaje claro para mostrar al usuario */
export function leerError(e: unknown): ErrorApi {
  if (e instanceof AxiosError) {
    const datos = e.response?.data as
      | { message?: string; codigo?: string; errors?: Record<string, string[]> }
      | undefined;
    const estado = e.response?.status;
    if (!e.response)
      return {
        mensaje:
          "No hay conexión con el servidor. Revise su conexión a internet e intente de nuevo.",
      };
    if (estado === 419)
      return {
        mensaje: "La página estuvo inactiva mucho tiempo. Vuelva a intentarlo.",
        estado,
      };
    if (estado === 429)
      return {
        mensaje:
          "Demasiados intentos. Espere un momento antes de volver a intentar.",
        estado,
      };
    if (estado === 403 && !datos?.codigo)
      return {
        mensaje: "Su perfil no tiene permiso para realizar esta acción.",
        estado,
      };
    const primero = datos?.errors
      ? Object.values(datos.errors)[0]?.[0]
      : undefined;
    return {
      mensaje:
        primero ??
        datos?.message ??
        "Ocurrió un error inesperado. Intente de nuevo.",
      codigo: datos?.codigo,
      estado,
      errores: datos?.errors,
    };
  }
  return {
    mensaje: e instanceof Error ? e.message : "Ocurrió un error inesperado.",
  };
}

/** Clave única para operaciones idempotentes (aceptar solicitud, registrar pago) */
export const nuevaClave = () =>
  globalThis.crypto?.randomUUID?.() ??
  `${Date.now()}-${Math.random().toString(16).slice(2)}`;

export type Pagina<T> = {
  data: T[];
  current_page: number;
  last_page: number;
  per_page: number;
  total: number;
};

/** Descarga un archivo protegido (PDF o Excel) como Blob usando la sesión */
export async function descargarBlob(ruta: string): Promise<Blob> {
  const r = await api.get(ruta, { responseType: "blob" });
  return r.data as Blob;
}

export function guardarBlob(blob: Blob, nombre: string) {
  const url = URL.createObjectURL(blob);
  const a = document.createElement("a");
  a.href = url;
  a.download = nombre;
  document.body.appendChild(a);
  a.click();
  a.remove();
  setTimeout(() => URL.revokeObjectURL(url), 2000);
}
