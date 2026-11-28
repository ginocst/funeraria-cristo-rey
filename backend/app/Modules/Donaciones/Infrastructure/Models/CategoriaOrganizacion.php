<?php

namespace App\Modules\Donaciones\Infrastructure\Models;

use App\Shared\Infrastructure\ModeloBase;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CategoriaOrganizacion extends ModeloBase
{
    protected $table = 'categorias_organizacion';

    protected $fillable = ['nombre', 'descripcion'];

    /** @return HasMany<Organizacion, $this> */
    public function organizaciones(): HasMany
    {
        return $this->hasMany(Organizacion::class, 'categoria_id');
    }
}
