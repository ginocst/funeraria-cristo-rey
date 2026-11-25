<?php

namespace App\Modules\Inventario\Infrastructure\Models;

use App\Modules\Inventario\Domain\Stock;
use App\Shared\Infrastructure\ModeloBase;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;

/**
 * @property-read object{cantidad: int, precio_unitario: int} $pivot Pivot producto_solicitud (solo al cargar vía Solicitud::productos)
 */
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
        'largo_cm',
        'ancho_cm',
        'alto_cm',
        'descripcion',
        'precio',
        'umbral_minimo',
        'infantil',
        'publico',
        'atributos'
    ];

    protected $appends = ['foto_url', 'critico'];

    protected $casts = [
        'precio' => 'integer',
        'stock' => 'integer',
        'umbral_minimo' => 'integer',
        'largo_cm' => 'integer',
        'ancho_cm' => 'integer',
        'alto_cm' => 'integer',
        'infantil' => 'boolean',
        'publico' => 'boolean',
        'atributos' => 'array'
    ];

    /** @return BelongsTo<Proveedor, $this> */
    public function proveedor(): BelongsTo
    {
        return $this->belongsTo(Proveedor::class);
    }

    /** @return HasMany<ProductoFoto, $this> */
    public function fotos(): HasMany
    {
        return $this->hasMany(ProductoFoto::class)->orderBy('orden')->orderBy('id');
    }

    /** Medidas para mostrar: «largo × ancho × alto cm» si están cargadas; si no, el texto libre */
    public function medidasTexto(): ?string
    {
        $numeros = array_filter([$this->largo_cm, $this->ancho_cm, $this->alto_cm], fn($v) => $v !== null);

        return $numeros ? implode(' × ', $numeros) . ' cm' : $this->medidas;
    }

    /** Fotos para el carrusel: la principal (vista superior) y luego las adicionales, en orden */
    public function galeria(): array
    {
        $galeria = [];
        if ($this->foto_url) {
            $galeria[] = ['id' => null, 'url' => $this->foto_url, 'vista' => 'superior'];
        }
        foreach ($this->fotos as $foto) {
            $galeria[] = ['id' => $foto->id, 'url' => $foto->url, 'vista' => $foto->vista];
        }

        return $galeria;
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
