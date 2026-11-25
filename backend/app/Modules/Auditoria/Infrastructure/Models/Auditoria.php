<?php

namespace App\Modules\Auditoria\Infrastructure\Models;

use Illuminate\Database\Eloquent\Model;

/** Bitácora inmutable: la base de datos rechaza su edición o borrado */
class Auditoria extends Model
{
    protected $table = 'auditorias';

    public $timestamps = false;

    protected $guarded = ['id'];

    protected $casts = ['antes' => 'array', 'despues' => 'array', 'fecha_utc' => 'datetime'];
}
