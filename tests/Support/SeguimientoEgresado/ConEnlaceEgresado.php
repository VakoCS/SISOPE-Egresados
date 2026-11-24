<?php

namespace Tests\Support\SeguimientoEgresado;

use App\Modules\SeguimientoEgresado\Services\Accesos\EnlaceInvitacion;
use Illuminate\Support\Facades\DB;
use Illuminate\Testing\TestResponse;

/** Las pruebas HTTP privadas usan el acceso real, no identidades inventadas sin invitación. */
trait ConEnlaceEgresado
{
    protected function entrarPorInvitacion(int $invitacion): TestResponse
    {
        $token = bin2hex(random_bytes(32));
        DB::table('seguimiento_egresado.invitacion_egresado')->where('id', $invitacion)
            ->update(['token_hash' => EnlaceInvitacion::hashDe($token)]);

        return $this->get(route('seguimiento-egresado.acceso.enlace', $token));
    }
}
