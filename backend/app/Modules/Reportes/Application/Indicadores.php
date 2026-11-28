<?php

namespace App\Modules\Reportes\Application;

use App\Modules\Donaciones\Application\ResumenDonaciones;
use App\Shared\Infrastructure\HoraLocal;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Indicadores del panel de inicio y del reporte mensual
 * Reportes solo lee datos (consultas agregadas); nunca modifica otros módulos
 */
class Indicadores
{
    /** Las donaciones cuentan como gasto, con esta categoría */
    private const DONACIONES = 'Donaciones';

    public function __construct(private ResumenDonaciones $donaciones) {}

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
            $gastos = (int) DB::table('gastos')->whereNull('deleted_at')->where('fecha', '>=', $mes->toDateString())->where('fecha', '<', $mes->addMonth()->toDateString())->sum('monto')
                + $this->donaciones->mensual($mes->format('Y-m'))['totales']['monto'];
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
        $donaciones = $this->donaciones->mensual($mes);
        if ($donaciones['totales']['cantidad'] > 0) {
            $gastosPorCategoria = $gastosPorCategoria
                ->push((object) ['categoria' => self::DONACIONES, 'total' => $donaciones['totales']['monto'], 'cantidad' => $donaciones['totales']['cantidad']])
                ->sortBy('categoria')->values();
        }
        $ingresos = (int) $ingresosPorMedio->sum('total');
        $gastos = (int) $gastosPorCategoria->sum('total');

