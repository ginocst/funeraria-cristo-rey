<?php

namespace App\Modules\Solicitudes\Infrastructure\Models;

use App\Modules\Expedientes\Infrastructure\Models\Expediente;
use App\Modules\Flota\Infrastructure\Models\Asignacion;
use App\Modules\Identidad\Infrastructure\Models\Sucursal;
use App\Modules\Identidad\Infrastructure\Models\Usuario;
use App\Modules\Inventario\Infrastructure\Models\Producto;
use App\Modules\Solicitudes\Domain\EstadoSolicitud;
use App\Shared\Infrastructure\ModeloBase;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Facades\DB;

class Solicitud extends ModeloBase
{
    protected $table = 'solicitudes';

    protected $fillable = [
        'expediente_id',
        'sucursal_id',
        'estado',
        'valor_servicio',
        'descuento',
        'velatorio_inicio',
        'lugar_velatorio',
        'traslado_at',
        'lugar_sepultura',
        'sepultura_detalle',
        'mensaje',
        'comentario',
        'evaluada_por',
        'evaluada_at',
        'motivo_cancelacion'
    ];

    protected $appends = ['total'];

    protected function casts(): array
    {
        return [
            'estado' => EstadoSolicitud::class,
            'valor_servicio' => 'integer',
            'descuento' => 'integer',
            'velatorio_inicio' => 'datetime',
            'traslado_at' => 'datetime',
            'evaluada_at' => 'datetime',
        ];
    }

    public function expediente(): BelongsTo
    {
        return $this->belongsTo(Expediente::class);
    }

    public function sucursal(): BelongsTo
    {
        return $this->belongsTo(Sucursal::class);
    }

    public function evaluador(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'evaluada_por');
    }

    public function productos(): BelongsToMany
    {
        return $this->belongsToMany(Producto::class, 'producto_solicitud')->withPivot(['cantidad', 'precio_unitario'])->wherePivotNull('deleted_at');
    }

    public function asignacion(): HasOne
    {
        return $this->hasOne(Asignacion::class);
    }

    /** @return array<int, int> producto_id => cantidad */
    public function itemsProductos(): array
    {
        return $this->productos->mapWithKeys(fn($p) => [$p->id => (int) $p->pivot->cantidad])->all();
    }

    public function getTotalAttribute(): int
    {
        $productos = $this->relationLoaded('productos')
            ? $this->productos->sum(fn($p) => $p->pivot->cantidad * $p->pivot->precio_unitario)
            : (int) $this->productos()->sum(DB::raw('producto_solicitud.cantidad * producto_solicitud.precio_unitario'));

        return (int) $this->valor_servicio - (int) $this->descuento + (int) $productos;
    }
}
