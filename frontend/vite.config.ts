import react from "@vitejs/plugin-react";
import { defineConfig, loadEnv } from "vite";

// En desarrollo, /api se redirige a la API de Laravel: la SPA y la API quedan
// en el mismo origen y la cookie de sesión de Sanctum funciona sin CORS
export default defineConfig(({ mode }) => {
  const env = loadEnv(mode, process.cwd(), "");
  const destino = env.VITE_PROXY_TARGET || "http://localhost:8000";

  return {
    plugins: [react()],
    server: {
      port: 5173,
      proxy: {
        "/api": { target: destino, changeOrigin: false },
        "/storage": { target: destino, changeOrigin: false },
      },
    },
    build: { chunkSizeWarningLimit: 1200 },
  };
});
