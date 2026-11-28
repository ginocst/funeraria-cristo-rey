<?php

namespace App\Modules\Flota\Infrastructure\Models;

use App\Modules\Identidad\Infrastructure\Models\Usuario;
use App\Shared\Infrastructure\ModeloBase;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $usuario_id
 * @property Carbon $desde
 * @property int $dias
 * @property string $motivo
 * @property string $estado
 * @property int|null $dias_aprobados
 * @property-read string $hasta Último día libre (Y-m-d), calculado
 */
class SolicitudDiasLibres extends ModeloBase
{
    protected $table = 'solicitudes_dias_libres';

    protected $fillable = ['usuario_id', 'desde', 'dias', 'motivo', 'estado', 'dias_aprobados', 'motivo_rechazo', 'resuelta_por', 'resuelta_at', 'ausencia_id'];

    protected $casts = ['desde' => 'date:Y-m-d', 'dias' => 'integer', 'dias_aprobados' => 'integer', 'resuelta_at' => 'datetime'];

    protected $appends = ['hasta'];

    /** Último día libre: según los días aprobados o, mientras no se resuelva, los pedidos */
    protected function hasta(): Attribute
    {
        return Attribute::get(fn () => $this->desde->copy()->addDays(($this->dias_aprobados ?? $this->dias) - 1)->toDateString());
    }

    /** @return BelongsTo<Usuario, $this> */
    public function usuario(): BelongsTo
    {
        return $this->belongsTo(Usuario::class)->withTrashed();
    }

    /** @return BelongsTo<Usuario, $this> */
    public function evaluador(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'resuelta_por')->withTrashed();
    }
}
