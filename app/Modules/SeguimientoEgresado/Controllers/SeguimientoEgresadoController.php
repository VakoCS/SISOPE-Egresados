<?php

namespace App\Modules\SeguimientoEgresado\Controllers;

use App\Modules\SeguimientoEgresado\Requests\SeguimientoEgresadoRequest;
use App\Modules\SeguimientoEgresado\Services\Accesos\SesionAcceso;
use App\Modules\SeguimientoEgresado\Services\AvisosDeInicio;
use App\Modules\SeguimientoEgresado\Services\SeguimientoEgresadoService;
use App\Shared\Controllers\ModuleController;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

final class SeguimientoEgresadoController extends ModuleController
{
    protected const MODULE = 'Seguimiento al Egresado';

    protected const SCHEMA = 'seguimiento_egresado';

    protected const VIEW = 'modules.seguimiento-egresado.inicio';

    /** Portada: exige autenticación institucional y muestra el estado según permisos. */
    public function index(): View
    {
        $usuario = auth()->user();
        if ($usuario) {
            $perfil = \App\Modules\SeguimientoEgresado\Support\Accesos\Perfil::desdeRolesDelPortal($usuario->getRoleNames());
            if (! $perfil) {
                auth()->logout();
                abort(403, 'Acceso denegado: este panel requiere rol de Secretaría Académica o Dirección de Escuela.');
            }
        }

        $personal = (bool) SesionAcceso::desde(request())->actual()?->puede('campanias.consultar');

        return view(static::VIEW, [
            'avisos' => $personal ? app(AvisosDeInicio::class)->obtener() : null,
            'puedeAdministrar' => $personal && (bool) SesionAcceso::desde(request())->actual()?->puede('campanias.administrar'),
        ]);
    }

    public function store(SeguimientoEgresadoRequest $request, SeguimientoEgresadoService $service): RedirectResponse
    {
        $service->handle($request->validated());

        return redirect()
            ->route('seguimiento-egresado.index')
            ->with('status', 'Registro procesado.');
    }
}
