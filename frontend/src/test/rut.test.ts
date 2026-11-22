import { formatearRut, normalizarRut, rutValido } from "../lib/rut";

describe("RUT (solo formato, sin módulo 11)", () => {
  it("acepta RUT con o sin puntos y con K", () => {
    expect(rutValido("12.345.678-9")).toBe(true);
    expect(rutValido("12345678-k")).toBe(true);
    expect(rutValido("9.876.543-2")).toBe(true);
  });

  it("no calcula el dígito verificador", () => {
    // 12.345.678-0 tiene un DV «incorrecto» según módulo 11, pero el formato es válido
    // No se usa módulo 11 por el momento para facilitar probar el sistema
    expect(rutValido("12.345.678-0")).toBe(true);
  });

  it("rechaza formatos inválidos", () => {
    expect(rutValido("1234-5")).toBe(false);
    expect(rutValido("12.345.678-X")).toBe(false);
    expect(rutValido("")).toBe(false);
  });

  it("normaliza y formatea", () => {
    expect(normalizarRut("12.345.678-k")).toBe("12345678-K");
    expect(formatearRut("12345678K")).toBe("12.345.678-K");
  });
});
