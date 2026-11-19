<?php

namespace App\Modules\ClientesConvenios\Infrastructure\Models;

use App\Shared\Infrastructure\ModeloBase;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Convenio extends ModeloBase
{
    protected $table = 'convenios';

    protected $fillable = ['nombre', 'institucion', 'tipo', 'descuento_pct', 'vigencia_desde', 'vigencia_hasta', 'descripcion', 'contacto', 'activo'];

    protected $appends = ['vigente'];

    protected function casts(): array
    {
        return ['vigencia_desde' => 'date:Y-m-d', 'vigencia_hasta' => 'date:Y-m-d', 'activo' => 'boolean', 'descuento_pct' => 'float'];
    }

    public function clientes(): BelongsToMany
    {
        return $this->belongsToMany(Cliente::class, 'cliente_convenio')->wherePivotNull('deleted_at');
    }

    public function getVigenteAttribute(): bool
    {
        $hoy = now(config('funeraria.zona_horaria_local'))->toDateString();

        return $this->activo
            && $this->vigencia_desde->toDateString() <= $hoy
            && ($this->vigencia_hasta === null || $this->vigencia_hasta->toDateString() >= $hoy);
    }
}
