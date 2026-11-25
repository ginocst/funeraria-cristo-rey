// Prueba de carga con k6: 20 usuarios simultáneos durante 2 minutos.
// Mide el catálogo público y el panel autenticado. Meta: p95 < 2 s, errores < 1 %.
// Uso: k6 run -e BASE=https://staging.ejemplo.cl -e EMAIL=... -e PASSWORD=... pruebas-carga/panel-y-catalogo.js
import http from 'k6/http'
import { check, sleep } from 'k6'

const BASE = __ENV.BASE || 'http://localhost:8000'

export const options = {
  scenarios: {
    publico: { executor: 'constant-vus', vus: 15, duration: '2m', exec: 'catalogo' },
    personal: { executor: 'constant-vus', vus: 5, duration: '2m', exec: 'panel' },
  },
  thresholds: {
    http_req_duration: ['p(95)<2000'],
    http_req_failed: ['rate<0.01'],
  },
}

export function catalogo() {
  const r = http.get(`${BASE}/api/publico/catalogo?orden=precio_asc`)
  check(r, { 'catálogo 200': (x) => x.status === 200 })
  sleep(1)
}

export function setup() {
  const jar = http.cookieJar()
  http.get(`${BASE}/api/sanctum/csrf-cookie`)
  const xsrf = decodeURIComponent(jar.cookiesForURL(BASE)['XSRF-TOKEN'][0])
  const r = http.post(`${BASE}/api/auth/login`, JSON.stringify({ email: __ENV.EMAIL, password: __ENV.PASSWORD }), {
    headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-XSRF-TOKEN': xsrf, Referer: BASE },
  })
  check(r, { 'login correcto': (x) => x.status === 200 || x.status === 204 })
  return { cookies: jar.cookiesForURL(BASE) }
}

export function panel(datos) {
  const jar = http.cookieJar()
  Object.entries(datos.cookies).forEach(([k, v]) => jar.set(BASE, k, v[0]))
  const r = http.get(`${BASE}/api/dashboard`, { headers: { Accept: 'application/json', Referer: BASE } })
  check(r, { 'panel 200': (x) => x.status === 200 })
  sleep(2)
}
