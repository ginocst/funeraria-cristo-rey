<?php

use App\Modules\Expedientes\Http\Controllers\DocumentoController;
use Illuminate\Support\Facades\Route;

Route::get('/', fn () => response()->json(['servicio' => 'API Funeraria Cristo Rey', 'estado' => 'ok']));

// Solo para el disco local de desarrollo: descarga con firma temporal (en producción se usa S3 prefirmado).
Route::get('/archivos/{id}', [DocumentoController::class, 'descargar'])->name('documentos.descargar')->middleware('signed');