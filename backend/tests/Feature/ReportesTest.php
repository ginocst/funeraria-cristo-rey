<?php

use Illuminate\Support\Carbon;
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
    $lector = IOFactory::createReader('Xlsx');
    $lector->setIncludeCharts(true);
    $libro = $lector->load($respuesta->baseResponse->getFile()->getPathname());

    expect($libro->getSheetNames())->toBe(['Reporte', 'Análisis', 'Trabajadores', 'Servicios', 'Pagos', 'Gastos', 'Urnas'])
        // Datos
        ->and(collect($libro->getAllSheets())->sum(fn ($hoja) => $hoja->getChartCount()))->toBe(0);
    // La primera hoja tiene las secciones del PDF
    $secciones = [
        'Indicadores del mes',
        'Evolución de los últimos 6 meses',
        'Servicios del mes',
        'Ingresos por medio de pago',
        'Gastos por categoría',
        'Trabajadores del mes',
        'Urnas y ataúdes vendidos',
    ];
    $primeraColumna = array_column($libro->getSheetByName('Reporte')->toArray(), 0);
    expect(array_values(array_intersect($primeraColumna, $secciones)))->toBe($secciones);
    $gastos = $libro->getSheetByName('Gastos');
    expect($gastos->getCell('C5')->getValue())->toBe('=1+1')
        ->and($gastos->getCell('C5')->getDataType())->toBe('s')
        ->and($gastos->getCell('G6')->getCalculatedValue())->toEqual(45000);
});

it('cuenta en el reporte mensual los servicios y las ausencias de cada trabajador', function () {
    $admin = $this->usuario('Administrador');
    $conductor = $this->usuario('Operario', ['nombre' => 'Conductor']);
    $apoyo = $this->usuario('Operario', ['nombre' => 'Apoyo']);
    $libre = $this->usuario('Operario', ['nombre' => 'Sin servicios']);
    $base = Escenario::base();
    $expediente = Escenario::expediente($base['sucursal']);
    $datos = Escenario::datosSolicitud($expediente, $base, [$conductor->id, $apoyo->id]);
    $solicitud = $this->actingAs($admin)->postJson('/api/solicitudes', $datos)->assertCreated()->json('id');
    Escenario::pagar($expediente, 1_000_000);
    $this->actingAs($admin)->postJson("/api/solicitudes/$solicitud/aceptar", [], ['Idempotency-Key' => 'rep-1'])->assertOk();
    $mes = Carbon::parse($datos['traslado_at'])->setTimezone('America/Santiago')->format('Y-m');
    DB::table('ausencias')->insert(['usuario_id' => $libre->id, 'tipo' => 'permiso', 'desde' => "$mes-01", 'hasta' => "$mes-02", 'created_at' => now(), 'updated_at' => now()]);

    $trabajadores = collect($this->actingAs($admin)->getJson("/api/reportes/mensual?mes=$mes")->assertOk()->json('trabajadores'))->keyBy('nombre');

    expect($trabajadores['Conductor'])->toMatchArray(['cargo' => 'Operario', 'servicios' => 1, 'conductor' => 1, 'apoyo' => 0])
        ->and($trabajadores['Apoyo'])->toMatchArray(['servicios' => 1, 'conductor' => 0, 'apoyo' => 1])
        ->and($trabajadores['Sin servicios'])->toMatchArray(['servicios' => 0, 'dias_ausente' => 2])
        ->and($trabajadores->has($admin->nombre))->toBeFalse();
});

