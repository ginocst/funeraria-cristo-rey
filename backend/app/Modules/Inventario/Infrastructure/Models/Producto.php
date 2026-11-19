<?php

namespace App\Modules\Inventario\Infrastructure\Models;

use App\Modules\Inventario\Domain\Stock;
use App\Shared\Infrastructure\ModeloBase;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class Producto extends ModeloBase
{
    protected $table = 'productos';

    protected $fillable = [
        'proveedor_id',
        'codigo',
        'nombre',
        'tipo',
        'material',
        'color',
        'medidas',
        'descripcion',
        'precio',
        'umbral_minimo',
        'infantil',
        'publico',
        'atributos'
    ];

    protected $appends = ['foto_url', 'critico'];

    protected function casts(): array
    {
        return [
            'precio' => 'integer',
            'stock' => 'integer',
            'umbral_minimo' => 'integer',
            'infantil' => 'boolean',
            'publico' => 'boolean',
            'atributos' => 'array'
        ];
    }

    public function proveedor(): BelongsTo
    {
        return $this->belongsTo(Proveedor::class);
    }

    public function getFotoUrlAttribute(): ?string
    {
        return $this->foto_ruta ? Storage::disk('catalogo')->url($this->foto_ruta) : null;
    }

    /** `null` si la consulta no trajo `stock` o `umbral_minimo`: así no se informa un estado falso */
    public function getCriticoAttribute(): ?bool
    {
        if (! array_key_exists('stock', $this->attributes) || ! array_key_exists('umbral_minimo', $this->attributes)) {
            return null;
        }

        return Stock::esCritico((int) $this->stock, (int) $this->umbral_minimo);
    }
}
