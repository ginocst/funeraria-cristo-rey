<?php

namespace App\Modules\Donaciones\Application;

use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/** Donaciones de un mes, con el total por organización y por categoría, con exportable PDF */
class ResumenDonaciones
{
    public function mensual(string $mes, ?int $organizacionId = null): array
    {
        $inicio = CarbonImmutable::createFromFormat('!Y-m', $mes);

        $donaciones = DB::table('donaciones as d')->join('organizaciones as o', 'o.id', '=', 'd.organizacion_id')
            ->join('categorias_organizacion as c', 'c.id', '=', 'o.categoria_id')
            ->whereNull('d.deleted_at')
            ->where('d.fecha', '>=', $inicio->toDateString())->where('d.fecha', '<', $inicio->addMonth()->toDateString())
            ->when($organizacionId, fn ($q) => $q->where('d.organizacion_id', $organizacionId))
            ->orderBy('d.fecha')->orderBy('d.id')
            ->get(['d.id', 'd.fecha', 'o.id as organizacion_id', 'o.nombre as organizacion', 'c.nombre as categoria', 'd.descripcion', 'd.monto']);

        return [
            'mes' => $mes,
            'donaciones' => $donaciones,
            'totales' => ['cantidad' => $donaciones->count(), 'monto' => (int) $donaciones->sum('monto')],
            'por_organizacion' => $this->agrupar($donaciones, 'organizacion'),
            'por_categoria' => $this->agrupar($donaciones, 'categoria'),
        ];
    }

    /** Cantidad y monto por nombre, de mayor a menor monto */
    private function agrupar(Collection $donaciones, string $campo): array
    {
        return $donaciones->groupBy($campo)
            ->map(fn (Collection $grupo, string $nombre) => ['nombre' => $nombre, 'cantidad' => $grupo->count(), 'monto' => (int) $grupo->sum('monto')])
            ->sortBy([['monto', 'desc'], ['nombre', 'asc']])->values()->all();
    }
}
