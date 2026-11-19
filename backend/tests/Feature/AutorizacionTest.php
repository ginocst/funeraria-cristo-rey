<?php

use Illuminate\Support\Facades\Route;

it('exige un permiso explícito en toda ruta autenticada (denegación por defecto)', function () {
    $libres = ['api/me', 'api/avisos', 'api/avisos/leer-todos', 'api/avisos/{id}/leer', 'api/avisos/stream'];
    $sinPermiso = collect(Route::getRoutes()->getRoutes())
        ->filter(fn($r) => str_starts_with($r->uri(), 'api/') && in_array('auth:sanctum', $r->gatherMiddleware(), true))
        ->reject(fn($r) => in_array($r->uri(), $libres, true))
        ->reject(fn($r) => collect($r->gatherMiddleware())->contains(fn($m) => is_string($m) && str_starts_with($m, 'can:')))
        ->map(fn($r) => implode('|', $r->methods()) . ' ' . $r->uri())->values()->all();

    expect($sinPermiso)->toBe([]);
});

it('responde 401 a quien no ha iniciado sesión', function () {
    $this->getJson('/api/expedientes')->assertUnauthorized();
});

it('impide a un operario ver solicitudes o pagos (CP-07)', function () {
    $operario = $this->usuario('Operario');
    $this->actingAs($operario)->getJson('/api/solicitudes')->assertForbidden();
    $this->actingAs($operario)->getJson('/api/pagos')->assertForbidden();
    $this->actingAs($operario)->getJson('/api/itinerario')->assertOk();
});

it('impide al coordinador evaluar solicitudes y gestionar usuarios', function () {
    $coordinador = $this->usuario('Coordinador');
    $this->actingAs($coordinador)->postJson('/api/solicitudes/1/aceptar', [], ['Idempotency-Key' => 'x'])->assertForbidden();
    $this->actingAs($coordinador)->getJson('/api/usuarios')->assertForbidden();
});

it('exige configurar el MFA a administradores antes de operar', function () {
    $admin = $this->usuario('Administrador');
    $admin->forceFill(['two_factor_secret' => null, 'two_factor_confirmed_at' => null])->save();

    $this->actingAs($admin)->getJson('/api/dashboard')->assertForbidden()->assertJsonPath('codigo', 'mfa_requerido');
    $this->actingAs($admin)->getJson('/api/me')->assertOk()->assertJsonPath('mfa_requerido', true);
});

it('aplica la suplencia temporal del coordinador sin permitir gestionar usuarios', function () {
    $admin = $this->usuario('Administrador');
    $coordinador = $this->usuario('Coordinador');

    $this->actingAs($admin)->postJson("/api/usuarios/{$coordinador->id}/suplencia", ['hasta' => now()->addDays(3)->toDateString()])->assertOk();

    expect($coordinador->fresh()->tienePermiso('solicitudes.evaluar'))->toBeTrue()
        ->and($coordinador->fresh()->tienePermiso('usuarios.gestionar'))->toBeFalse();
});
