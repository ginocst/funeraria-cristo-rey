<?php

namespace App\Modules\Reportes\Application;

use App\Shared\Infrastructure\HoraLocal;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * Indicadores del panel de inicio y del reporte mensual
 * Reportes solo lee datos (consultas agregadas); nunca modifica otros módulos
 */
class Indicadores
{
    public function panel(bool $conMontos): array
    {
        $zona = HoraLocal::zona();
        $ahora = CarbonImmutable::now('UTC');
        $mes = CarbonImmutable::now($zona)->startOfMonth();
        [$iniMes, $finMes] = [$mes->utc(), $mes->addMonth()->utc()];
        [$iniAnt, $finAnt] = [$mes->subMonth()->utc(), $mes->utc()];

        $kpis = [
            'servicios_mes' => $this->serviciosEntre($iniMes, $finMes),
            'servicios_mes_anterior' => $this->serviciosEntre($iniAnt, $finAnt),
            'en_curso' => count($this->enCurso($ahora)),
            'en_revision' => DB::table('solicitudes')->whereNull('deleted_at')->whereIn('estado', ['en_revision', 'con_observaciones'])->count(),
            'cuotas_atrasadas' => $this->cuotasAtrasadas(),
            'stock_critico' => DB::table('productos')->whereNull('deleted_at')->whereColumn('stock', '<=', 'umbral_minimo')->count(),
        ];
        if ($conMontos) {
            $ingresos = (int) DB::table('pagos')->where('fecha', '>=', $iniMes)->where('fecha', '<', $finMes)->sum('monto');
            $gastos = (int) DB::table('gastos')->whereNull('deleted_at')->where('fecha', '>=', $mes->toDateString())->where('fecha', '<', $mes->addMonth()->toDateString())->sum('monto');
            $kpis += [
                'ingresos_mes' => $ingresos,
                'gastos_mes' => $gastos,
                'resultado_mes' => $ingresos - $gastos,
                'saldo_por_cobrar' => (int) DB::table('vista_saldos_expediente')->where('saldo', '>', 0)->sum('saldo'),
            ];
        }

        return [
            'kpis' => $kpis,
            'servicios_por_mes' => $this->serviciosPorMes(12),
            'finanzas_por_mes' => $conMontos ? $this->finanzasPorMes(12) : null,
            'urnas_mas_vendidas' => $this->urnasMasVendidas(CarbonImmutable::now('UTC')->subMonths(12), $ahora, 6),
            'solicitudes_por_estado' => DB::table('solicitudes')->whereNull('deleted_at')->groupBy('estado')->selectRaw('estado, COUNT(*) AS total')->pluck('total', 'estado'),
            'en_curso' => $this->enCurso($ahora),
            'servicios_hoy' => $this->serviciosDelDia(CarbonImmutable::now($zona)->startOfDay()),
            'proximos_traslados' => $this->proximosTraslados($ahora, 8),
            'stock_critico' => DB::table('productos')->whereNull('deleted_at')->whereColumn('stock', '<=', 'umbral_minimo')
                ->orderBy('stock')->limit(6)->get(['id', 'codigo', 'nombre', 'stock', 'umbral_minimo', 'infantil']),
        ];
    }

    public function mensual(string $mes): array
    {
        $ini = HoraLocal::inicioMesUtc($mes);
        $fin = $ini->setTimezone(HoraLocal::zona())->addMonth()->utc();
        $iniFecha = $ini->setTimezone(HoraLocal::zona())->toDateString();
        $finFecha = $ini->setTimezone(HoraLocal::zona())->addMonth()->toDateString();

        $servicios = DB::table('solicitudes as s')->join('expedientes as e', 'e.id', '=', 's.expediente_id')
            ->join('clientes as c', 'c.id', '=', 'e.cliente_id')->join('difuntos as d', 'd.id', '=', 'e.difunto_id')
            ->join('sucursales as su', 'su.id', '=', 's.sucursal_id')->leftJoin('vista_saldos_expediente as v', 'v.expediente_id', '=', 'e.id')
            ->whereNull('s.deleted_at')->whereIn('s.estado', ['aceptada', 'ejecutada'])
            ->where('s.traslado_at', '>=', $ini)->where('s.traslado_at', '<', $fin)->orderBy('s.traslado_at')
            ->get(['e.codigo', 'd.nombre as difunto', 'c.nombre as cliente', 'su.nombre as sucursal', 's.traslado_at', 's.lugar_sepultura', 's.estado', 'v.total', 'v.pagado', 'v.saldo']);

        $ingresosPorMedio = DB::table('pagos as p')->join('medios_pago as m', 'm.id', '=', 'p.medio_pago_id')
            ->where('p.fecha', '>=', $ini)->where('p.fecha', '<', $fin)->groupBy('m.nombre')->orderBy('m.nombre')
            ->selectRaw('m.nombre AS medio, SUM(p.monto) AS total, COUNT(*) AS cantidad')->get();
        $gastosPorCategoria = DB::table('gastos')->whereNull('deleted_at')->where('fecha', '>=', $iniFecha)->where('fecha', '<', $finFecha)
            ->groupBy('categoria')->orderBy('categoria')->selectRaw('categoria, SUM(monto) AS total, COUNT(*) AS cantidad')->get();
        $ingresos = (int) $ingresosPorMedio->sum('total');
        $gastos = (int) $gastosPorCategoria->sum('total');

        return [
            'mes' => $mes,
            'servicios' => $servicios,
            'totales' => ['servicios' => $servicios->count(), 'ingresos' => $ingresos, 'gastos' => $gastos, 'resultado' => $ingresos - $gastos],
            'ingresos_por_medio' => $ingresosPorMedio,
            'gastos_por_categoria' => $gastosPorCategoria,
            'urnas_vendidas' => $this->urnasMasVendidas($ini, $fin, 20),
        ];
    }

