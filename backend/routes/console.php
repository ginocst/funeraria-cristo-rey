<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schedule;

// Alertas de cuotas atrasadas. onOneServer evita duplicados si hay varias instancias.
Schedule::command('cuotas:revisar')->dailyAt('07:00')->timezone('America/Santiago')->onOneServer();

// Limpieza de claves de idempotencia de más de 7 días.
Schedule::call(fn () => DB::table('claves_idempotencia')->where('created_at', '<', now()->subDays(7))->delete())
    ->name('limpiar-claves-idempotencia')->daily()->onOneServer();