<?php

namespace App\Modules\Flota\Infrastructure\Models;

use App\Modules\Identidad\Infrastructure\Models\Usuario;
use App\Shared\Infrastructure\ModeloBase;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Ausencia extends ModeloBase
{
    protected $table = 'ausencias';

    protected $fillable = ['usuario_id', 'tipo', 'desde', 'hasta', 'motivo'];

    protected $casts = ['desde' => 'date:Y-m-d', 'hasta' => 'date:Y-m-d'];

    /** @return BelongsTo<Usuario, $this> */
    public function usuario(): BelongsTo
    {
        return $this->belongsTo(Usuario::class);
    }
}
