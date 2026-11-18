<?php

namespace App\Modules\Identidad\Infrastructure\Models;

use App\Shared\Infrastructure\Trazable;
use Illuminate\Database\Eloquent\Model;

class Parametro extends Model
{
    use Trazable;

    protected $table = 'parametros';

    protected $primaryKey = 'clave';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = ['clave', 'valor', 'descripcion'];
}
