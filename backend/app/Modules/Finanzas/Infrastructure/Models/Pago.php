<?php

namespace App\Modules\Finanzas\Infrastructure\Models;

use App\Modules\Identidad\Infrastructure\Models\Usuario;
use App\Shared\Infrastructure\Trazable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

/** Libro de pagos inmutable: la base rechaza su edición o borrado */
class Pago extends Model
{
    use Trazable;

    protected $table = 'pagos';

    protected $fillable = ['expediente_id', 'cuota_id', 'medio_pago_id', 'tipo', 'monto', 'referencia', 'anula_pago_id', 'motivo_anulacion', 'fecha'];

    protected $hidden = ['updated_by', 'deleted_at'];

    protected $casts = ['monto' => 'integer', 'fecha' => 'datetime'];

    /** @return BelongsTo<MedioPago, $this> */
    public function medioPago(): BelongsTo
    {
        return $this->belongsTo(MedioPago::class);
    }

    /** @return HasOne<Comprobante, $this> */
    public function comprobante(): HasOne
    {
        return $this->hasOne(Comprobante::class);
    }

    /** @return HasOne<Pago, $this> */
    public function anulacion(): HasOne
    {
        return $this->hasOne(Pago::class, 'anula_pago_id');
    }

    /** @return BelongsTo<Usuario, $this> */
    public function registradoPor(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'created_by');
    }
}
