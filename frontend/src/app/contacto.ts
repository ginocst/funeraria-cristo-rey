/**
 * Datos de contacto de la funeraria que muestran las páginas públicas.
 * Están en un solo lugar para que un cambio de teléfono o dirección se haga una vez.
 */
export type SucursalPublica = {
  nombre: string;
  direccion: string;
  /** Población o referencia de ubicación, si corresponde */
  sector?: string;
  ciudad: string;
  telefono: string;
  /** Enlace para llamar desde el teléfono (formato internacional, sin espacios). */
  telefonoEnlace: string;
  /** Abre Google Maps con la dirección; no se incrusta un mapa para no cargar terceros en la página. */
  mapa: string;
};

const enMapa = (lugar: string) =>
  `https://www.google.com/maps/search/?api=1&query=${encodeURIComponent(`Funeraria Cristo Rey, ${lugar}`)}`;

const SUCURSAL_BLANCO: SucursalPublica = {
  nombre: "Sucursal Blanco",
  direccion: "Calle Blanco #142",
  sector: "Entre Lautaro y Miraflores",
  ciudad: "Temuco, Araucanía",
  telefono: "9 6729 4759",
  telefonoEnlace: "tel:+56967294759",
  mapa: enMapa("Calle Blanco 142, Temuco"),
};

const CASA_MATRIZ: SucursalPublica = {
  nombre: "Casa Matriz",
  direccion: "Manuel Montt #1991",
  sector: "Pobl. Santa Elena",
  ciudad: "Temuco, Araucanía",
  telefono: "9 8601 7135",
  telefonoEnlace: "tel:+56986017135",
  mapa: enMapa("Manuel Montt 1991, Población Santa Elena, Temuco"),
};

export const SUCURSALES: SucursalPublica[] = [SUCURSAL_BLANCO, CASA_MATRIZ];

/** La Sucursal Blanco es la principal: su teléfono va en la cabecera y en el arco. `sucursales` trae ambas. */
export const CONTACTO = {
  ...SUCURSAL_BLANCO,
  sucursales: SUCURSALES,
  cobertura: "Temuco y alrededores",
};
