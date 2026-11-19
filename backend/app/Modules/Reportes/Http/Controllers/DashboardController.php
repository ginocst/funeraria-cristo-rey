<?php

namespace App\Modules\Reportes\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Reportes\Application\Indicadores;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** Panel de inicio: los montos globales solo se entregan a quien tiene reportes financieros */
class DashboardController extends Controller
{
    public function __invoke(Request $request, Indicadores $indicadores): JsonResponse
    {
        return response()->json($indicadores->panel($request->user()->tienePermiso('reportes.financieros')));
    }
}
