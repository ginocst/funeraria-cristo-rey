<?php

namespace App\Modules\ClientesConvenios\Infrastructure\Models;

use App\Modules\Expedientes\Infrastructure\Models\Expediente;
use App\Shared\Infrastructure\ModeloBase;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Cliente extends ModeloBase
{
    protected $table = 'clientes';

    protected $fillable = ['rut', 'nombre', 'telefono', 'email', 'direccion', 'comuna'];

    public function convenios(): BelongsToMany
    {
        return $this->belongsToMany(Convenio::class, 'cliente_convenio')->withPivot('numero_socio')->wherePivotNull('deleted_at');
    }

    public function expedientes(): HasMany
    {
        return $this->hasMany(Expediente::class);
    }
}
