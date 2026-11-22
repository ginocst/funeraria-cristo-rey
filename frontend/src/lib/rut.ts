/**
 * RUT: se valida el formato (cuerpo de 7 u 8 dígitos y dígito verificador 0-9 o K)
 */
export function normalizarRut(rut: string): string {
  const limpio = rut.replace(/[^0-9kK]/g, "").toUpperCase();
  return limpio.length < 2
    ? limpio
    : `${limpio.slice(0, -1)}-${limpio.slice(-1)}`;
}

export function rutValido(rut: string): boolean {
  if (!/^(\d{1,2}(\.\d{3}){2}|\d{7,8})-?[0-9kK]$/.test(rut.trim()))
    return false;
  return /^\d{7,8}-[0-9K]$/.test(normalizarRut(rut));
}

/** 12345678K a 12.345.678-K */
export function formatearRut(rut: string | null | undefined): string {
  if (!rut) return "—";
  const n = normalizarRut(rut);
  const [cuerpo, dv] = n.split("-");
  if (!dv) return rut;
  return `${Number(cuerpo).toLocaleString("es-CL")}-${dv}`;
}

export const MENSAJE_RUT = "Use el formato 12.345.678-9";
