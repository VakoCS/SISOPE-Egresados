<?php

namespace App\Modules\SeguimientoEgresado\Services\Indicadores;

use App\Modules\SeguimientoEgresado\Models\Cohorte;
use App\Modules\SeguimientoEgresado\Models\Egresado;
use App\Modules\SeguimientoEgresado\Models\InteraccionCrm;
use App\Modules\SeguimientoEgresado\Models\MejoraCurricular;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Repositorio de consultas analíticas y datos agregados para el cálculo de indicadores (I1–I6)
 * y métricas operativas del CRM Seguimiento al Egresado.
 *
 * Diseñado para abastecer el módulo de indicadores a cargo de Jim Becerra y las campañas CRM de Diego.
 */
class ConsultasIndicadoresRepository
{
    private const ESTADOS_ACREDITADOS = ['EGRESADO', 'BACHILLER', 'TITULADO'];

    /**
     * I1: Numerador (egresados acreditados) y Denominador (total de ingresantes de la cohorte).
     *
     * @return Collection<int, object>
     */
    public function obtenerIngresantesYEgresadosPorCohorte(?int $cohorteId = null): Collection
    {
        if (DB::getDriverName() === 'pgsql' && DB::table('information_schema.views')->where('table_schema', 'seguimiento_egresado')->where('table_name', 'vw_i1_cohorte_actual')->exists()) {
            $query = DB::table('seguimiento_egresado.vw_i1_cohorte_actual');
            if ($cohorteId) {
                $query->where('id_cohorte', $cohorteId);
            }

            return $query->orderBy('anio_ingreso', 'desc')->get();
        }

        $query = Cohorte::query();
        if ($cohorteId) {
            $query->where('id', $cohorteId);
        }

        return $query->orderByDesc('anio_ingreso')->get()->map(function ($c) {
            $egresadosAcreditados = DB::table('seguimiento_egresado.historial_estado_academico as h')
                ->join('seguimiento_egresado.cat_estado_academico as ea', 'ea.id', '=', 'h.id_estado_academico')
                ->join('seguimiento_egresado.egresado as e', 'e.id', '=', 'h.id_egresado')
                ->where('e.id_cohorte', $c->id)
                ->whereNull('h.vigente_hasta')
                ->whereIn('ea.codigo', self::ESTADOS_ACREDITADOS)
                ->count();

            $ingresantes = (int) $c->total_ingresantes;
            $pct = $ingresantes > 0 ? round(($egresadosAcreditados / $ingresantes) * 100, 2) : 0.0;

            return (object) [
                'id_cohorte' => $c->id,
                'codigo_promocion' => $c->codigo_promocion,
                'anio_ingreso' => $c->anio_ingreso,
                'total_egresados' => $egresadosAcreditados,
                'total_ingresantes' => $ingresantes,
                'porcentaje' => $pct,
            ];
        });
    }

    /**
     * Cobertura de contacto / contactabilidad:
     * Porcentaje de egresados que cuentan con al menos un medio de contacto vigente (correo o teléfono).
     *
     * @return array{total_egresados: int, contactables: int, sin_contacto: int, porcentaje_cobertura: ?float}
     */
    public function obtenerCoberturaContacto(?int $cohorteId = null): array
    {
        $baseEgresados = DB::table('seguimiento_egresado.egresado as e')
            ->when($cohorteId, fn ($q) => $q->where('e.id_cohorte', $cohorteId));

        $totalEgresados = (clone $baseEgresados)->count();

        $contactables = (clone $baseEgresados)
            ->whereExists(function ($sub) {
                $sub->select(DB::raw(1))
                    ->from('seguimiento_egresado.contacto_egresado as ce')
                    ->whereColumn('ce.id_egresado', 'e.id')
                    ->whereNull('ce.vigente_hasta')
                    ->where(function ($w) {
                        $w->whereNotNull('ce.correo_personal')
                            ->orWhereNotNull('ce.celular_whatsapp')
                            ->orWhereNotNull('ce.telefono_fijo');
                    });
            })
            ->count();

        $sinContacto = $totalEgresados - $contactables;
        $pct = $totalEgresados > 0 ? round(($contactables / $totalEgresados) * 100, 2) : null;

        return [
            'total_egresados' => $totalEgresados,
            'contactables' => $contactables,
            'sin_contacto' => $sinContacto,
            'porcentaje_cobertura' => $pct,
        ];
    }

    /**
     * Tasa de respuesta a invitaciones de encuestas por campaña o global.
     * Numerador: invitaciones con medición laboral registrada.
     * Denominador: invitaciones enviadas.
     *
     * @return array{invitados: int, respondieron: int, pendientes: int, tasa_respuesta: ?float}
     */
    public function obtenerTasaRespuestaInvitaciones(?int $campaniaId = null): array
    {
        $query = DB::table('seguimiento_egresado.invitacion_egresado as i')
            ->join('seguimiento_egresado.campania_seguimiento as c', 'c.id', '=', 'i.id_campania')
            ->leftJoin('seguimiento_egresado.medicion_laboral as m', 'm.id_invitacion', '=', 'i.id')
            ->where('i.proposito', 'SEGUIMIENTO')
            ->when($campaniaId, fn ($q) => $q->where('i.id_campania', $campaniaId));

        $total = (clone $query)->count();
        $respondieron = (clone $query)->whereNotNull('m.id')->count();
        $pendientes = $total - $respondieron;
        $tasa = $total > 0 ? round(($respondieron / $total) * 100, 2) : null;

        return [
            'invitados' => $total,
            'respondieron' => $respondieron,
            'pendientes' => $pendientes,
            'tasa_respuesta' => $tasa,
        ];
    }

