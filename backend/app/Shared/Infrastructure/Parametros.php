<?php

namespace App\Shared\Infrastructure;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/** Lectura de los parámetros configurables del negocio */
class Parametros
{
    public function todos(): array
    {
        return Cache::remember('parametros', 300, function () {
            $base = collect(config('funeraria.parametros'))->map(fn ($p) => $p['valor'])->all();
            $db = DB::table('parametros')->whereNull('deleted_at')->pluck('valor', 'clave')->all();

            return array_merge($base, $db);
        });
    }

    public function entero(string $clave): int
    {
        return (int) ($this->todos()[$clave] ?? 0);
    }

    public function olvidar(): void
    {
        Cache::forget('parametros');
    }
}
