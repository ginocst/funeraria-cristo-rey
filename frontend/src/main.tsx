import { CssBaseline, ThemeProvider } from '@mui/material'
import { MutationCache, QueryCache, QueryClient, QueryClientProvider } from '@tanstack/react-query'
import { StrictMode } from 'react'
import { createRoot } from 'react-dom/client'
import { Rutas } from './app/Rutas'
import { tema } from './app/tema'
import { ProveedorSesion } from './auth/Sesion'
import { ProveedorNotificaciones } from './components/Notificador'

/** Si la API responde 401 (sesión vencida) se vuelve a pedir /me, lo que lleva al inicio de sesión. */
function alError(e: unknown) {
  const estado = (e as { response?: { status?: number } }).response?.status
  if (estado === 401) clienteConsultas.setQueryData(['me'], null)
  if (estado === 403 && (e as { response?: { data?: { codigo?: string } } }).response?.data?.codigo === 'mfa_requerido') {
    clienteConsultas.invalidateQueries({ queryKey: ['me'] })
  }
}

const clienteConsultas = new QueryClient({
  queryCache: new QueryCache({ onError: alError }),
  mutationCache: new MutationCache({ onError: alError }),
  defaultOptions: {
    queries: {
      staleTime: 30_000,
      refetchOnWindowFocus: true,
      retry: (intentos, e) => {
        const estado = (e as { response?: { status?: number } }).response?.status
        return !!estado && estado >= 500 && intentos < 2
      },
    },
  },
})

createRoot(document.getElementById('root')!).render(
  <StrictMode>
    <ThemeProvider theme={tema}>
      <CssBaseline />
      <QueryClientProvider client={clienteConsultas}>
        <ProveedorNotificaciones>
          <ProveedorSesion>
            <Rutas />
          </ProveedorSesion>
        </ProveedorNotificaciones>
      </QueryClientProvider>
    </ThemeProvider>
  </StrictMode>,
)