<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Modules\SeguimientoEgresado\Models\UsuarioInterno;
use App\Modules\SeguimientoEgresado\Services\Accesos\SesionAcceso;
use App\Modules\SeguimientoEgresado\Support\Accesos\Perfil;
use App\Modules\SeguimientoEgresado\Support\Accesos\RutaInicial;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class AuthenticatedSessionController extends Controller
{
    /**
     * Display the login view.
     */
    public function create(): View
    {
        return view('auth.login');
    }

    /**
     * Handle an incoming authentication request.
     */
    public function store(LoginRequest $request): RedirectResponse
    {
        $request->authenticate();

        $usuario = Auth::user();
        $perfil = Perfil::desdeRolesDelPortal($usuario->getRoleNames()->all());

        if ($perfil === null) {
            Auth::guard('web')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            throw ValidationException::withMessages([
                'email' => 'Acceso restringido: Esta cuenta no cuenta con permisos de Secretaría Académica ni de Dirección para ingresar al CRM de Seguimiento al Egresado.',
            ]);
        }

        // Asegurar que el usuario interno exista en el esquema del módulo para autoría
        UsuarioInterno::delPortal($usuario, $perfil);

        $request->session()->regenerate();

        $rutaDestino = RutaInicial::para($perfil);

        return redirect()->intended(route($rutaDestino, absolute: false));
    }

    /**
     * Destroy an authenticated session.
     */
    public function destroy(Request $request): RedirectResponse
    {
        SesionAcceso::desde($request)->cerrar();

        Auth::guard('web')->logout();

        $request->session()->invalidate();

        $request->session()->regenerateToken();

        return redirect()->route('login');
    }
}
