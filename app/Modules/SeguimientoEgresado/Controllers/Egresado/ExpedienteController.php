<?php

namespace App\Modules\SeguimientoEgresado\Controllers\Egresado;

use App\Modules\SeguimientoEgresado\Services\Egresado\HistorialService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

final class ExpedienteController extends EgresadoController
{
    public function __construct(private readonly HistorialService $historial) {}

    public function inicio(Request $request): View
    {
        $identidad = $this->identidad($request);

        $egresado = DB::table('seguimiento_egresado.egresado as e')
            ->join('seguimiento_egresado.cohorte as c', 'c.id', '=', 'e.id_cohorte')
            ->where('e.id', $identidad->egresadoId)
            ->first(['e.nombres', 'e.apellido_paterno', 'e.apellido_materno', 'e.codigo_matricula', 'e.programa', 'e.periodo_egreso', 'c.codigo_promocion']);

        // Solo la invitación con la que entró: define qué puede hacer ahora (actualizar contacto o responder la encuesta).
        $invitacion = DB::table('seguimiento_egresado.invitacion_egresado as i')
            ->leftJoin('seguimiento_egresado.campania_seguimiento as c', 'c.id', '=', 'i.id_campania')
            ->where('i.id', $identidad->invitacionId)->where('i.id_egresado', $identidad->egresadoId)
            ->first(['i.proposito', 'i.estado', 'i.expira_en', 'c.nombre as campania', 'c.periodo', 'c.estado as estado_campania']);

        $contacto = collect($this->historial->contactos($identidad->egresadoId))->firstWhere('vigente_hasta', null);

        // Encuestas por responder de campañas abiertas: se ofrecen todas, entre con el enlace que entre.
        $encuestas = DB::table('seguimiento_egresado.invitacion_egresado as i')
            ->join('seguimiento_egresado.campania_seguimiento as c', 'c.id', '=', 'i.id_campania')
            ->where('i.id_egresado', $identidad->egresadoId)
            ->where('i.proposito', 'SEGUIMIENTO')->where('i.estado', 'PENDIENTE')
            ->where('i.expira_en', '>', now()->format('Y-m-d H:i:sP'))->where('c.estado', 'ABIERTA')
            ->where('c.fecha_inicio', '<=', now('America/Lima')->toDateString())
            ->where('c.fecha_fin', '>=', now('America/Lima')->toDateString())
            ->orderBy('c.fecha_fin')->orderBy('i.id')
            ->get(['i.id', 'i.expira_en', 'c.nombre as campania', 'c.periodo']);

        return view('modules.seguimiento-egresado.egresado.inicio', [
            'egresado' => $egresado,
            'invitacion' => $invitacion,
            'encuestas' => $encuestas,
            'contacto' => $contacto,
            'campanias' => $this->historial->campanias($identidad->egresadoId),
        ]);
    }
}
