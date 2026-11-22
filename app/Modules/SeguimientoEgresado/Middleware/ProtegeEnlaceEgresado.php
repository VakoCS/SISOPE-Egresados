<?php

namespace App\Modules\SeguimientoEgresado\Middleware;

use App\Modules\SeguimientoEgresado\Services\Accesos\SesionAcceso;
use Carbon\CarbonImmutable;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

/** El enlace abre una sesión, pero PostgreSQL sigue siendo la autoridad en cada petición privada. */
final class ProtegeEnlaceEgresado
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->routeIs('seguimiento-egresado.egresado.*')) {
            $sesion = SesionAcceso::desde($request);
            $identidad = $sesion->egresado();
            $invitacion = $identidad === null ? null : DB::table('seguimiento_egresado.invitacion_egresado')
                ->where('id', $identidad->invitacionId)->where('id_egresado', $identidad->egresadoId)->first();

            if ($invitacion === null || ! in_array($invitacion->estado, ['PENDIENTE', 'COMPLETADA'], true)
                || CarbonImmutable::parse($invitacion->expira_en)->lessThanOrEqualTo(CarbonImmutable::now())
                || ! $sesion->correspondeAlEnlace($invitacion)) {
                $sesion->cerrar();
                $respuesta = $request->expectsJson()
                    ? response()->json(['message' => 'Abre un enlace personal vigente para continuar.'], 401)
                    : redirect()->route('seguimiento-egresado.acceso');

                return $this->privada($respuesta);
            }
        }

        return $this->privada($next($request));
    }

    private function privada(Response $respuesta): Response
    {
        $respuesta->headers->set('Cache-Control', 'no-store, private');
        $respuesta->headers->set('Referrer-Policy', 'no-referrer');
        $respuesta->headers->set('X-Robots-Tag', 'noindex, nofollow');

        return $respuesta;
    }
}
