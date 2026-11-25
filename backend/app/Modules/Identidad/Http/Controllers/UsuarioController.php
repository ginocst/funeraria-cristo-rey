<?php

namespace App\Modules\Identidad\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Identidad\Domain\ContrasenaSegura;
use App\Modules\Identidad\Infrastructure\Models\Rol;
use App\Modules\Identidad\Infrastructure\Models\Usuario;
use App\Shared\Contratos\IAuditoria;
use App\Shared\Domain\ReglaNegocioException;
use App\Shared\Domain\Rut;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

/** Mantenedor de usuarios, roles y suplencias (solo Administrador) */
class UsuarioController extends Controller
{
    public function __construct(private IAuditoria $auditoria) {}

    public function index(Request $request): JsonResponse
    {
        $q = Usuario::with('sucursal:id,nombre')->orderBy('nombre');
        if ($texto = trim((string) $request->query('q'))) {
            $q->where(fn ($w) => $w->where('nombre', 'ilike', "%$texto%")->orWhere('email', 'ilike', "%$texto%"));
        }
        if ($request->filled('rol')) {
            $q->whereExists(fn ($s) => $s->from('rol_usuario')->join('roles', 'roles.id', '=', 'rol_usuario.rol_id')
                ->whereColumn('rol_usuario.usuario_id', 'usuarios.id')->whereNull('rol_usuario.deleted_at')
                ->whereNull('rol_usuario.vigencia_hasta')->where('roles.nombre', $request->query('rol')));
        }

        return response()->json($q->get()->map(fn (Usuario $u) => $this->presentar($u)));
    }

    public function show(int $id): JsonResponse
    {
        return response()->json($this->presentar(Usuario::with('sucursal:id,nombre')->findOrFail($id)));
    }

    public function store(Request $request): JsonResponse
    {
        $datos = $request->validate($this->reglas($request));
        $usuario = DB::transaction(function () use ($datos) {
            $u = Usuario::create(collect($datos)->except('rol')->all());
            $u->asignarRolBase($datos['rol']);

            return $u;
        });
        $this->auditoria->registrar('crear', 'usuarios', $usuario->id, null, ['nombre' => $usuario->nombre, 'email' => $usuario->email, 'rol' => $datos['rol']]);

        return response()->json($this->presentar($usuario->fresh('sucursal')), 201);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $usuario = Usuario::findOrFail($id);
        $datos = $request->validate($this->reglas($request, $usuario));
        if (empty($datos['password'])) {
            unset($datos['password']);
        }
        if ($usuario->id === $request->user()->id && (($datos['activo'] ?? true) === false || ($datos['rol'] ?? 'Administrador') !== 'Administrador')) {
            throw new ReglaNegocioException('No puede desactivar su propia cuenta ni quitarse el rol de administrador.');
        }
        $antes = $this->presentar($usuario);
        DB::transaction(function () use ($usuario, $datos) {
            $usuario->update(collect($datos)->except('rol')->all());
            $usuario->asignarRolBase($datos['rol']);
        });
        $this->auditoria->registrar('editar', 'usuarios', $usuario->id, $antes, $this->presentar($usuario->fresh('sucursal')));

        return response()->json($this->presentar($usuario->fresh('sucursal')));
    }

    /** Las cuentas no se borran físicamente: se desactivan y quedan con borrado lógico */
    public function destroy(Request $request, int $id): JsonResponse
    {
        $usuario = Usuario::findOrFail($id);
        if ($usuario->id === $request->user()->id) {
            throw new ReglaNegocioException('No puede eliminar su propia cuenta.');
        }
        $usuario->update(['activo' => false]);
        $usuario->delete();
        $this->auditoria->registrar('eliminar', 'usuarios', $usuario->id, ['nombre' => $usuario->nombre]);

        return response()->json(null, 204);
    }

