<?php

namespace App\Modules\Expedientes\Infrastructure\Models;

use App\Shared\Infrastructure\ModeloBase;

/** Metadatos de un documento guardado en el bucket privado de archivos */
class Archivo extends ModeloBase
{
    protected $table = 'archivos';

    protected $fillable = ['expediente_id', 'tipo', 'disco', 'clave', 'nombre_original', 'mime', 'tamano', 'sha256'];

    protected $hidden = ['disco', 'clave', 'created_by', 'updated_by', 'deleted_at'];
}
