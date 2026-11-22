import dayjs, { type Dayjs } from "dayjs";
import "dayjs/locale/es";
import customParseFormat from "dayjs/plugin/customParseFormat";
import relativeTime from "dayjs/plugin/relativeTime";
import timezone from "dayjs/plugin/timezone";
import utc from "dayjs/plugin/utc";

dayjs.extend(utc);
dayjs.extend(timezone);
dayjs.extend(relativeTime);
dayjs.extend(customParseFormat);
dayjs.locale("es");

/** Todas las fechas se guardan en UTC y se muestran en la hora local */
export const ZONA = "America/Santiago";

const SIN_ZONA = /^\d{4}-\d{2}-\d{2}[ T]\d{2}:\d{2}(:\d{2}(\.\d+)?)?$/;

/**
 * Convierte lo que entrega la API a un instante en hora Chile
 * Las consultas SQL devuelven "2026-11-09 21:45:00" (UTC sin zona) y los
 * modelos devuelven ISO con "Z": ambos se interpretan como UTC.
 */
export function aLocal(valor: string | Date | null | undefined): Dayjs | null {
  if (!valor) return null;
  if (valor instanceof Date) return dayjs(valor).tz(ZONA);
  const texto = SIN_ZONA.test(valor) ? valor.replace(" ", "T") + "Z" : valor;
  const d = dayjs.utc(texto);
  return d.isValid() ? d.tz(ZONA) : null;
}

export const ahoraLocal = () => dayjs().tz(ZONA);

/** 09-11-2026 15:30 */
export function fechaHora(valor: string | null | undefined): string {
  return aLocal(valor)?.format("DD-MM-YYYY HH:mm") ?? "—";
}

/** 09-11-2026 (para columnas date, sin conversión de zona) */
export function fecha(valor: string | null | undefined): string {
  if (!valor) return "—";
  if (/^\d{4}-\d{2}-\d{2}$/.test(valor))
    return dayjs(valor).format("DD-MM-YYYY");
  return aLocal(valor)?.format("DD-MM-YYYY") ?? "—";
}

export function hora(valor: string | null | undefined): string {
  return aLocal(valor)?.format("HH:mm") ?? "—";
}

/** «lunes 9 de noviembre, 15:30» */
export function fechaLarga(
  valor: string | null | undefined,
  conHora = true,
): string {
  const d = aLocal(valor);
  if (!d) return "—";
  return d.format(
    conHora ? "dddd D [de] MMMM, HH:mm" : "dddd D [de] MMMM [de] YYYY",
  );
}

/** «hace 3 horas», «en 2 días» */
export function relativo(valor: string | null | undefined): string {
  const d = aLocal(valor);
  return d ? d.from(ahoraLocal()) : "—";
}

/** Valor para <input type="datetime-local"> en hora local. */
export function aInputLocal(valor: string | null | undefined): string {
  return aLocal(valor)?.format("YYYY-MM-DDTHH:mm") ?? "";
}

/** Convierte el valor de un <input type="datetime-local"> (hora Chile) a ISO UTC */
export function desdeInputLocal(valor: string): string {
  return dayjs.tz(valor, ZONA).utc().format("YYYY-MM-DDTHH:mm:ss[Z]");
}

const clp = new Intl.NumberFormat("es-CL", {
  style: "currency",
  currency: "CLP",
  maximumFractionDigits: 0,
});
const entero = new Intl.NumberFormat("es-CL", { maximumFractionDigits: 0 });

/** $1.250.000 */
export const pesos = (n: number | string | null | undefined) =>
  clp.format(Number(n ?? 0));
export const numero = (n: number | string | null | undefined) =>
  entero.format(Number(n ?? 0));

/** $1,3 M para ejes de gráficos. */
export function pesosCortos(n: number): string {
  if (Math.abs(n) >= 1_000_000)
    return `$${(n / 1_000_000).toLocaleString("es-CL", { maximumFractionDigits: 1 })} M`;
  if (Math.abs(n) >= 1_000) return `$${Math.round(n / 1_000)} mil`;
  return pesos(n);
}

/** «nov 26» (mes abreviado y año de dos cifras) a partir de «2026-11» */
export function mesCorto(ym: string): string {
  return dayjs(`${ym}-01`).format("MMM YY").replace(".", "");
}

export function mesLargo(ym: string): string {
  const t = dayjs(`${ym}-01`).format("MMMM [de] YYYY");
  return t.charAt(0).toUpperCase() + t.slice(1);
}

export function capitalizar(t: string): string {
  return t ? t.charAt(0).toUpperCase() + t.slice(1) : t;
}
