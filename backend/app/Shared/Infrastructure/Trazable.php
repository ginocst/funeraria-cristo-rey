<?php

namespace App\Shared\Infrastructure;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

/** Completa created_by y updated_by con el usuario autenticado */
trait Trazable
{
    public static function bootTrazable(): void
    {
        static::creating(function (Model $modelo) {
            $id = Auth::id();
            $modelo->setAttribute('created_by', $modelo->getAttribute('created_by') ?? $id);
            $modelo->setAttribute('updated_by', $modelo->getAttribute('updated_by') ?? $id);
        });

        static::updating(function (Model $modelo) {
            $modelo->setAttribute('updated_by', Auth::id() ?? $modelo->getAttribute('updated_by'));
        });

        static::deleting(function (Model $modelo) {
            if (method_exists($modelo, 'trashed') && Auth::id()) {
                $modelo->setAttribute('updated_by', Auth::id());
                $modelo->saveQuietly();
            }
        });
    }
}
