<?php

namespace App\Modules\Notificaciones\Infrastructure\Models;

use Illuminate\Database\Eloquent\Model;

/** Evento del outbox transaccional */
class Notificacion extends Model
{
    protected $table = 'notificaciones';

    protected $fillable = ['evento', 'payload', 'estado', 'reintentos', 'error', 'procesado_at', 'created_by'];

    protected $casts = ['payload' => 'array', 'procesado_at' => 'datetime'];
}
