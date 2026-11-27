<?php

use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Tests\Feature\Escenario;

it('genera el reporte mensual en PDF', function () {
    $admin = $this->usuario('Administrador');

    $respuesta = $this->actingAs($admin)->get('/api/reportes/mensual.pdf?mes=2026-11');

    $respuesta->assertOk()->assertHeader('content-type', 'application/pdf');
    expect($respuesta->getContent())->toStartWith('%PDF');
});

it('genera el Excel mensual con sus hojas, totales y textos que no se ejecutan como fórmula', function () {
    $admin = $this->usuario('Administrador');
    $base = Escenario::base();
    DB::table('gastos')->insert([
        'sucursal_id' => $base['sucursal'],
        'categoria' => 'Combustible',
        'descripcion' => '=1+1',
        'monto' => 45000,
        'fecha' => '2026-11-10',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $respuesta = $this->actingAs($admin)->get('/api/reportes/mensual.xlsx?mes=2026-11');
    $respuesta->assertOk();
    $libro = IOFactory::load($respuesta->baseResponse->getFile()->getPathname());

    expect($libro->getSheetNames())->toBe(['Resumen', 'Servicios', 'Pagos', 'Gastos', 'Urnas']);
    $gastos = $libro->getSheetByName('Gastos');
    expect($gastos->getCell('C5')->getValue())->toBe('=1+1')
        ->and($gastos->getCell('C5')->getDataType())->toBe('s')
        ->and($gastos->getCell('F6')->getCalculatedValue())->toEqual(45000);
});

it('no entrega reportes financieros a quien no tiene el permiso', function () {
    $coordinador = $this->usuario('Coordinador');

    $this->actingAs($coordinador)->getJson('/api/reportes/mensual.pdf')->assertForbidden();
    $this->actingAs($coordinador)->getJson('/api/reportes/mensual.xlsx')->assertForbidden();
});