    private function serviciosEntre(CarbonImmutable $ini, CarbonImmutable $fin): int
    {
        return DB::table('solicitudes')->whereNull('deleted_at')->whereIn('estado', ['aceptada', 'ejecutada'])
            ->where('traslado_at', '>=', $ini)->where('traslado_at', '<', $fin)->count();
    }

    private function serviciosPorMes(int $meses): array
    {
        $zona = HoraLocal::zona();
        $filas = DB::table('solicitudes')->whereNull('deleted_at')->whereIn('estado', ['aceptada', 'ejecutada'])
            ->where('traslado_at', '>=', CarbonImmutable::now($zona)->startOfMonth()->subMonths($meses - 1)->utc())
            ->selectRaw("to_char((traslado_at AT TIME ZONE 'UTC') AT TIME ZONE ?, 'YYYY-MM') AS mes, COUNT(*) AS total", [$zona])
            ->groupBy('mes')->pluck('total', 'mes');

        return $this->rellenarMeses($meses, fn ($m) => (int) ($filas[$m] ?? 0));
    }

    private function finanzasPorMes(int $meses): array
    {
        $zona = HoraLocal::zona();
        $desde = CarbonImmutable::now($zona)->startOfMonth()->subMonths($meses - 1);
        $ingresos = DB::table('pagos')->where('fecha', '>=', $desde->utc())
            ->selectRaw("to_char((fecha AT TIME ZONE 'UTC') AT TIME ZONE ?, 'YYYY-MM') AS mes, SUM(monto) AS total", [$zona])->groupBy('mes')->pluck('total', 'mes');
        $gastos = DB::table('gastos')->whereNull('deleted_at')->where('fecha', '>=', $desde->toDateString())
            ->selectRaw("to_char(fecha, 'YYYY-MM') AS mes, SUM(monto) AS total")->groupBy('mes')->pluck('total', 'mes');

        return $this->rellenarMeses($meses, fn ($m) => ['ingresos' => (int) ($ingresos[$m] ?? 0), 'gastos' => (int) ($gastos[$m] ?? 0)]);
    }

    private function rellenarMeses(int $meses, callable $valor): array
    {
        $inicio = CarbonImmutable::now(HoraLocal::zona())->startOfMonth()->subMonths($meses - 1);
        $salida = [];
        for ($i = 0; $i < $meses; $i++) {
            $m = $inicio->addMonths($i)->format('Y-m');
            $v = $valor($m);
            $salida[] = is_array($v) ? ['mes' => $m, ...$v] : ['mes' => $m, 'total' => $v];
        }

        return $salida;
    }

    private function urnasMasVendidas(CarbonImmutable $desde, CarbonImmutable $hasta, int $limite)
    {
        return DB::table('producto_solicitud as ps')->join('solicitudes as s', 's.id', '=', 'ps.solicitud_id')->join('productos as p', 'p.id', '=', 'ps.producto_id')
            ->whereIn('s.estado', ['aceptada', 'ejecutada'])->whereNull('s.deleted_at')->whereIn('p.tipo', ['ataud', 'urna', 'urna_cremacion'])
            ->where('s.traslado_at', '>=', $desde)->where('s.traslado_at', '<', $hasta)
            ->groupBy('p.id', 'p.codigo', 'p.nombre')->orderByDesc('cantidad')->limit($limite)
            ->selectRaw('p.id, p.codigo, p.nombre, SUM(ps.cantidad) AS cantidad, SUM(ps.cantidad * ps.precio_unitario) AS monto')->get();
    }