    /**
     * Distribución de egresados por segmento CRM.
     *
     * @return array<string, int>
     */
    public function obtenerEgresadosPorSegmento(): array
    {
        return DB::table('seguimiento_egresado.egresado')
            ->select('segmento', DB::raw('count(*) as total'))
            ->groupBy('segmento')
            ->pluck('total', 'segmento')
            ->toArray();
    }

    /**
     * Egresados que requieren seguimiento o contacto pendiente.
     * Incluye egresados con interacciones en estado REQUIERE_SEGUIMIENTO o NO_CONTACTADO,
     * o con fecha de proximo_contacto_en vencida o próxima en los próximos 7 días.
     *
     * @return Collection<int, object>
     */
    public function obtenerEgresadosRequierenSeguimiento(): Collection
    {
        $haceSieteDias = now()->subDays(7)->toDateTimeString();
        $enSieteDias = now()->addDays(7)->toDateTimeString();

        return DB::table('seguimiento_egresado.interaccion_crm as i')
            ->join('seguimiento_egresado.egresado as e', 'e.id', '=', 'i.id_egresado')
            ->leftJoin('seguimiento_egresado.cohorte as c', 'c.id', '=', 'e.id_cohorte')
            ->where(function ($w) use ($enSieteDias) {
                $w->whereIn('i.estado', ['REQUIERE_SEGUIMIENTO', 'NO_CONTACTADO'])
                    ->orWhere(function ($sub) use ($enSieteDias) {
                        $sub->whereNotNull('i.proximo_contacto_en')
                            ->where('i.proximo_contacto_en', '<=', $enSieteDias);
                    });
            })
            ->select([
                'e.id as id_egresado',
                'e.codigo_matricula',
                'e.nombres',
                'e.apellido_paterno',
                'e.apellido_materno',
                'e.segmento',
                'c.codigo_promocion',
                'i.id as id_interaccion',
                'i.canal',
                'i.estado as estado_interaccion',
                'i.resumen',
                'i.proximo_contacto_en',
                'i.fecha_interaccion',
            ])
            ->orderBy('i.proximo_contacto_en', 'asc')
            ->orderByDesc('i.fecha_interaccion')
            ->get();
    }

    /**
     * I6: Cantidad absoluta de mejoras curriculares implementadas con evidencia y origen del seguimiento.
     *
     * @return array<int, int> Año => Total mejoras
     */
    public function obtenerConteoMejorasImplementadas(?int $anio = null): array
    {
        $query = DB::table('seguimiento_egresado.mejora_curricular')
            ->where('estado', 'IMPLEMENTADA')
            ->where('origen_seguimiento', true)
            ->whereNotNull('fecha_implementacion');

        if ($anio) {
            $query->whereYear('fecha_implementacion', $anio);
        }

        $registros = $query->get(['fecha_implementacion']);

        return $registros
            ->countBy(fn ($r) => (int) substr((string) $r->fecha_implementacion, 0, 4))
            ->toArray();
    }

    /**
     * Resumen general consolidado de KPIs para CRM y Seguimiento.
     *
     * @return array<string, mixed>
     */
    public function obtenerResumenCrmGeneral(): array
    {
        $cobertura = $this->obtenerCoberturaContacto();
        $tasaRespuesta = $this->obtenerTasaRespuestaInvitaciones();
        $segmentos = $this->obtenerEgresadosPorSegmento();
        $totalInteracciones = DB::table('seguimiento_egresado.interaccion_crm')->count();
        $campaniasActivas = DB::table('seguimiento_egresado.campania_seguimiento')->where('estado', 'ABIERTA')->count();
        $requierenSeguimiento = DB::table('seguimiento_egresado.interaccion_crm')
            ->where('estado', 'REQUIERE_SEGUIMIENTO')
            ->distinct('id_egresado')
            ->count('id_egresado');

        $canalesPreferidos = DB::table('seguimiento_egresado.contacto_egresado')
            ->whereNull('vigente_hasta')
            ->whereNotNull('canal_preferido')
            ->select('canal_preferido', DB::raw('count(*) as total'))
            ->groupBy('canal_preferido')
            ->pluck('total', 'canal_preferido')
            ->toArray();

        return [
            'total_egresados' => $cobertura['total_egresados'],
            'egresados_contactables' => $cobertura['contactables'],
            'porcentaje_contactabilidad' => $cobertura['porcentaje_cobertura'],
            'campanias_activas' => $campaniasActivas,
            'total_interacciones' => $totalInteracciones,
            'egresados_requieren_seguimiento' => $requierenSeguimiento,
            'tasa_respuesta_encuestas' => $tasaRespuesta['tasa_respuesta'],
            'distribucion_segmentos' => $segmentos,
            'canales_contacto_preferidos' => $canalesPreferidos,
        ];
    }
}
