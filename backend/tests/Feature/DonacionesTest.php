<?php

use Database\Seeders\DonacionesDemoSeeder;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\IOFactory;

beforeEach(function () {
    $this->admin = $this->usuario('Administrador');
    $this->actingAs($this->admin);
    $this->categoria = $this->postJson('/api/categorias-organizacion', ['nombre' => 'Organización deportiva'])->assertCreated()->json('id');
    $this->organizacion = $this->postJson('/api/organizaciones', ['categoria_id' => $this->categoria, 'nombre' => 'Club de prueba', 'activo' => true])->assertCreated()->json('id');
});

function donar($prueba, int $organizacion, int $monto, string $fecha)
{
    return $prueba->postJson('/api/donaciones', ['organizacion_id' => $organizacion, 'monto' => $monto, 'fecha' => $fecha, 'descripcion' => 'Aporte']);
}

it('registra el historial de cada organización y totaliza el mes', function () {
    $otra = $this->postJson('/api/organizaciones', ['categoria_id' => $this->categoria, 'nombre' => 'Otro club'])->json('id');
    donar($this, $this->organizacion, 100000, '2026-10-05')->assertCreated();
    donar($this, $this->organizacion, 50000, '2026-10-20')->assertCreated();
    donar($this, $otra, 30000, '2026-10-31')->assertCreated();
    donar($this, $otra, 999000, '2026-09-30')->assertCreated();

    $this->getJson("/api/donaciones?organizacion_id={$this->organizacion}")->assertOk()->assertJsonPath('total', 2);
    $this->getJson('/api/donaciones/mensual?mes=2026-10')->assertOk()
        ->assertJsonPath('totales', ['cantidad' => 3, 'monto' => 180000])
        ->assertJsonPath('por_organizacion.0', ['nombre' => 'Club de prueba', 'cantidad' => 2, 'monto' => 150000])
        ->assertJsonPath('por_categoria.0.monto', 180000);
    expect((int) $this->getJson('/api/organizaciones?q=prueba')->json('data.0.total_donado'))->toBe(150000);
});

it('rechaza fechas futuras, montos no positivos y organizaciones inactivas', function () {
    donar($this, $this->organizacion, 1000, now('America/Santiago')->addDay()->toDateString())->assertJsonValidationErrorFor('fecha');
    donar($this, $this->organizacion, 0, '2026-10-01')->assertJsonValidationErrorFor('monto');
    $id = donar($this, $this->organizacion, 1000, '2026-10-01')->json('id');
    $this->putJson("/api/organizaciones/{$this->organizacion}", ['categoria_id' => $this->categoria, 'nombre' => 'Club de prueba', 'activo' => false])->assertOk();

    donar($this, $this->organizacion, 1000, '2026-10-02')->assertJsonValidationErrorFor('organizacion_id');
    // La donación ya registrada se puede corregir
    $this->putJson("/api/donaciones/$id", ['organizacion_id' => $this->organizacion, 'monto' => 2000, 'fecha' => '2026-10-01'])->assertOk();
});

it('no elimina categorías ni organizaciones en uso, ni repite nombres', function () {
    $donacion = donar($this, $this->organizacion, 1000, '2026-10-01')->json('id');

    $this->deleteJson("/api/categorias-organizacion/{$this->categoria}")->assertStatus(409);
    $this->deleteJson("/api/organizaciones/{$this->organizacion}")->assertStatus(409);
    $this->postJson('/api/categorias-organizacion', ['nombre' => 'Organización deportiva'])->assertJsonValidationErrorFor('nombre');

    $this->deleteJson("/api/donaciones/$donacion")->assertNoContent();
    $this->deleteJson("/api/organizaciones/{$this->organizacion}")->assertNoContent();
    $this->deleteJson("/api/categorias-organizacion/{$this->categoria}")->assertNoContent();
});

it('exporta el mes en PDF y Excel, y el PDF de una organización', function () {
    donar($this, $this->organizacion, 100000, '2026-10-05');

    foreach (['/api/donaciones/mensual.pdf?mes=2026-10', "/api/organizaciones/{$this->organizacion}/donaciones.pdf?mes=2026-10", '/api/donaciones/mensual.pdf?mes=2026-01'] as $ruta) {
        expect($this->get($ruta)->assertOk()->assertHeader('content-type', 'application/pdf')->getContent())->toStartWith('%PDF');
    }
    $excel = $this->get('/api/donaciones/mensual.xlsx?mes=2026-10')->assertOk();
    $libro = IOFactory::load($excel->baseResponse->getFile()->getPathname());
    expect($libro->getSheetNames())->toBe(['Donaciones'])
        ->and(array_column($libro->getActiveSheet()->toArray(), 0))->toContain('Por organización', 'Por categoría', 'Detalle');
});

it('solo permite gestionar donaciones al administrador', function () {
    foreach (['Coordinador', 'Operario'] as $rol) {
        $this->actingAs($this->usuario($rol));
        $this->getJson('/api/donaciones')->assertForbidden();
        $this->getJson('/api/organizaciones')->assertForbidden();
        $this->getJson('/api/donaciones/mensual.pdf')->assertForbidden();
    }
});

it('carga la demostración una sola vez', function () {
    DB::table('donaciones')->delete();
    DB::table('organizaciones')->delete();
    DB::table('categorias_organizacion')->delete();

    $this->seed(DonacionesDemoSeeder::class);
    $this->seed(DonacionesDemoSeeder::class);

    expect(DB::table('organizaciones')->pluck('nombre')->all())->toContain('Deportes Temuco', 'Hogar de Cristo')
        ->and(DB::table('organizaciones')->count())->toBe(3)
        ->and(DB::table('categorias_organizacion')->count())->toBe(2);
});
