<?php

namespace App\Modules\Donaciones\Infrastructure\Models;

use App\Shared\Infrastructure\ModeloBase;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $categoria_id
 * @property string $nombre
 */
class Organizacion extends ModeloBase
{
    protected $table = 'organizaciones';

    protected $fillable = ['categoria_id', 'nombre', 'contacto', 'telefono', 'email', 'notas', 'activo'];

    protected $casts = ['activo' => 'boolean'];

    /** @return BelongsTo<CategoriaOrganizacion, $this> */
    public function categoria(): BelongsTo
    {
        return $this->belongsTo(CategoriaOrganizacion::class, 'categoria_id')->withTrashed();
    }

    /** @return HasMany<Donacion, $this> */
    public function donaciones(): HasMany
    {
        return $this->hasMany(Donacion::class);
    }
}
