import { defineConfig, devices } from "@playwright/test";

/**
 * Pruebas de extremo a extremo sobre el sistema: navegador → Vite →
 * API de Laravel → PostgreSQL con los datos demo
 *
 * Antes de ejecutar:
 *   1. Backend en marcha (con MFA_OBLIGATORIO=false)
 *   2. Base de datos recién cargada: php artisan migrate:fresh --seed
 *      (algunas pruebas modifican datos; sin reiniciar la base, fallan al repetirse)
 * Uso: npx playwright test
 */
export default defineConfig({
  testDir: "./e2e",
  timeout: 45_000,
  fullyParallel: false,
  workers: 1, // las pruebas comparten una sola base de datos
  reporter: [["list"]],
  use: {
    baseURL: "http://localhost:5173",
    locale: "es-CL",
    timezoneId: "America/Santiago",
    trace: "retain-on-failure",
  },
  projects: [
    { name: "escritorio", use: { ...devices["Desktop Chrome"] } },
    { name: "telefono", use: { ...devices["Pixel 7"] }, grep: /@movil/ },
  ],
  webServer: {
    command: "npx vite --port 5173 --strictPort",
    url: "http://localhost:5173",
    reuseExistingServer: true,
  },
});
