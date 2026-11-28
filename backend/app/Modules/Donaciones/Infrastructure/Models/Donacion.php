<?php

namespace App\Modules\Donaciones\Infrastructure\Models;

use App\Shared\Infrastructure\ModeloBase;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** @property int $organizacion_id */
class Donacion extends ModeloBase
{
    protected $table = 'donaciones';

    protected $fillable = ['organizacion_id', 'fecha', 'monto', 'descripcion'];

    protected $casts = ['fecha' => 'date:Y-m-d', 'monto' => 'integer'];

    /** @return BelongsTo<Organizacion, $this> */
    public function organizacion(): BelongsTo
    {
        return $this->belongsTo(Organizacion::class)->withTrashed();
    }
}
