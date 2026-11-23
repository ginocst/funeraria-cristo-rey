<?php

namespace App\Modules\Inventario\Infrastructure\Models;

use App\Shared\Infrastructure\ModeloBase;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

/** Foto adicional de un modelo (vista abierta, lateral u otra) */
class ProductoFoto extends ModeloBase
{
    protected $table = 'producto_fotos';

    protected $fillable = ['producto_id', 'ruta', 'vista', 'orden'];

    protected $appends = ['url'];

    public function producto(): BelongsTo
    {
        return $this->belongsTo(Producto::class);
    }

    public function getUrlAttribute(): string
    {
        return Storage::disk('catalogo')->url($this->ruta);
    }
}
