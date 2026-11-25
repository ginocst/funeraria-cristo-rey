<?php

namespace App\Modules\Finanzas\Infrastructure\Models;

use App\Shared\Infrastructure\Trazable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Comprobante extends Model
{
    use Trazable;

    protected $table = 'comprobantes';

    protected $fillable = ['pago_id'];

    protected $casts = ['emitido_at' => 'datetime'];

    /** @return BelongsTo<Pago, $this> */
    public function pago(): BelongsTo
    {
        return $this->belongsTo(Pago::class);
    }
}