    /** Suplencia: entrega temporalmente al coordinador los permisos del administrador */
    public function otorgarSuplencia(Request $request, int $id): JsonResponse
    {
        $datos = $request->validate(['hasta' => ['required', 'date', 'after:now', 'before:+90 days']]);
        $usuario = Usuario::findOrFail($id);
        if ($usuario->rolPrincipal() !== 'Coordinador') {
            throw new ReglaNegocioException('La suplencia solo se otorga a un coordinador.');
        }
        $rolId = Rol::where('nombre', 'Administrador')->value('id');
        DB::table('rol_usuario')->updateOrInsert(
            ['rol_id' => $rolId, 'usuario_id' => $usuario->id],
            ['vigencia_hasta' => $datos['hasta'], 'deleted_at' => null, 'updated_at' => now(), 'created_at' => now(), 'created_by' => $request->user()->id],
        );
        $this->auditoria->registrar('otorgar_suplencia', 'usuarios', $usuario->id, null, ['hasta' => $datos['hasta']]);

        return response()->json($this->presentar($usuario->fresh('sucursal')));
    }

    public function revocarSuplencia(Request $request, int $id): JsonResponse
    {
        $usuario = Usuario::findOrFail($id);
        $rolId = Rol::where('nombre', 'Administrador')->value('id');
        DB::table('rol_usuario')->where(['rol_id' => $rolId, 'usuario_id' => $usuario->id])
            ->whereNotNull('vigencia_hasta')->update(['deleted_at' => now(), 'updated_at' => now(), 'updated_by' => $request->user()->id]);
        $this->auditoria->registrar('revocar_suplencia', 'usuarios', $usuario->id);

        return response()->json($this->presentar($usuario->fresh('sucursal')));
    }

    /** Restablece el MFA de un usuario que perdió su dispositivo */
    public function restablecerMfa(int $id): JsonResponse
    {
        $usuario = Usuario::findOrFail($id);
        $usuario->forceFill(['two_factor_secret' => null, 'two_factor_recovery_codes' => null, 'two_factor_confirmed_at' => null])->save();
        $this->auditoria->registrar('restablecer_mfa', 'usuarios', $usuario->id);

        return response()->json($this->presentar($usuario->fresh('sucursal')));
    }

    private function reglas(Request $request, ?Usuario $usuario = null): array
    {
        // Se normaliza antes de validar para que la unicidad compare 12345678-9 con 12345678-9
        $request->merge(['rut' => Rut::normalizar((string) $request->input('rut'))]);

        return [
            'nombre' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email:rfc', 'max:160', Rule::unique('usuarios', 'email')->ignore($usuario?->id)->whereNull('deleted_at')],
            'rut' => [
                'required',
                'string',
                'max:12',
                fn ($a, $v, $f) => Rut::formatoValido($v) ? null : $f('El RUT debe tener el formato 12345678-9.'),
                Rule::unique('usuarios', 'rut')->ignore($usuario?->id)->whereNull('deleted_at'),
            ],
            'telefono' => ['nullable', 'string', 'max:30'],
            'sucursal_id' => ['required', 'integer', Rule::exists('sucursales', 'id')->whereNull('deleted_at')],
            'rol' => ['required', Rule::in(['Administrador', 'Coordinador', 'Operario'])],
            'activo' => ['sometimes', 'boolean'],
            'password' => [$usuario ? 'nullable' : 'required', 'string', Password::min(12)->letters()->numbers(), new ContrasenaSegura],
        ];
    }

    private function presentar(Usuario $u): array
    {
        return [
            'id' => $u->id,
            'nombre' => $u->nombre,
            'email' => $u->email,
            'rut' => $u->rut,
            'telefono' => $u->telefono,
            'sucursal_id' => $u->sucursal_id,
            'sucursal' => $u->sucursal?->nombre,
            'rol' => $u->rolPrincipal(),
            'suplencia_hasta' => $u->suplenciaHasta(),
            'activo' => $u->activo,
            'mfa_activo' => $u->mfaActivo(),
            'ultimo_acceso_at' => $u->ultimo_acceso_at,
        ];
    }
}
