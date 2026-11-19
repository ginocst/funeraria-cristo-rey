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

    protected function casts(): array
    {
        return ['monto' => 'integer', 'fecha' => 'datetime'];
    }

    public function medioPago(): BelongsTo
    {
        return $this->belongsTo(MedioPago::class);
    }

    public function comprobante(): HasOne
    {
        return $this->hasOne(Comprobante::class);
    }

    public function anulacion(): HasOne
    {
        return $this->hasOne(Pago::class, 'anula_pago_id');
    }

    public function registradoPor(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'created_by');
    }
}
