<?php

namespace App\Shared\Infrastructure;

use Illuminate\Support\Facades\Auth;

/** Completa created_by y updated_by con el usuario autenticado */
trait Trazable
{
    public static function bootTrazable(): void
    {
        static::creating(function ($modelo) {
            $id = Auth::id();
            $modelo->created_by ??= $id;
            $modelo->updated_by ??= $id;
        });

        static::updating(function ($modelo) {
            $modelo->updated_by = Auth::id() ?? $modelo->updated_by;
        });

        static::deleting(function ($modelo) {
            if (method_exists($modelo, 'trashed') && Auth::id()) {
                $modelo->updated_by = Auth::id();
                $modelo->saveQuietly();
            }
        });
    }
}