    private function cuotasAtrasadas(): int
    {
        $hoy = CarbonImmutable::now(HoraLocal::zona())->toDateString();

        return DB::table('cuotas as cu')
            ->leftJoin(DB::raw('(SELECT cuota_id, SUM(monto) AS pagado FROM pagos WHERE cuota_id IS NOT NULL GROUP BY cuota_id) p'), 'p.cuota_id', '=', 'cu.id')
            ->whereNull('cu.deleted_at')->where('cu.vencimiento', '<', $hoy)->whereRaw('COALESCE(p.pagado, 0) < cu.monto')->count();
    }

    /** Servicios en curso en tiempo real: velatorio o traslado ocurriendo ahora */
    private function enCurso(CarbonImmutable $ahora): array
    {
        return DB::table('solicitudes as s')->join('expedientes as e', 'e.id', '=', 's.expediente_id')->join('difuntos as d', 'd.id', '=', 'e.difunto_id')
            ->join('asignaciones as a', 'a.solicitud_id', '=', 's.id')->leftJoin('vehiculos as v', 'v.id', '=', 'a.vehiculo_id')
            ->where('s.estado', 'aceptada')->whereNull('s.deleted_at')->where('s.velatorio_inicio', '<=', $ahora)->where('a.fin', '>', $ahora)
            ->orderBy('s.traslado_at')
            ->get(['s.id', 'e.id as expediente_id', 'e.codigo', 'd.nombre as difunto', 's.velatorio_inicio', 's.lugar_velatorio', 's.traslado_at', 's.lugar_sepultura', 'a.fin', 'v.nombre as vehiculo'])
            ->map(function ($s) use ($ahora) {
                $s->etapa = $ahora < CarbonImmutable::parse($s->traslado_at, 'UTC') ? 'velatorio' : 'traslado';
                $s->ubicacion = $s->etapa === 'velatorio' ? $s->lugar_velatorio : $s->lugar_sepultura;

                return $s;
            })->all();
    }

    /** Línea de tiempo del día (hora local): velatorios y traslados de las solicitudes aceptadas */
    private function serviciosDelDia(CarbonImmutable $diaLocal): array
    {
        [$ini, $fin] = [$diaLocal->utc(), $diaLocal->addDay()->utc()];

        return DB::table('solicitudes as s')->join('expedientes as e', 'e.id', '=', 's.expediente_id')->join('difuntos as d', 'd.id', '=', 'e.difunto_id')
            ->join('asignaciones as a', 'a.solicitud_id', '=', 's.id')->leftJoin('vehiculos as v', 'v.id', '=', 'a.vehiculo_id')
            ->whereIn('s.estado', ['aceptada', 'ejecutada'])->whereNull('s.deleted_at')
            ->where('s.velatorio_inicio', '<', $fin)->where('a.fin', '>', $ini)->orderBy('s.velatorio_inicio')
            ->get(['s.id', 'e.codigo', 'd.nombre as difunto', 's.velatorio_inicio', 's.traslado_at', 'a.inicio as presentacion', 'a.fin', 's.lugar_velatorio', 's.lugar_sepultura', 'v.nombre as vehiculo'])
            ->all();
    }

    private function proximosTraslados(CarbonImmutable $ahora, int $limite): array
    {
        $filas = DB::table('solicitudes as s')->join('expedientes as e', 'e.id', '=', 's.expediente_id')->join('difuntos as d', 'd.id', '=', 'e.difunto_id')
            ->join('asignaciones as a', 'a.solicitud_id', '=', 's.id')->leftJoin('vehiculos as v', 'v.id', '=', 'a.vehiculo_id')
            ->where('s.estado', 'aceptada')->whereNull('s.deleted_at')->where('s.traslado_at', '>=', $ahora)->orderBy('s.traslado_at')->limit($limite)
            ->get(['s.id', 'a.id as asignacion_id', 'e.codigo', 'd.nombre as difunto', 's.traslado_at', 'a.inicio as presentacion', 's.lugar_sepultura', 'v.nombre as vehiculo', 'v.origen']);
        $equipos = DB::table('asignacion_usuario as au')->join('usuarios as u', 'u.id', '=', 'au.usuario_id')
            ->whereIn('au.asignacion_id', $filas->pluck('asignacion_id'))->get(['au.asignacion_id', 'u.nombre'])->groupBy('asignacion_id');

        return $filas->map(function ($f) use ($equipos) {
            $f->equipo = ($equipos[$f->asignacion_id] ?? collect())->pluck('nombre')->all();

            return $f;
        })->all();
    }
}