it('cuenta las donaciones como gasto en el reporte mensual y en el panel', function () {
    $admin = $this->usuario('Administrador');
    $base = Escenario::base();
    $hoy = now('America/Santiago')->toDateString();
    $mes = substr($hoy, 0, 7);
    $marcas = ['created_at' => now(), 'updated_at' => now()];
    DB::table('gastos')->insert(['sucursal_id' => $base['sucursal'], 'categoria' => 'Combustible', 'descripcion' => 'Bencina', 'monto' => 45000, 'fecha' => $hoy, ...$marcas]);
    $categoria = DB::table('categorias_organizacion')->insertGetId(['nombre' => 'Deportiva', ...$marcas]);
    $club = DB::table('organizaciones')->insertGetId(['categoria_id' => $categoria, 'nombre' => 'Club', ...$marcas]);
    DB::table('donaciones')->insert(['organizacion_id' => $club, 'fecha' => $hoy, 'monto' => 100000, ...$marcas]);
    DB::table('donaciones')->insert(['organizacion_id' => $club, 'fecha' => $hoy, 'monto' => 999000, 'deleted_at' => now(), ...$marcas]);

    $this->actingAs($admin)->getJson("/api/reportes/mensual?mes=$mes")->assertOk()
        ->assertJsonPath('totales.gastos', 145000)
        ->assertJsonPath('totales.resultado', -145000)
        ->assertJsonPath('donaciones_por_organizacion.0', ['nombre' => 'Club', 'cantidad' => 1, 'monto' => 100000])
        ->assertJsonPath('evolucion.5.gastos', 145000);
    $this->getJson('/api/dashboard')->assertOk()->assertJsonPath('kpis.gastos_mes', 145000);

    $excel = $this->get("/api/reportes/mensual.xlsx?mes=$mes")->assertOk();
    $gastos = IOFactory::load($excel->baseResponse->getFile()->getPathname())->getSheetByName('Gastos');
    // La donación muestra su organización en una columna distinta a la del proveedor
    expect([$gastos->getCell('D4')->getValue(), $gastos->getCell('E4')->getValue()])->toBe(['Proveedor', 'Organización'])
        ->and($gastos->getCell('D6')->getValue())->toBe('—')
        ->and($gastos->getCell('E6')->getValue())->toBe('Club')
        ->and($gastos->getCell('E5')->getValue())->toBe('—')
        // La suma del detalle coincide con el total del mes
        ->and($gastos->getCell('G7')->getCalculatedValue())->toEqual(145000);
    $this->get("/api/reportes/mensual.pdf?mes=$mes")->assertOk();
});

it('exporta el panel en PDF y en Excel con sus gráficos', function () {
    $admin = $this->usuario('Administrador');
    Escenario::base();

    $pdf = $this->actingAs($admin)->get('/api/dashboard.pdf')->assertOk()->assertHeader('content-type', 'application/pdf');
    expect($pdf->getContent())->toStartWith('%PDF');

    $excel = $this->actingAs($admin)->get('/api/dashboard.xlsx')->assertOk();
    $lector = IOFactory::createReader('Xlsx');
    $lector->setIncludeCharts(true);
    $libro = $lector->load($excel->baseResponse->getFile()->getPathname());

    expect($libro->getSheetNames())->toBe(['Resumen', 'Servicios por mes', 'Ingresos y gastos', 'Urnas más elegidas', 'Solicitudes'])
        ->and($libro->getSheetByName('Servicios por mes')->getChartCount())->toBe(1)
        ->and($libro->getSheetByName('Ingresos y gastos')->getChartCount())->toBe(1);
});

it('exporta el panel del coordinador sin montos', function () {
    $coordinador = $this->usuario('Coordinador');

    $this->actingAs($coordinador)->get('/api/dashboard.pdf')->assertOk();
    $excel = $this->actingAs($coordinador)->get('/api/dashboard.xlsx')->assertOk();
    $libro = IOFactory::load($excel->baseResponse->getFile()->getPathname());

    expect($libro->getSheetNames())->not->toContain('Ingresos y gastos')
        ->and(json_encode($libro->getSheetByName('Resumen')->toArray()))->not->toContain('Ingresos');
});

it('no entrega el panel exportado a un operario', function () {
    $operario = $this->usuario('Operario');

    $this->actingAs($operario)->getJson('/api/dashboard.pdf')->assertForbidden();
    $this->actingAs($operario)->getJson('/api/dashboard.xlsx')->assertForbidden();
});

it('no entrega reportes financieros a quien no tiene el permiso', function () {
    $coordinador = $this->usuario('Coordinador');

    $this->actingAs($coordinador)->getJson('/api/reportes/mensual.pdf')->assertForbidden();
    $this->actingAs($coordinador)->getJson('/api/reportes/mensual.xlsx')->assertForbidden();
});
