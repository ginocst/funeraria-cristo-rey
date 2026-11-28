import type { SalaVelatoria } from "../../api/tipos";

export const ESTADOS_SALA = {
  disponible: { etiqueta: "Disponible", color: "success", nota: "" },
  mantenimiento: {
    etiqueta: "En mantenimiento",
    color: "warning",
    nota: "en mantenimiento",
  },
  fuera_de_servicio: {
    etiqueta: "Fuera de servicio",
    color: "error",
    nota: "fuera de servicio",
  },
} as const;

const normalizar = (texto: string) =>
  texto.trim().replace(/\s+/g, " ").toLowerCase();

/** La sala propia escrita como lugar (por nombre, dirección o ambos), igual que en la API */
export const buscarSala = (salas: SalaVelatoria[] | undefined, lugar: string) =>
  salas?.find((s) =>
    [s.nombre, s.direccion, s.lugar]
      .map(normalizar)
      .includes(normalizar(lugar)),
  );
