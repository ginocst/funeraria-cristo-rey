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

    /** @return BelongsTo<Cliente, $this> */
    public function cliente(): BelongsTo
    {
        return $this->belongsTo(Cliente::class);
    }

    /** @return BelongsTo<Difunto, $this> */
    public function difunto(): BelongsTo
    {
        return $this->belongsTo(Difunto::class);
    }

    /** @return BelongsTo<Sucursal, $this> */
    public function sucursal(): BelongsTo
    {
        return $this->belongsTo(Sucursal::class);
    }

    /** @return BelongsTo<Convenio, $this> */
    public function convenio(): BelongsTo
    {
        return $this->belongsTo(Convenio::class);
    }

    /** @return HasMany<Actividad, $this> */
    public function actividades(): HasMany
    {
        return $this->hasMany(Actividad::class)->orderBy('inicio');
    }

    /** @return HasMany<Solicitud, $this> */
    public function solicitudes(): HasMany
    {
        return $this->hasMany(Solicitud::class)->orderByDesc('id');
    }

    /** @return HasMany<Archivo, $this> */
    public function documentos(): HasMany
    {
        return $this->hasMany(Archivo::class);
    }
}
