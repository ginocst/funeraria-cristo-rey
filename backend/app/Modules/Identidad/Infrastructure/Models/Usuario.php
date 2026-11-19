<?php

namespace App\Modules\Identidad\Infrastructure\Models;

use App\Shared\Infrastructure\Trazable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Support\Facades\DB;
use Laravel\Fortify\TwoFactorAuthenticatable;

class Usuario extends Authenticatable
{
    use SoftDeletes;
    use Trazable;
    use TwoFactorAuthenticatable;

    protected $table = 'usuarios';

    protected $fillable = ['sucursal_id', 'nombre', 'rut', 'email', 'telefono', 'password', 'activo', 'ultimo_acceso_at'];

    protected $hidden = ['password', 'remember_token', 'two_factor_secret', 'two_factor_recovery_codes', 'created_by', 'updated_by', 'deleted_at'];

    /** @var array<int, array{nombre:string, temporal:bool, vigencia_hasta:?string}>|null */
    private ?array $rolesVigentesCache = null;

    protected function casts(): array
    {
        return [
            'password' => 'hashed',
            'activo' => 'boolean',
            'two_factor_confirmed_at' => 'datetime',
            'ultimo_acceso_at' => 'datetime',
        ];
    }

    public function sucursal(): BelongsTo
    {
        return $this->belongsTo(Sucursal::class);
    }

    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Rol::class, 'rol_usuario')
            ->withPivot(['vigencia_hasta', 'deleted_at'])
            ->wherePivotNull('deleted_at');
    }

    /** Roles vigentes: los permanentes y las suplencias cuya fecha de término no ha pasado */
    public function rolesVigentes(): array
    {
        if ($this->rolesVigentesCache !== null) {
            return $this->rolesVigentesCache;
        }

        return $this->rolesVigentesCache = DB::table('rol_usuario')
            ->join('roles', 'roles.id', '=', 'rol_usuario.rol_id')
            ->where('rol_usuario.usuario_id', $this->id)
            ->whereNull('rol_usuario.deleted_at')
            ->where(fn($q) => $q->whereNull('rol_usuario.vigencia_hasta')->orWhere('rol_usuario.vigencia_hasta', '>', now()))
            ->get(['roles.nombre', 'rol_usuario.vigencia_hasta'])
            ->map(fn($r) => ['nombre' => $r->nombre, 'temporal' => $r->vigencia_hasta !== null, 'vigencia_hasta' => $r->vigencia_hasta])
            ->all();
    }

    public function olvidarRoles(): void
    {
        $this->rolesVigentesCache = null;
    }

    public function rolPrincipal(): ?string
    {
        foreach ($this->rolesVigentes() as $r) {
            if (! $r['temporal']) {
                return $r['nombre'];
            }
        }

        return null;
    }

    public function tieneRol(string ...$roles): bool
    {
        return collect($this->rolesVigentes())->pluck('nombre')->intersect($roles)->isNotEmpty();
    }

    public function suplenciaHasta(): ?string
    {
        foreach ($this->rolesVigentes() as $r) {
            if ($r['temporal'] && $r['nombre'] === 'Administrador') {
                return $r['vigencia_hasta'];
            }
        }

        return null;
    }

    /** Matriz de permisos (config/funeraria.php). La suplencia no entrega los permisos excluidos */
    public function tienePermiso(string $permiso): bool
    {
        $roles = config('funeraria.permisos', [])[$permiso] ?? [];
        $excluidos = config('funeraria.excluidos_de_suplencia', []);

        foreach ($this->rolesVigentes() as $r) {
            if (in_array($r['nombre'], $roles, true) && ! ($r['temporal'] && in_array($permiso, $excluidos, true))) {
                return true;
            }
        }

        return false;
    }

    public function permisos(): array
    {
        return collect(array_keys(config('funeraria.permisos', [])))
            ->filter(fn($p) => $this->tienePermiso($p))
            ->values()
            ->all();
    }

    public function requiereMfa(): bool
    {
        return (bool) config('funeraria.mfa_obligatorio')
            && $this->tieneRol(...config('funeraria.roles_con_mfa', []));
    }

    public function mfaActivo(): bool
    {
        return $this->two_factor_confirmed_at !== null;
    }

    /** Asigna el rol base (reemplaza el anterior, con borrado lógico del vínculo) */
    public function asignarRolBase(string $nombreRol): void
    {
        $rolId = Rol::where('nombre', $nombreRol)->value('id');
        DB::table('rol_usuario')->where('usuario_id', $this->id)->whereNull('vigencia_hasta')
            ->where('rol_id', '!=', $rolId)->update(['deleted_at' => now(), 'updated_at' => now()]);
        DB::table('rol_usuario')->updateOrInsert(
            ['rol_id' => $rolId, 'usuario_id' => $this->id],
            ['vigencia_hasta' => null, 'deleted_at' => null, 'updated_at' => now(), 'created_at' => now(), 'created_by' => auth()->id()],
        );
        $this->olvidarRoles();
    }
}
