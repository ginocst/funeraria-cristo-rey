import { expect, test, type Page } from '@playwright/test'

// Cuentas y datos de backend/database/sql/002_demo.sql. Requiere el backend corriendo
// y la base recién cargada (ver playwright.config.ts)
const CONTRASENA = 'CristoRey#2026!'

async function ingresar(page: Page, email: string) {
  await page.goto('/ingresar')
  await page.getByLabel('Correo').fill(email)
  await page.getByLabel('Contraseña').fill(CONTRASENA)
  await page.getByRole('button', { name: 'Ingresar' }).click()
  // Espera a que la sesión quede iniciada antes de navegar a otra página
  await expect(page).not.toHaveURL(/ingresar/)
}

test('el administrador ve el panel con los servicios de hoy', async ({ page }) => {
  await ingresar(page, 'renato.osses@cristorey.test')
  await expect(page.getByRole('heading', { name: /Buenas|Buenos/ })).toBeVisible()
  await expect(page.getByText('Servicios de hoy')).toBeVisible()
  await expect(page.getByText('Rosa Elena Catrileo Painemal').first()).toBeVisible()
})

test('aceptar una solicitud descuenta stock y confirma el equipo', async ({ page }) => {
  await ingresar(page, 'renato.osses@cristorey.test')
  await page.goto('/solicitudes/1005')
  await page.getByRole('button', { name: 'Aceptar' }).click()
  await page.getByRole('dialog').getByRole('button', { name: 'Aceptar' }).click()
  await expect(page.getByText('Solicitud aceptada')).toBeVisible()
  await expect(page.getByText('Aceptada').first()).toBeVisible()
})

test('no permite aceptar sin el abono mínimo', async ({ page }) => {
  await ingresar(page, 'renato.osses@cristorey.test')
  await page.goto('/solicitudes/1004')
  await expect(page.getByText(/Falta abono/)).toBeVisible()
  await expect(page.getByRole('button', { name: 'Aceptar', exact: true })).toBeDisabled()
})

test('registrar un pago emite comprobante y lo muestra en PDF', async ({ page }) => {
  await ingresar(page, 'carla.fuentes@cristorey.test')
  await page.goto('/expedientes/1004?pestana=pagos')
  await page.getByRole('button', { name: 'Registrar pago' }).click()
  const dialogo = page.getByRole('dialog')
  await dialogo.getByLabel('Monto').fill('600000')
  await dialogo.getByLabel('Medio de pago').click()
  await page.getByRole('option', { name: 'Efectivo' }).click()
  await dialogo.getByRole('button', { name: 'Registrar pago' }).click()
  await expect(page.getByText(/Pago registrado/)).toBeVisible()
  await page.getByRole('button', { name: /^C-\d+/ }).first().click()
  await expect(page.getByRole('button', { name: 'Descargar PDF' })).toBeEnabled()
})

test('el coordinador no ve finanzas globales ni puede evaluar', async ({ page }) => {
  await ingresar(page, 'carla.fuentes@cristorey.test')
  await expect(page.getByText('Ingresos del mes')).toHaveCount(0)
  await page.goto('/usuarios')
  await expect(page.getByText('Sin acceso')).toBeVisible()
})

test('el operario solo ve su itinerario @movil', async ({ page }) => {
  await ingresar(page, 'pedro.soto@cristorey.test')
  await expect(page).toHaveURL(/itinerario/)
  await expect(page.getByRole('heading', { name: 'Mi itinerario' })).toBeVisible()
  await expect(page.getByText(/Presentarse a las/).first()).toBeVisible()
})

test('el catálogo público muestra precios y filtra por categoría', async ({ page }) => {
  await page.goto('/catalogo')
  await expect(page.getByRole('heading', { name: 'Catálogo de urnas y ataúdes' })).toBeVisible()
  await page.getByRole('tab', { name: 'Urnas de cremación' }).click()
  await expect(page.getByText('Cremación Mármol')).toBeVisible()
  await expect(page.getByText('Imperial Roble')).toHaveCount(0)
  await expect(page.getByText(/stock/i)).toHaveCount(0)
})

test('la portada pública muestra el contacto y lleva al catálogo y al acceso del personal @movil', async ({ page }) => {
  await page.goto('/')
  await expect(page.getByRole('heading', { level: 1, name: /Servicios funerarios en Temuco/ })).toBeVisible()
  await expect(page.getByRole('link', { name: 'Llamar ahora' })).toHaveAttribute('href', 'tel:+56967294759')
  await expect(page.getByText('Calle Blanco #142').first()).toBeVisible()
  await expect(page.getByText('Manuel Montt #1991').first()).toBeVisible()
  await expect(page.getByRole('heading', { name: 'Lo que coordinamos' })).toBeVisible()
  await page.getByRole('link', { name: 'Ver catálogo y precios' }).click()
  await expect(page).toHaveURL(/\/catalogo$/)
  await page.getByRole('link', { name: 'Acceso del personal' }).first().click()
  await expect(page).toHaveURL(/\/ingresar$/)
})
