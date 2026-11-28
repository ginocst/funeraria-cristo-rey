/**
 * Enlaces de Google Maps con la ruta ya cargada. En el teléfono abren la aplicación;
 * sin punto de partida, se parte desde la ubicación actual.
 * No se incrusta un mapa para no cargar terceros en la página.
 */
export function enlaceRuta(destino: string, origen?: string): string {
  const parametros = new URLSearchParams({
    api: "1",
    ...(origen ? { origin: conPais(origen) } : {}),
    destination: conPais(destino),
    travelmode: "driving",
  });
  return `https://www.google.com/maps/dir/?${parametros}`;
}

/** Los lugares se escriben como texto libre: el país evita resultados de otra parte */
const conPais = (lugar: string) =>
  /\bchile\b/i.test(lugar) ? lugar : `${lugar}, Chile`;
