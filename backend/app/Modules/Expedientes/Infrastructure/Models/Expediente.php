<?php

namespace App\Modules\Expedientes\Infrastructure\Models;

use App\Modules\ClientesConvenios\Infrastructure\Models\Cliente;
use App\Modules\ClientesConvenios\Infrastructure\Models\Convenio;
use App\Modules\Identidad\Infrastructure\Models\Sucursal;
use App\Modules\Solicitudes\Infrastructure\Models\Solicitud;
use App\Shared\Infrastructure\ModeloBase;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Carpeta digital del cliente: raíz de agregado del servicio */
class Expediente extends ModeloBase
{
    protected $table = 'expedientes';

    protected $fillable = ['cliente_id', 'sucursal_id', 'difunto_id', 'convenio_id', 'estado', 'observaciones'];

    public function cliente(): BelongsTo
    {
        return $this->belongsTo(Cliente::class);
    }

    public function difunto(): BelongsTo
    {
        return $this->belongsTo(Difunto::class);
    }

    public function sucursal(): BelongsTo
    {
        return $this->belongsTo(Sucursal::class);
    }

    public function convenio(): BelongsTo
    {
        return $this->belongsTo(Convenio::class);
    }

    public function actividades(): HasMany
    {
        return $this->hasMany(Actividad::class)->orderBy('inicio');
    }

    public function solicitudes(): HasMany
    {
        return $this->hasMany(Solicitud::class)->orderByDesc('id');
    }

    public function documentos(): HasMany
    {
        return $this->hasMany(Archivo::class);
    }
}
