/**
 * Datos de contacto de la funeraria que muestran las páginas públicas.
 * Están en un solo lugar para que un cambio de teléfono o dirección se haga una vez.
 */
export const CONTACTO = {
  direccion: "Blanco Encalada 142",
  ciudad: "Temuco, Araucanía",
  codigoPostal: "4781096",
  telefono: "9 6729 4759",
  /** Enlace para llamar desde el teléfono */
  telefonoEnlace: "tel:+56967294759",
  /** Abre Google Maps con la dirección; no se incrusta un mapa para no cargar terceros en la página. */
  mapa: `https://www.google.com/maps/search/?api=1&query=${encodeURIComponent("Funeraria Cristo Rey, Blanco Encalada 142, Temuco")}`,
  cobertura: "Temuco y alrededores",
};
