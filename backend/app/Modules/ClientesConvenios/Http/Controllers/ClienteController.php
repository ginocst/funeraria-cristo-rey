<?php

namespace App\Modules\ClientesConvenios\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\ClientesConvenios\Infrastructure\Models\Cliente;
use App\Shared\Domain\Rut;
use App\Shared\Http\CrudSimple;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class ClienteController extends Controller
{
    use CrudSimple {
        store as crudStore;
        update as crudUpdate;
    }

    protected function modelo(): string
    {
        return Cliente::class;
    }

    protected function buscables(): array
    {
        return ['nombre', 'rut', 'email', 'telefono'];
    }

    protected function consulta(Request $request): Builder
    {
        return Cliente::query()->with('convenios:id,nombre')->withCount('expedientes');
    }

    /** `convenios` no es una columna de clientes: se sincroniza aparte (sincronizarConvenios) */
    protected function prepararDatos(array $datos, $registro = null): array
    {
        return collect($datos)->except('convenios')->all();
    }

    protected function reglas(Request $request, $registro = null): array
    {
        $request->merge(['rut' => Rut::normalizar((string) $request->input('rut'))]);

        return [
            'rut' => [
                'required',
                'string',
                'max:12',
                fn ($a, $v, $f) => Rut::formatoValido($v) ? null : $f('El RUT debe tener el formato 12345678-9.'),
                Rule::unique('clientes', 'rut')->ignore($registro?->id)->whereNull('deleted_at'),
            ],
            'nombre' => ['required', 'string', 'max:160'],
            'telefono' => ['nullable', 'string', 'max:30'],
            'email' => ['nullable', 'email', 'max:160'],
            'direccion' => ['nullable', 'string', 'max:200'],
            'comuna' => ['nullable', 'string', 'max:80'],
            'convenios' => ['sometimes', 'array'],
            'convenios.*' => ['integer', Rule::exists('convenios', 'id')->whereNull('deleted_at')],
        ];
    }

    public function store(Request $request): JsonResponse
    {
        return DB::transaction(function () use ($request) {
            $respuesta = $this->crudStore($request);
            $this->sincronizarConvenios($request, $respuesta->getData()->id);

            return response()->json(Cliente::with('convenios:id,nombre')->find($respuesta->getData()->id), 201);
        });
    }

    public function update(Request $request, int $id): JsonResponse
    {
        return DB::transaction(function () use ($request, $id) {
            $this->crudUpdate($request, $id);
            $this->sincronizarConvenios($request, $id);

            return response()->json(Cliente::with('convenios:id,nombre')->find($id));
        });
    }

    /** Historial del cliente: sus expedientes con estado y saldo */
    public function show(int $id): JsonResponse
    {
        $cliente = Cliente::with('convenios:id,nombre')->findOrFail($id);
        $expedientes = DB::table('expedientes as e')
            ->join('difuntos as d', 'd.id', '=', 'e.difunto_id')
            ->leftJoin('vista_saldos_expediente as v', 'v.expediente_id', '=', 'e.id')
            ->where('e.cliente_id', $id)->whereNull('e.deleted_at')
            ->orderByDesc('e.id')
            ->get(['e.id', 'e.codigo', 'e.estado', 'e.created_at', 'd.nombre as difunto', 'v.total', 'v.pagado', 'v.saldo']);

        return response()->json([...$cliente->toArray(), 'expedientes' => $expedientes]);
    }

    private function sincronizarConvenios(Request $request, int $clienteId): void
    {
        if (! $request->has('convenios')) {
            return;
        }
        $ids = collect($request->input('convenios', []))->map(fn ($v) => (int) $v)->unique();
        DB::table('cliente_convenio')->where('cliente_id', $clienteId)->whereNotIn('convenio_id', $ids)->update(['deleted_at' => now()]);
        foreach ($ids as $convenioId) {
            DB::table('cliente_convenio')->updateOrInsert(['cliente_id' => $clienteId, 'convenio_id' => $convenioId], ['deleted_at' => null, 'updated_at' => now(), 'created_at' => now()]);
        }
    }
}
