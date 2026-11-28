<?php

namespace App\Modules\Solicitudes\Infrastructure\Models;

use App\Shared\Infrastructure\ModeloBase;
use Illuminate\Database\Eloquent\Casts\Attribute;

/**
 * @property string $nombre
 * @property string $direccion
 * @property string $estado
 * @property-read string $lugar Texto con que figura como lugar del velatorio
 */
class SalaVelatoria extends ModeloBase
{
    public const ESTADOS = ['disponible' => 'disponible', 'mantenimiento' => 'en mantenimiento', 'fuera_de_servicio' => 'fuera de servicio'];

    protected $table = 'salas_velatorias';

    protected $fillable = ['nombre', 'direccion', 'capacidad', 'estado', 'observaciones'];

    protected $casts = ['capacidad' => 'integer'];

    protected $attributes = ['estado' => 'disponible'];

    protected $appends = ['lugar'];

    /** Incluye la dirección para que el equipo (y Google Maps) sepan dónde ir */
    protected function lugar(): Attribute
    {
        return Attribute::get(fn() => "{$this->nombre}, {$this->direccion}");
    }
}
