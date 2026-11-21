<?php

namespace Tests\Feature\SeguimientoEgresado\Indicadores;

use App\Modules\SeguimientoEgresado\Database\Seeders\DatosPruebaSeeder;
use App\Modules\SeguimientoEgresado\Models\Cohorte;
use App\Modules\SeguimientoEgresado\Models\Egresado;
use App\Modules\SeguimientoEgresado\Models\InteraccionCrm;
use App\Modules\SeguimientoEgresado\Services\Indicadores\ConsultasIndicadoresRepository;
use Tests\Support\SeguimientoEgresado\UsaBaseDeDatos;
use Tests\TestCase;

class ConsultasIndicadoresRepositoryTest extends TestCase
{
    use UsaBaseDeDatos;

    private ConsultasIndicadoresRepository $repo;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatosPruebaSeeder::class);
        $this->repo = app(ConsultasIndicadoresRepository::class);
    }

    public function test_obtener_ingresantes_y_egresados_por_cohorte_devuelve_datos_completos(): void
    {
        $cohortes = $this->repo->obtenerIngresantesYEgresadosPorCohorte();

        $this->assertNotEmpty($cohortes);
        $primera = $cohortes->first();

        $this->assertObjectHasProperty('id_cohorte', $primera);
        $this->assertObjectHasProperty('codigo_promocion', $primera);
        $this->assertObjectHasProperty('total_egresados', $primera);
        $this->assertObjectHasProperty('total_ingresantes', $primera);
        $this->assertObjectHasProperty('porcentaje', $primera);
        $this->assertGreaterThan(0, $primera->total_ingresantes);
    }

    public function test_obtener_cobertura_contacto_calcula_metricas_correctas(): void
    {
        $cobertura = $this->repo->obtenerCoberturaContacto();

        $this->assertArrayHasKey('total_egresados', $cobertura);
        $this->assertArrayHasKey('contactables', $cobertura);
        $this->assertArrayHasKey('sin_contacto', $cobertura);
        $this->assertArrayHasKey('porcentaje_cobertura', $cobertura);

        $this->assertGreaterThan(0, $cobertura['total_egresados']);
        $this->assertSame($cobertura['total_egresados'], $cobertura['contactables'] + $cobertura['sin_contacto']);
    }

    public function test_obtener_distribucion_por_segmento(): void
    {
        $segmentos = $this->repo->obtenerEgresadosPorSegmento();

        $this->assertIsArray($segmentos);
        $this->assertNotEmpty($segmentos);
        $totalSegmentos = array_sum($segmentos);
        $totalEgresados = Egresado::count();

        $this->assertSame($totalEgresados, $totalSegmentos);
    }

    public function test_obtener_egresados_que_requieren_seguimiento(): void
    {
        $egresado = Egresado::first();
        $usuarioId = \Illuminate\Support\Facades\DB::table('seguimiento_egresado.usuario_interno')->value('id');

        InteraccionCrm::create([
            'id_egresado' => $egresado->id,
            'id_usuario_registro' => $usuarioId,
            'canal' => 'CORREO',
            'estado' => 'REQUIERE_SEGUIMIENTO',
            'resumen' => 'Solicita llamada de seguimiento sobre proceso de titulación.',
            'proximo_contacto_en' => now()->addDays(2),
            'fecha_interaccion' => now(),
        ]);

        $requieren = $this->repo->obtenerEgresadosRequierenSeguimiento();

        $this->assertNotEmpty($requieren);
        $encontrado = $requieren->firstWhere('id_egresado', $egresado->id);
        $this->assertNotNull($encontrado);
        $this->assertSame('REQUIERE_SEGUIMIENTO', $encontrado->estado_interaccion);
    }

    public function test_obtener_resumen_crm_general(): void
    {
        $resumen = $this->repo->obtenerResumenCrmGeneral();

        $this->assertArrayHasKey('total_egresados', $resumen);
        $this->assertArrayHasKey('egresados_contactables', $resumen);
        $this->assertArrayHasKey('porcentaje_contactabilidad', $resumen);
        $this->assertArrayHasKey('campanias_activas', $resumen);
        $this->assertArrayHasKey('total_interacciones', $resumen);
        $this->assertArrayHasKey('egresados_requieren_seguimiento', $resumen);
        $this->assertArrayHasKey('tasa_respuesta_encuestas', $resumen);
        $this->assertArrayHasKey('distribucion_segmentos', $resumen);
        $this->assertArrayHasKey('canales_contacto_preferidos', $resumen);
    }
}
