<?php

namespace App\Modules\Solicitudes\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Solicitudes\Application\GestionSolicitudes;
use App\Modules\Solicitudes\Domain\EstadoSolicitud;
use App\Modules\Solicitudes\Domain\PoliticaReserva;
use App\Modules\Solicitudes\Infrastructure\Models\Solicitud;
use App\Shared\Contratos\IExpediente;
use App\Shared\Infrastructure\Parametros;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class SolicitudController extends Controller
{
    public function __construct(private GestionSolicitudes $gestion) {}

    /** Lista para evaluación: expediente, servicio, abono registrado frente al mínimo y estado */
    public function index(Request $request, Parametros $parametros): JsonResponse
    {
        $q = DB::table('solicitudes as s')
            ->join('expedientes as e', 'e.id', '=', 's.expediente_id')
            ->join('clientes as c', 'c.id', '=', 'e.cliente_id')
            ->join('difuntos as d', 'd.id', '=', 'e.difunto_id')
            ->join('sucursales as su', 'su.id', '=', 's.sucursal_id')
            ->leftJoin('vista_saldos_expediente as v', 'v.expediente_id', '=', 'e.id')
            ->leftJoin('asignaciones as a', 'a.solicitud_id', '=', 's.id')
            ->leftJoin('vehiculos as ve', 've.id', '=', 'a.vehiculo_id')
            ->whereNull('s.deleted_at')
            ->select(
                's.id',
                's.estado',
                's.velatorio_inicio',
                's.traslado_at',
                's.lugar_velatorio',
                's.lugar_sepultura',
                's.comentario',
                's.created_at',
                'e.id as expediente_id',
                'e.codigo',
                'c.nombre as cliente',
                'd.nombre as difunto',
                'su.nombre as sucursal',
                'v.total',
                'v.pagado',
                'v.saldo',
                've.nombre as vehiculo',
                've.origen as vehiculo_origen'
            )
            ->orderByRaw("CASE s.estado WHEN 'en_revision' THEN 0 WHEN 'con_observaciones' THEN 1 WHEN 'aceptada' THEN 2 ELSE 3 END")
            ->orderBy('s.traslado_at');

        if ($request->filled('estado')) {
            $q->whereIn('s.estado', explode(',', $request->query('estado')));
        }
        if ($texto = trim((string) $request->query('q'))) {
            $like = '%'.addcslashes($texto, '%_').'%';
            $q->where(fn ($w) => $w->where('e.codigo', 'ilike', $like)->orWhere('c.nombre', 'ilike', $like)->orWhere('d.nombre', 'ilike', $like));
        }
        if ($request->filled('expediente_id')) {
            $q->where('s.expediente_id', $request->integer('expediente_id'));
        }
        $pct = $parametros->entero('abono_minimo_pct');
        $pagina = $q->paginate(min((int) $request->query('por_pagina', 25), 200));
        $pagina->getCollection()->transform(function ($fila) use ($pct) {
            $fila->abono_minimo = PoliticaReserva::abonoMinimoRequerido((int) $fila->total, $pct);
            $fila->abono_cumplido = (int) $fila->pagado >= $fila->abono_minimo;

            return $fila;
        });

        return response()->json($pagina);
    }

    public function show(int $id, Parametros $parametros): JsonResponse
    {
        $s = Solicitud::with([
            'expediente.cliente:id,nombre,rut,telefono',
            'expediente.difunto',
            'sucursal:id,nombre',
            'productos:id,codigo,nombre,tipo,stock',
            'asignacion.vehiculo:id,nombre,patente,origen,proveedor_externo',
            'asignacion.equipo:id,nombre',
            'evaluador:id,nombre',
        ])->findOrFail($id);
        $resumen = app(IExpediente::class)->resumenFinanciero($s->expediente_id);
        $minimo = PoliticaReserva::abonoMinimoRequerido($resumen['total'], $parametros->entero('abono_minimo_pct'));

        return response()->json([...$s->toArray(), 'resumen' => [...$resumen, 'abono_minimo' => $minimo, 'abono_cumplido' => $resumen['pagado'] >= $minimo]]);
    }

    public function store(Request $request): JsonResponse
    {
        $solicitud = $this->gestion->crear($request->validate($this->reglas()));

        return response()->json($solicitud->fresh(['productos', 'asignacion']), 201);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $datos = $request->validate($this->reglas(true));

        return response()->json($this->gestion->modificar($id, $datos)->fresh(['productos', 'asignacion']));
    }

    public function aceptar(int $id): JsonResponse
    {
        return response()->json(['message' => 'Solicitud aceptada. Se reservaron la urna, el vehículo y el equipo.', 'solicitud' => $this->gestion->aceptar($id)]);
    }

    public function observar(Request $request, int $id): JsonResponse
    {
        $datos = $request->validate(['comentario' => ['required', 'string', 'min:5', 'max:1000']]);

        return response()->json($this->gestion->evaluar($id, EstadoSolicitud::ConObservaciones, $datos['comentario']));
    }

    public function noAplica(Request $request, int $id): JsonResponse
    {
        $datos = $request->validate(['comentario' => ['required', 'string', 'min:5', 'max:1000']]);

        return response()->json($this->gestion->evaluar($id, EstadoSolicitud::NoAplica, $datos['comentario']));
    }

    public function cancelar(Request $request, int $id): JsonResponse
    {
        $datos = $request->validate(['motivo' => ['required', 'string', 'min:5', 'max:1000']]);

        return response()->json($this->gestion->cancelar($id, $datos['motivo']));
    }

    public function ejecutar(int $id): JsonResponse
    {
        return response()->json($this->gestion->ejecutar($id));
    }

    private function reglas(bool $edicion = false): array
    {
        return [
            'expediente_id' => [$edicion ? 'prohibited' : 'required', 'integer', Rule::exists('expedientes', 'id')->whereNull('deleted_at')],
            'sucursal_id' => ['required', 'integer', Rule::exists('sucursales', 'id')->whereNull('deleted_at')],
            'valor_servicio' => ['required', 'integer', 'min:0', 'max:100000000'],
            'descuento' => ['nullable', 'integer', 'min:0', 'lte:valor_servicio'],
            'velatorio_inicio' => ['required', 'date'],
            'lugar_velatorio' => ['required', 'string', 'max:200'],
            'traslado_at' => ['required', 'date', 'after:velatorio_inicio'],
            'lugar_sepultura' => ['required', 'string', 'max:200'],
            'sepultura_detalle' => ['nullable', 'string', 'max:200'],
            'mensaje' => ['nullable', 'string', 'max:2000'],
            'productos' => ['required', 'array', 'min:1', 'max:10'],
            'productos.*.producto_id' => ['required', 'integer', 'distinct', Rule::exists('productos', 'id')->whereNull('deleted_at')],
            'productos.*.cantidad' => ['nullable', 'integer', 'min:1', 'max:50'],
            'vehiculo_id' => ['required', 'integer', Rule::exists('vehiculos', 'id')->whereNull('deleted_at')->where('activo', true)],
            'equipo' => ['required', 'array', 'min:1', 'max:6'],
            'equipo.*' => ['integer', 'distinct', Rule::exists('usuarios', 'id')->whereNull('deleted_at')->where('activo', true)],
        ];
    }
}