        return [
            'mes' => $mes,
            'servicios' => $servicios,
            'totales' => ['servicios' => $servicios->count(), 'ingresos' => $ingresos, 'gastos' => $gastos, 'resultado' => $ingresos - $gastos],
            'ingresos_por_medio' => $ingresosPorMedio,
            'gastos_por_categoria' => $gastosPorCategoria,
            'donaciones_por_organizacion' => $donaciones['por_organizacion'],
            'urnas_vendidas' => $this->urnasMasVendidas($ini, $fin, 20),
            'trabajadores' => $this->trabajadores($ini, $fin, $iniFecha, $finFecha),
            'evolucion' => $this->evolucion($ini, 6),
        ];
    }

    /**
     * Movimientos uno por uno del mes, para las hojas de detalle del Excel.
     * Usa los mismos filtros que mensual(), así las sumas coinciden con sus totales.
     */
    public function detalleMensual(string $mes): array
    {
        $ini = HoraLocal::inicioMesUtc($mes);
        $inicioLocal = $ini->setTimezone(HoraLocal::zona());
        $fin = $inicioLocal->addMonth()->utc();

        return [
            'participaciones' => $this->participaciones($ini, $fin)->sortBy([['trabajador', 'asc'], ['traslado_at', 'asc']])->values(),
            'pagos' => DB::table('pagos as p')->join('medios_pago as m', 'm.id', '=', 'p.medio_pago_id')
                ->join('expedientes as e', 'e.id', '=', 'p.expediente_id')->join('clientes as c', 'c.id', '=', 'e.cliente_id')
                ->leftJoin('comprobantes as co', 'co.pago_id', '=', 'p.id')->leftJoin('usuarios as u', 'u.id', '=', 'p.created_by')
                ->where('p.fecha', '>=', $ini)->where('p.fecha', '<', $fin)->orderBy('p.fecha')->orderBy('p.id')
                ->get(['p.fecha', 'co.folio', 'e.codigo', 'c.nombre as cliente', 'm.nombre as medio', 'p.tipo', 'p.referencia', 'u.nombre as registrado_por', 'p.monto']),
            'gastos' => DB::table('gastos as g')->leftJoin('proveedores as pr', 'pr.id', '=', 'g.proveedor_id')
                ->leftJoin('sucursales as su', 'su.id', '=', 'g.sucursal_id')->whereNull('g.deleted_at')
                ->where('g.fecha', '>=', $inicioLocal->toDateString())->where('g.fecha', '<', $inicioLocal->addMonth()->toDateString())
                ->orderBy('g.fecha')->orderBy('g.id')
                ->get(['g.fecha', 'g.categoria', 'g.descripcion', 'pr.nombre as proveedor', DB::raw('null as organizacion'), 'su.nombre as sucursal', 'g.monto'])
                // Las donaciones también son gasto: se agregan al detalle con su organización y sin proveedor
                ->concat($this->donaciones->mensual($mes)['donaciones']->map(fn ($d) => (object) [
                    'fecha' => $d->fecha,
                    'categoria' => self::DONACIONES,
                    'descripcion' => $d->descripcion,
                    'proveedor' => null,
                    'organizacion' => $d->organizacion,
                    'sucursal' => null,
                    'monto' => $d->monto,
                ]))
                ->sortBy('fecha')->values(),
        ];
    }

    private function serviciosEntre(CarbonImmutable $ini, CarbonImmutable $fin): int
    {
        return DB::table('solicitudes')->whereNull('deleted_at')->whereIn('estado', ['aceptada', 'ejecutada'])
            ->where('traslado_at', '>=', $ini)->where('traslado_at', '<', $fin)->count();
    }

    /**
     * Trabajo de cada persona en el mes: en cuántos servicios participa (con los mismos
     * filtros que mensual()), con qué función, cuántas horas y cuántos días estuvo ausente.
     * Aparecen todos los operarios y coordinadores activos, aunque no tengan servicios.
     */
    private function trabajadores(CarbonImmutable $ini, CarbonImmutable $fin, string $iniFecha, string $finFecha): array
    {
        $participaciones = $this->participaciones($ini, $fin)->groupBy('usuario_id');
        $ausencias = DB::table('ausencias')->whereNull('deleted_at')->where('desde', '<', $finFecha)->where('hasta', '>=', $iniFecha)
            ->get(['usuario_id', 'desde', 'hasta'])->groupBy('usuario_id');
        // Rol base de cada persona (las suplencias temporales no cambian el cargo)
        $cargos = DB::table('rol_usuario as ru')->join('roles as r', 'r.id', '=', 'ru.rol_id')
            ->whereNull('ru.deleted_at')->whereNull('ru.vigencia_hasta')->pluck('r.nombre', 'ru.usuario_id');
        $ultimoDia = CarbonImmutable::parse($finFecha)->subDay()->toDateString();

        return DB::table('usuarios')->orderBy('nombre')->get(['id', 'nombre', 'activo', 'deleted_at'])
            ->filter(fn ($u) => $participaciones->has($u->id) || $ausencias->has($u->id)
                || ($u->activo && ! $u->deleted_at && in_array($cargos[$u->id] ?? null, ['Operario', 'Coordinador'], true)))
            ->map(function ($u) use ($participaciones, $ausencias, $cargos, $iniFecha, $ultimoDia) {
                $propias = $participaciones->get($u->id, collect());

                return [
                    'id' => $u->id,
                    'nombre' => $u->nombre,
                    'cargo' => $cargos[$u->id] ?? '—',
                    'servicios' => $propias->count(),
                    'conductor' => $propias->where('funcion', 'conductor')->count(),
                    'apoyo' => $propias->where('funcion', 'apoyo')->count(),
                    'horas' => round($propias->sum(fn ($p) => (strtotime($p->fin) - strtotime($p->inicio)) / 3600), 1),
                    'dias_ausente' => $this->diasAusente($ausencias->get($u->id, collect()), $iniFecha, $ultimoDia),
                ];
            })
            ->sortBy([['servicios', 'desc'], ['nombre', 'asc']])->values()->all();
    }

    /** Una fila por persona y servicio aceptado o ejecutado con traslado en el intervalo */
    private function participaciones(CarbonImmutable $ini, CarbonImmutable $fin): Collection
    {
        return DB::table('asignacion_usuario as au')->join('asignaciones as a', 'a.id', '=', 'au.asignacion_id')
            ->join('solicitudes as s', 's.id', '=', 'a.solicitud_id')->join('usuarios as u', 'u.id', '=', 'au.usuario_id')
            ->join('expedientes as e', 'e.id', '=', 's.expediente_id')->join('difuntos as d', 'd.id', '=', 'e.difunto_id')
            ->leftJoin('vehiculos as v', 'v.id', '=', 'a.vehiculo_id')
            ->whereNull('au.deleted_at')->whereNull('a.deleted_at')->whereNull('s.deleted_at')->whereIn('s.estado', ['aceptada', 'ejecutada'])
            ->where('s.traslado_at', '>=', $ini)->where('s.traslado_at', '<', $fin)
            ->get(['au.usuario_id', 'u.nombre as trabajador', 'au.funcion', 'a.inicio', 'a.fin', 's.traslado_at', 's.estado', 'e.codigo', 'd.nombre as difunto', 'v.nombre as vehiculo']);
    }

    /** Días corridos del mes cubiertos por alguna ausencia */
    private function diasAusente(Collection $ausencias, string $primerDia, string $ultimoDia): int
    {
        $dias = [];
        foreach ($ausencias as $a) {
            $hasta = min($a->hasta, $ultimoDia);
            for ($d = CarbonImmutable::parse(max($a->desde, $primerDia)); $d->toDateString() <= $hasta; $d = $d->addDay()) {
                $dias[$d->toDateString()] = true;
            }
        }

        return count($dias);
    }

    /** Servicios, ingresos y gastos de los últimos meses que terminan en el mes del reporte */
    private function evolucion(CarbonImmutable $iniMesUtc, int $meses): array
    {
        $ultimo = $iniMesUtc->setTimezone(HoraLocal::zona());
        $finanzas = $this->finanzasPorMes($meses, $ultimo);

        return array_map(fn (array $s, array $f) => [...$s, ...$f], $this->serviciosPorMes($meses, $ultimo), $finanzas);
    }

    private function serviciosPorMes(int $meses, ?CarbonImmutable $ultimoMes = null): array
    {
        $zona = HoraLocal::zona();
        $filas = DB::table('solicitudes')->whereNull('deleted_at')->whereIn('estado', ['aceptada', 'ejecutada'])
            ->where('traslado_at', '>=', $this->primerMes($meses, $ultimoMes)->utc())
            ->selectRaw("to_char((traslado_at AT TIME ZONE 'UTC') AT TIME ZONE ?, 'YYYY-MM') AS mes, COUNT(*) AS total", [$zona])
            ->groupBy('mes')->pluck('total', 'mes');

        return $this->rellenarMeses($meses, fn ($m) => (int) ($filas[$m] ?? 0), $ultimoMes);
    }

    private function finanzasPorMes(int $meses, ?CarbonImmutable $ultimoMes = null): array
    {
        $zona = HoraLocal::zona();
        $desde = $this->primerMes($meses, $ultimoMes);
        $ingresos = DB::table('pagos')->where('fecha', '>=', $desde->utc())
            ->selectRaw("to_char((fecha AT TIME ZONE 'UTC') AT TIME ZONE ?, 'YYYY-MM') AS mes, SUM(monto) AS total", [$zona])->groupBy('mes')->pluck('total', 'mes');
        $gastos = DB::table('gastos')->whereNull('deleted_at')->where('fecha', '>=', $desde->toDateString())
            ->selectRaw("to_char(fecha, 'YYYY-MM') AS mes, SUM(monto) AS total")->groupBy('mes')->pluck('total', 'mes');
        $donaciones = DB::table('donaciones')->whereNull('deleted_at')->where('fecha', '>=', $desde->toDateString())
            ->selectRaw("to_char(fecha, 'YYYY-MM') AS mes, SUM(monto) AS total")->groupBy('mes')->pluck('total', 'mes');

        return $this->rellenarMeses($meses, fn ($m) => [
            'ingresos' => (int) ($ingresos[$m] ?? 0),
            'gastos' => (int) ($gastos[$m] ?? 0) + (int) ($donaciones[$m] ?? 0),
        ], $ultimoMes);
    }

    /** Inicio (horario local) del primer mes de una serie que termina en $ultimoMes (por omisión, el actual) */
    private function primerMes(int $meses, ?CarbonImmutable $ultimoMes): CarbonImmutable
    {
        return ($ultimoMes ?? CarbonImmutable::now(HoraLocal::zona()))->startOfMonth()->subMonths($meses - 1);
    }

    private function rellenarMeses(int $meses, callable $valor, ?CarbonImmutable $ultimoMes = null): array
    {
        $inicio = $this->primerMes($meses, $ultimoMes);
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
