import { enlaceRuta } from "../lib/mapas";

const parametros = (url: string) => new URL(url).searchParams;

describe("rutas en Google Maps", () => {
  it("lleva al destino desde la ubicación actual", () => {
    const p = parametros(enlaceRuta("Cementerio General de Temuco"));
    expect(p.get("destination")).toBe("Cementerio General de Temuco, Chile");
    expect(p.has("origin")).toBe(false);
    expect(p.get("travelmode")).toBe("driving");
  });

  it("traza el traslado entre dos lugares y no repite el país", () => {
    const p = parametros(
      enlaceRuta("Parque #2 & Sur, Temuco, Chile", "Sala Velatoria, Temuco"),
    );
    expect(p.get("origin")).toBe("Sala Velatoria, Temuco, Chile");
    expect(p.get("destination")).toBe("Parque #2 & Sur, Temuco, Chile");
  });
});
