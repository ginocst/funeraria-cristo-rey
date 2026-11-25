<?php

namespace App\Modules\Flota\Infrastructure\Models;

use App\Modules\Identidad\Infrastructure\Models\Usuario;
use App\Modules\Solicitudes\Infrastructure\Models\Solicitud;
use App\Shared\Infrastructure\ModeloBase;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/** Vehículo y equipo comprometidos por una solicitud en un intervalo */
class Asignacion extends ModeloBase
{
    protected $table = 'asignaciones';

    protected $fillable = ['solicitud_id', 'vehiculo_id', 'externo', 'inicio', 'fin', 'activa'];

    protected $hidden = ['intervalo', 'created_by', 'updated_by', 'deleted_at'];

    protected $casts = ['inicio' => 'datetime', 'fin' => 'datetime', 'activa' => 'boolean', 'externo' => 'boolean'];

    /** @return BelongsTo<Vehiculo, $this> */
    public function vehiculo(): BelongsTo
    {
        return $this->belongsTo(Vehiculo::class);
    }

    /** @return BelongsTo<Solicitud, $this> */
    public function solicitud(): BelongsTo
    {
        return $this->belongsTo(Solicitud::class);
    }

    /** @return BelongsToMany<Usuario, $this> */
    public function equipo(): BelongsToMany
    {
        return $this->belongsToMany(Usuario::class, 'asignacion_usuario')->withPivot('funcion')->wherePivotNull('deleted_at');
    }

    public function incluye(int $usuarioId): bool
    {
        return $this->equipo()->where('usuarios.id', $usuarioId)->exists();
    }
}
