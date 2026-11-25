<?php

namespace App\Modules\Inventario\Infrastructure\Models;

use App\Shared\Infrastructure\ModeloBase;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Proveedor extends ModeloBase
{
    protected $table = 'proveedores';

    protected $fillable = ['nombre', 'rut', 'rubro', 'contacto', 'telefono', 'email', 'direccion', 'notas', 'activo'];

    protected $casts = ['activo' => 'boolean'];

    /** @return HasMany<Producto, $this> */
    public function productos(): HasMany
    {
        return $this->hasMany(Producto::class);
    }
}
