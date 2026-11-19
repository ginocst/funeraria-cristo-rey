<?php

namespace App\Modules\Notificaciones\Infrastructure\Models;

use Illuminate\Database\Eloquent\Model;

/** Aviso que ve un usuario dentro del sistema */
class Aviso extends Model
{
    protected $table = 'avisos';

    protected $fillable = ['usuario_id', 'notificacion_id', 'tipo', 'titulo', 'mensaje', 'enlace', 'leido_at'];

    protected $hidden = ['created_by', 'updated_by', 'deleted_at'];

    protected function casts(): array
    {
        return ['leido_at' => 'datetime'];
    }
}
