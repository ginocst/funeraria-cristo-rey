<?php

namespace App\Modules\Auditoria\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Auditoria\Infrastructure\Models\Auditoria;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** Consulta de la bitácora (solo Administrador) */
class AuditoriaController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $q = Auditoria::query()
            ->leftJoin('usuarios', 'usuarios.id', '=', 'auditorias.usuario_id')
            ->select('auditorias.*', 'usuarios.nombre as usuario')
            ->orderByDesc('auditorias.id');

        foreach (['accion', 'entidad'] as $campo) {
            if ($request->filled($campo)) {
                $q->where("auditorias.$campo", $request->query($campo));
            }
        }
        if ($request->filled('usuario_id')) {
            $q->where('auditorias.usuario_id', $request->integer('usuario_id'));
        }
        if ($request->filled('desde')) {
            $q->where('auditorias.fecha_utc', '>=', $request->date('desde'));
        }
        if ($request->filled('hasta')) {
            $q->where('auditorias.fecha_utc', '<', $request->date('hasta')->addDay());
        }

        return response()->json($q->paginate(min((int) $request->query('por_pagina', 50), 200)));
    }
}
