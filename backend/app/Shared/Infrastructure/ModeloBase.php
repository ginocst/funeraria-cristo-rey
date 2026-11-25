<?php

namespace App\Shared\Infrastructure;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Modelo con borrado lógico (deleted_at) y trazabilidad (created_by, updated_by)
 *
 * @property int|null $created_by
 * @property int|null $updated_by
 */
abstract class ModeloBase extends Model
{
    use SoftDeletes;
    use Trazable;

    protected $hidden = ['created_by', 'updated_by', 'deleted_at'];
}
