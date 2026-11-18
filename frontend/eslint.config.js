import js from "@eslint/js";
import globals from "globals";
import reactHooks from "eslint-plugin-react-hooks";
import reactRefresh from "eslint-plugin-react-refresh";
import tseslint from "typescript-eslint";
import { defineConfig, globalIgnores } from "eslint/config";

export default defineConfig([
  globalIgnores(["dist", "test-results", "playwright-report"]),
  {
    files: ["**/*.{ts,tsx}"],
    extends: [
      js.configs.recommended,
      tseslint.configs.recommended,
      reactHooks.configs.flat.recommended,
      reactRefresh.configs.vite,
    ],
    languageOptions: {
      globals: globals.browser,
    },
    rules: {
      // Control de seguridad: la interfaz escapa los datos por defecto,
      // y insertar HTML sin escapar queda prohibido. Si un caso lo
      // exige, se justifica con un comentario eslint-disable en esa línea.
      "no-restricted-syntax": [
        "error",
        {
          selector: "JSXAttribute[name.name='dangerouslySetInnerHTML']",
          message:
            "Prohibido dangerouslySetInnerHTML sin sanitizar. Si el HTML es de confianza, justifícalo con un comentario eslint-disable.",
        },
      ],
      // Estas reglas aconsejan cómo organizar el código, no detectan fallos:
      // avisan, pero no impiden que pase la integración continua.
      "react-refresh/only-export-components": [
        "warn",
        { allowConstantExport: true },
      ],
      "react-hooks/set-state-in-effect": "warn",
      "react-hooks/incompatible-library": "warn",
    },
  },
]);
