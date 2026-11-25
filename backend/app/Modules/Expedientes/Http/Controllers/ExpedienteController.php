<?php

namespace App\Modules\Expedientes\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Expedientes\Infrastructure\Models\Difunto;
use App\Modules\Expedientes\Infrastructure\Models\Expediente;
use App\Shared\Contratos\IAuditoria;
use App\Shared\Contratos\IExpediente;
use App\Shared\Domain\ReglaNegocioException;
use App\Shared\Domain\Rut;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class ExpedienteController extends Controller
{
    public function __construct(private IAuditoria $auditoria, private IExpediente $expedientes) {}

    public function index(Request $request): JsonResponse
    {
        $q = DB::table('expedientes as e')
            ->join('clientes as c', 'c.id', '=', 'e.cliente_id')
            ->join('difuntos as d', 'd.id', '=', 'e.difunto_id')
            ->join('sucursales as s', 's.id', '=', 'e.sucursal_id')
            ->leftJoin('vista_saldos_expediente as v', 'v.expediente_id', '=', 'e.id')
            ->whereNull('e.deleted_at')
            ->select(
                'e.id',
                'e.codigo',
                'e.estado',
                'e.created_at',
                'c.id as cliente_id',
                'c.nombre as cliente',
                'c.rut as cliente_rut',
                'd.nombre as difunto',
                's.nombre as sucursal',
                'v.total',
                'v.pagado',
                'v.saldo'
            )
            ->orderByDesc('e.id');

        if ($texto = trim((string) $request->query('q'))) {
            $like = '%'.addcslashes($texto, '%_').'%';
            $q->where(fn ($w) => $w->where('e.codigo', 'ilike', $like)->orWhere('c.nombre', 'ilike', $like)
                ->orWhere('d.nombre', 'ilike', $like)->orWhere('c.rut', 'ilike', $like));
        }
        if ($request->filled('estado')) {
            $q->where('e.estado', $request->query('estado'));
        }
        if ($request->boolean('con_saldo')) {
            $q->where('v.saldo', '>', 0);
        }

        return response()->json($q->paginate(min((int) $request->query('por_pagina', 25), 200)));
    }

    public function store(Request $request): JsonResponse
    {
        $datos = $request->validate($this->reglas());
        $expediente = DB::transaction(function () use ($datos) {
            $difunto = Difunto::create($this->datosDifunto($datos['difunto']));

            return Expediente::create([...collect($datos)->except('difunto')->all(), 'difunto_id' => $difunto->id, 'estado' => 'abierto']);
        });
        $this->auditoria->registrar('crear', 'expedientes', $expediente->id, null, $expediente->toArray());

        return response()->json($expediente->fresh(['cliente', 'difunto']), 201);
    }

    public function show(int $id): JsonResponse
    {
        $e = Expediente::with([
            'cliente.convenios:id,nombre',
            'difunto',
            'sucursal:id,nombre',
            'convenio:id,nombre,descuento_pct',
            'actividades',
            'documentos',
            'solicitudes.productos:id,codigo,nombre,tipo',
            'solicitudes.asignacion.vehiculo:id,nombre,patente,origen',
            'solicitudes.asignacion.equipo:id,nombre',
        ])->findOrFail($id);

        return response()->json([...$e->toArray(), 'resumen' => $this->expedientes->resumenFinanciero($id)]);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $expediente = Expediente::with('difunto')->findOrFail($id);
        $datos = $request->validate($this->reglas(true));
        $antes = $expediente->toArray();
        DB::transaction(function () use ($expediente, $datos) {
            if (isset($datos['difunto'])) {
                $expediente->difunto->update($this->datosDifunto($datos['difunto']));
            }
            $expediente->update(collect($datos)->except('difunto')->all());
        });
        $this->auditoria->registrar('editar', 'expedientes', $id, $antes, $expediente->fresh('difunto')->toArray());

        return response()->json($expediente->fresh(['cliente', 'difunto']));
    }

    public function destroy(int $id): JsonResponse
    {
        $expediente = Expediente::withCount(['solicitudes' => fn ($q) => $q->whereIn('estado', ['aceptada', 'ejecutada'])])->findOrFail($id);
        if ($expediente->solicitudes_count > 0 || DB::table('pagos')->where('expediente_id', $id)->exists()) {
            throw new ReglaNegocioException('El expediente tiene servicios o pagos registrados; cámbielo a «anulado» en lugar de eliminarlo.');
        }
        $expediente->delete();
        $this->auditoria->registrar('eliminar', 'expedientes', $id);

        return response()->json(null, 204);
    }

    private function reglas(bool $edicion = false): array
    {
        $req = $edicion ? 'sometimes' : 'required';

        return [
            'cliente_id' => [$req, 'integer', Rule::exists('clientes', 'id')->whereNull('deleted_at')],
            'sucursal_id' => [$req, 'integer', Rule::exists('sucursales', 'id')->whereNull('deleted_at')],
            'convenio_id' => ['nullable', 'integer', Rule::exists('convenios', 'id')->whereNull('deleted_at')],
            'estado' => ['sometimes', Rule::in(['abierto', 'en_servicio', 'cerrado', 'anulado'])],
            'observaciones' => ['nullable', 'string', 'max:2000'],
            'difunto' => [$req, 'array'],
            'difunto.nombre' => ['required_with:difunto', 'string', 'max:160'],
            'difunto.rut' => ['nullable', 'string', 'max:12', fn ($a, $v, $f) => $v && ! Rut::formatoValido($v) ? $f('El RUT debe tener el formato 12345678-9.') : null],
            'difunto.fecha_nacimiento' => ['nullable', 'date', 'before_or_equal:difunto.fecha_fallecimiento'],
            'difunto.fecha_fallecimiento' => ['required_with:difunto', 'date', 'before_or_equal:today'],
        ];
    }

    private function datosDifunto(array $d): array
    {
        if (! empty($d['rut'])) {
            $d['rut'] = Rut::normalizar($d['rut']);
        }

        return $d;
    }
}
