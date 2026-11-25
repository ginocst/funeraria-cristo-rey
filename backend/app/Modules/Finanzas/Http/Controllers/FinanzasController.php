<?php

namespace App\Modules\Finanzas\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Finanzas\Application\GestionPagos;
use App\Modules\Finanzas\Domain\PlanCuotas;
use App\Modules\Finanzas\Infrastructure\Models\Cuota;
use App\Modules\Finanzas\Infrastructure\Models\Pago;
use App\Modules\Solicitudes\Domain\PoliticaReserva;
use App\Shared\Contratos\IExpediente;
use App\Shared\Infrastructure\HoraLocal;
use App\Shared\Infrastructure\Parametros;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/** Pagos, abonos y cuotas por expediente (reemplaza el libro de pagos) */
class FinanzasController extends Controller
{
    public function __construct(private GestionPagos $gestion, private IExpediente $expedientes) {}

    public function resumen(int $expedienteId, Parametros $parametros): JsonResponse
    {
        $resumen = $this->expedientes->resumenFinanciero($expedienteId);
        $pct = $parametros->entero('abono_minimo_pct');
        $pagos = Pago::with(['medioPago:id,nombre', 'comprobante:id,pago_id,folio', 'registradoPor:id,nombre'])
            ->where('expediente_id', $expedienteId)->orderByDesc('id')->get();
        $anulados = $pagos->whereNotNull('anula_pago_id')->pluck('anula_pago_id')->all();

        return response()->json([
            'resumen' => [...$resumen, 'abono_minimo' => PoliticaReserva::abonoMinimoRequerido($resumen['total'], $pct), 'abono_minimo_pct' => $pct],
            'pagos' => $pagos->map(fn (Pago $p) => [...$p->toArray(), 'anulado' => in_array($p->id, $anulados, true)]),
            'cuotas' => $this->cuotasConEstado($expedienteId),
        ]);
    }

    public function registrar(Request $request): JsonResponse
    {
        $datos = $request->validate([
            'expediente_id' => ['required', 'integer', Rule::exists('expedientes', 'id')->whereNull('deleted_at')],
            'monto' => ['required', 'integer'],
            'medio_pago_id' => ['required', 'integer', Rule::exists('medios_pago', 'id')->whereNull('deleted_at')],
            'cuota_id' => ['nullable', 'integer'],
            'referencia' => ['nullable', 'string', 'max:80'],
        ]);
        $pago = $this->gestion->registrar($datos);

        return response()->json(['pago' => $pago, 'resumen' => $this->expedientes->resumenFinanciero($pago->expediente_id)], 201);
    }

    public function anular(Request $request, int $id): JsonResponse
    {
        $datos = $request->validate(['motivo' => ['required', 'string', 'min:5', 'max:250']]);
        $anulacion = $this->gestion->anular($id, $datos['motivo']);

        return response()->json(['anulacion' => $anulacion, 'resumen' => $this->expedientes->resumenFinanciero($anulacion->expediente_id)], 201);
    }

    public function pactarCuotas(Request $request, int $expedienteId): JsonResponse
    {
        $datos = $request->validate([
            'numero_cuotas' => ['required', 'integer', 'min:1', 'max:24'],
            'primer_vencimiento' => ['required', 'date', 'after_or_equal:today'],
        ]);
        $this->gestion->pactarCuotas($expedienteId, $datos['numero_cuotas'], $datos['primer_vencimiento']);

        return response()->json($this->cuotasConEstado($expedienteId), 201);
    }

    /** Pagos recientes de todos los expedientes (Administrador y Coordinador) */
    public function pagos(Request $request): JsonResponse
    {
        $q = DB::table('pagos as p')->join('expedientes as e', 'e.id', '=', 'p.expediente_id')->join('clientes as c', 'c.id', '=', 'e.cliente_id')
            ->join('medios_pago as m', 'm.id', '=', 'p.medio_pago_id')->leftJoin('comprobantes as co', 'co.pago_id', '=', 'p.id')
            ->leftJoin('usuarios as u', 'u.id', '=', 'p.created_by')
            ->select(
                'p.id',
                'p.tipo',
                'p.monto',
                'p.fecha',
                'p.referencia',
                'p.motivo_anulacion',
                'e.id as expediente_id',
                'e.codigo',
                'c.nombre as cliente',
                'm.nombre as medio',
                'co.id as comprobante_id',
                'co.folio',
                'u.nombre as usuario'
            )
            ->orderByDesc('p.id');
        $request->validate(['desde' => ['nullable', 'date_format:Y-m-d'], 'hasta' => ['nullable', 'date_format:Y-m-d']]);
        // Los días se cuentan en hora local y se convierten a UTC, que es como se guarda `fecha`.
        if ($request->filled('desde')) {
            $q->where('p.fecha', '>=', CarbonImmutable::parse($request->query('desde'), HoraLocal::zona())->startOfDay()->utc());
        }
        if ($request->filled('hasta')) {
            $q->where('p.fecha', '<', CarbonImmutable::parse($request->query('hasta'), HoraLocal::zona())->startOfDay()->addDay()->utc());
        }

        return response()->json($q->paginate(min((int) $request->query('por_pagina', 30), 200)));
    }

    /** Cuotas atrasadas o por vencer de todos los expedientes, para el seguimiento de cobros */
    public function cobranza(): JsonResponse
    {
        $hoy = CarbonImmutable::now(config('funeraria.zona_horaria_local'))->startOfDay();
        $filas = DB::table('cuotas as cu')->join('expedientes as e', 'e.id', '=', 'cu.expediente_id')->join('clientes as c', 'c.id', '=', 'e.cliente_id')
            ->leftJoin(DB::raw('(SELECT cuota_id, SUM(monto) AS pagado FROM pagos WHERE cuota_id IS NOT NULL GROUP BY cuota_id) p'), 'p.cuota_id', '=', 'cu.id')
            ->whereNull('cu.deleted_at')->where('cu.vencimiento', '<=', $hoy->addDays(15)->toDateString())
            ->whereRaw('COALESCE(p.pagado, 0) < cu.monto')
            ->orderBy('cu.vencimiento')
            ->get(['cu.id', 'cu.numero', 'cu.monto', 'cu.vencimiento', 'e.id as expediente_id', 'e.codigo', 'c.nombre as cliente', 'c.telefono', DB::raw('COALESCE(p.pagado, 0) AS pagado')]);

        return response()->json($filas->map(function ($f) use ($hoy) {
            $f->estado = PlanCuotas::estado((int) $f->monto, (int) $f->pagado, CarbonImmutable::parse($f->vencimiento), $hoy);

            return $f;
        }));
    }

    private function cuotasConEstado(int $expedienteId): array
    {
        $hoy = CarbonImmutable::now(config('funeraria.zona_horaria_local'))->startOfDay();
        $pagado = Pago::where('expediente_id', $expedienteId)->whereNotNull('cuota_id')->groupBy('cuota_id')
            ->selectRaw('cuota_id, SUM(monto) AS pagado')->pluck('pagado', 'cuota_id');

        return Cuota::where('expediente_id', $expedienteId)->orderBy('numero')->get()->map(function (Cuota $c) use ($pagado, $hoy) {
            $p = (int) ($pagado[$c->id] ?? 0);

            return [...$c->toArray(), 'pagado' => $p, 'estado' => PlanCuotas::estado($c->monto, $p, CarbonImmutable::parse($c->vencimiento), $hoy)];
        })->all();
    }
}
