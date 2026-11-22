import { useQuery, useQueryClient } from "@tanstack/react-query";
import {
  createContext,
  useCallback,
  useContext,
  useMemo,
  type ReactNode,
} from "react";
import { api, obtenerCsrf } from "../api/cliente";
import type { Sesion } from "../api/tipos";

type ResultadoLogin = { requiere2fa: boolean };

type ContextoSesion = {
  sesion: Sesion | null;
  cargando: boolean;
  puede: (...permisos: string[]) => boolean;
  iniciar: (
    email: string,
    password: string,
    recordar?: boolean,
  ) => Promise<ResultadoLogin>;
  verificar2fa: (codigo: string, esRecuperacion?: boolean) => Promise<void>;
  cerrar: () => Promise<void>;
  recargar: () => Promise<unknown>;
};

const Contexto = createContext<ContextoSesion | null>(null);

/** Mantiene el usuario autenticado (GET /api/me) y expone login, 2FA y cierre */
export function ProveedorSesion({ children }: { children: ReactNode }) {
  const qc = useQueryClient();
  const consulta = useQuery({
    queryKey: ["me"],
    queryFn: async () => {
      try {
        return (await api.get<Sesion>("/me")).data;
      } catch (e) {
        const estado = (e as { response?: { status?: number } }).response
          ?.status;
        if (estado === 401 || estado === 419) return null;
        throw e;
      }
    },
    staleTime: 5 * 60_000,
    retry: false,
  });

  const sesion = consulta.data ?? null;

  const puede = useCallback(
    (...permisos: string[]) =>
      !!sesion && permisos.some((p) => sesion.permisos.includes(p)),
    [sesion],
  );

  const iniciar = useCallback(
    async (
      email: string,
      password: string,
      recordar = false,
    ): Promise<ResultadoLogin> => {
      await obtenerCsrf();
      const r = await api.post("/auth/login", {
        email,
        password,
        remember: recordar,
      });
      if (r.data?.two_factor) return { requiere2fa: true };
      await qc.invalidateQueries({ queryKey: ["me"] });
      return { requiere2fa: false };
    },
    [qc],
  );

  const verificar2fa = useCallback(
    async (codigo: string, esRecuperacion = false) => {
      await api.post(
        "/auth/two-factor-challenge",
        esRecuperacion ? { recovery_code: codigo } : { code: codigo },
      );
      await qc.invalidateQueries({ queryKey: ["me"] });
    },
    [qc],
  );

  const cerrar = useCallback(async () => {
    try {
      await api.post("/auth/logout");
    } finally {
      qc.setQueryData(["me"], null);
      qc.removeQueries({ predicate: (q) => q.queryKey[0] !== "me" });
    }
  }, [qc]);

  const valor = useMemo(
    () => ({
      sesion,
      cargando: consulta.isLoading,
      puede,
      iniciar,
      verificar2fa,
      cerrar,
      recargar: consulta.refetch,
    }),
    [
      sesion,
      consulta.isLoading,
      puede,
      iniciar,
      verificar2fa,
      cerrar,
      consulta.refetch,
    ],
  );

  return <Contexto.Provider value={valor}>{children}</Contexto.Provider>;
}

export function useSesion() {
  const c = useContext(Contexto);
  if (!c) throw new Error("useSesion debe usarse dentro de ProveedorSesion");
  return c;
}
