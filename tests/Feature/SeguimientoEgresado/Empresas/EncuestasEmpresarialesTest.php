<?php

namespace Tests\Feature\SeguimientoEgresado\Empresas;

use App\Modules\SeguimientoEgresado\Services\Empresas\CuestionarioEmpresa;
use App\Modules\SeguimientoEgresado\Services\Empresas\EnlaceEmpresa;
use App\Modules\SeguimientoEgresado\Services\Empresas\EnlaceNoGenerable;
use App\Modules\SeguimientoEgresado\Services\Empresas\GestionDeEmpresas;
use App\Modules\SeguimientoEgresado\Services\Indicadores\CalculadoraIndicadores;
use App\Modules\SeguimientoEgresado\Services\Indicadores\FiltroIndicadores;
use Illuminate\Support\Facades\DB;
use Tests\Support\SeguimientoEgresado\FabricaEmpresasIndicadores;
use Tests\Support\SeguimientoEgresado\UsaBaseDeDatos;
use Tests\TestCase;

class EncuestasEmpresarialesTest extends TestCase
{
    use FabricaEmpresasIndicadores, UsaBaseDeDatos;

    private function empresa(): int
    {
        return app(GestionDeEmpresas::class)->guardar([
            'ruc' => '20512345678', 'razon_social' => 'Empleador de prueba', 'correo' => 'empresa@example.test',
        ]);
    }

    private function evaluable(int $empresa): int
    {
        $egresado = $this->egresado($this->cohorte(), cambios: ['numero_documento' => '79112233']);
        $this->responder($this->invitacionSeguimiento($this->campania(['tipo' => 'EGRESADOS']), $egresado), cambios: ['autoriza_contacto_empresa' => true]);
        app(GestionDeEmpresas::class)->vincular($empresa, $egresado);

        return $egresado;
    }

    private function enlace(string $contexto = 'MERCADO', array $datos = []): array
    {
        return app(EnlaceEmpresa::class)->generar($datos + ['id_campania' => $this->campania(), 'contexto' => $contexto]);
    }

    private function mercado(array $cambios = []): array
    {
        return $cambios + $this->datosEncuesta() + [
            'sectores_contratacion' => ['SOFTWARE_TI', 'EDUCACION'],
            'perfiles_requeridos' => ['DESARROLLO', 'DATOS_IA'],
            'demanda_profesionales' => 'UNO_CINCO',
            'satisfaccion_formacion' => 'MUY_SATISFECHO',
        ];
    }

    private function desempeno(array $cambios = []): array
    {
        return $cambios + $this->datosEncuesta(['satisfaccion' => 'SATISFECHO']) + array_fill_keys(array_keys(CuestionarioEmpresa::DIMENSIONES), 4);
    }

    private function enviar(array $enlace, array $datos)
    {
        return $this->post(route('seguimiento-egresado.encuesta-empresa.responder', $enlace['token']), $datos);
    }

    public function test_tipo_a_publico_sin_egresado_guarda_datos_estructurados_y_no_alimenta_i5(): void
    {
        $enlace = $this->enlace();
        $this->get(route('seguimiento-egresado.encuesta-empresa', $enlace['token']))
            ->assertOk()->assertSee('perfiles_requeridos[]', false)->assertSee('satisfaccion_formacion', false)
            ->assertDontSee('name="desempeno_laboral"', false)->assertDontSee('sg-navigation', false);
        $this->enviar($enlace, $this->mercado())->assertSessionHasNoErrors()->assertRedirect(route('seguimiento-egresado.encuesta-empresa.gracias'));
        $r = DB::table('seguimiento_egresado.respuesta_empresa')->first();
        $this->assertNull($r->id_nivel_satisfaccion);
        $this->assertSame('UNO_CINCO', $r->demanda_profesionales);
        $this->assertSame($this->catalogo('cat_nivel_satisfaccion', 'MUY_SATISFECHO'), (int) $r->id_satisfaccion_formacion);
        $this->assertDatabaseCount('seguimiento_egresado.respuesta_empresa_sector', 2);
        $this->assertDatabaseCount('seguimiento_egresado.respuesta_empresa_perfil', 2);
        $this->assertNull(app(CalculadoraIndicadores::class)->i5(new FiltroIndicadores)->valor);
    }

    public function test_tipo_b_identifica_al_vinculado_guarda_cinco_dimensiones_y_alimenta_i5(): void
    {
        $empresa = $this->empresa();
        $egresado = $this->evaluable($empresa);
        $enlace = $this->enlace('DESEMPENO', ['id_empresa' => $empresa, 'id_egresado' => $egresado]);
        $this->get(route('seguimiento-egresado.encuesta-empresa', $enlace['token']))
            ->assertOk()->assertSee('Persona')->assertSee('name="comunicacion"', false)
            ->assertDontSee('name="satisfaccion_formacion"', false)->assertDontSee('79112233');
        $this->enviar($enlace, $this->desempeno())->assertSessionHasNoErrors()->assertRedirect();
        $this->assertDatabaseHas('seguimiento_egresado.respuesta_empresa', array_fill_keys(array_keys(CuestionarioEmpresa::DIMENSIONES), 4));
        $i5 = app(CalculadoraIndicadores::class)->i5(new FiltroIndicadores);
        $this->assertSame(100.0, $i5->valor);
        $this->assertSame(1, $i5->denominador);
        $this->assertDatabaseHas('seguimiento_egresado.invitacion_empresa', ['id' => $enlace['id'], 'id_empresa' => $empresa, 'id_egresado' => $egresado, 'estado' => 'COMPLETADA']);
        $this->assertNotNull(DB::table('seguimiento_egresado.invitacion_empresa')->where('id', $enlace['id'])->value('respondido_en'));
        $this->entrarComo('DIRECTOR');
        $this->get(route('seguimiento-egresado.empresa.detalle', $empresa))->assertOk()->assertSee('4 / 5')->assertDontSee('Generar enlace personalizado');
    }

    public function test_cuestionario_nuevo_exige_campos_de_mercado_en_servidor(): void
    {
        $enlace = $this->enlace();
        $this->enviar($enlace, $this->datosEncuesta())->assertSessionHasErrors(['sectores_contratacion', 'perfiles_requeridos', 'demanda_profesionales', 'satisfaccion_formacion']);
        $this->enviar($enlace, $this->mercado(['sectores_contratacion' => ['INVENTADO'], 'perfiles_requeridos' => ['INVENTADO'], 'demanda_profesionales' => '1000']))
            ->assertSessionHasErrors(['sectores_contratacion.0', 'perfiles_requeridos.0', 'demanda_profesionales']);
        $this->enviar($enlace, $this->mercado(['perfiles_requeridos' => ['DESARROLLO', 'DESARROLLO']]))->assertSessionHasErrors('perfiles_requeridos.0');
        $this->assertDatabaseCount('seguimiento_egresado.respuesta_empresa', 0);
    }

    public function test_evaluacion_exige_dimensiones_y_rechaza_valores_fuera_de_escala(): void
    {
        $empresa = $this->empresa();
        $enlace = $this->enlace('DESEMPENO', ['id_empresa' => $empresa, 'id_egresado' => $this->evaluable($empresa)]);
        $this->enviar($enlace, $this->datosEncuesta(['satisfaccion' => 'SATISFECHO']))->assertSessionHasErrors(array_keys(CuestionarioEmpresa::DIMENSIONES));
        $this->enviar($enlace, $this->desempeno(['comunicacion' => 6, 'trabajo_equipo' => 0]))->assertSessionHasErrors(['comunicacion', 'trabajo_equipo']);
        $this->assertDatabaseCount('seguimiento_egresado.respuesta_empresa', 0);
    }

    public function test_manipular_identidad_tipo_o_version_no_guarda_respuestas(): void
    {
        $enlace = $this->enlace();
        $this->enviar($enlace, $this->mercado(['id_empresa' => 999, 'id_egresado' => 999, 'contexto' => 'DESEMPENO', 'version_formulario' => 1]))
            ->assertSessionHasErrors(['id_empresa', 'id_egresado', 'contexto', 'version_formulario']);
        $this->enviar($enlace, $this->mercado(['satisfaccion' => 'SATISFECHO', 'comunicacion' => 5]))->assertSessionHasErrors(['satisfaccion', 'comunicacion']);
        $this->assertDatabaseCount('seguimiento_egresado.respuesta_empresa', 0);
    }

    public function test_token_alterado_vencido_renovado_y_reutilizado_no_admiten_envios(): void
    {
        $enlace = $this->enlace();
        $alterado = $enlace;
        $alterado['token'][0] = $alterado['token'][0] === 'a' ? 'b' : 'a';
        $this->enviar($alterado, $this->mercado())->assertNotFound();
        DB::table('seguimiento_egresado.invitacion_empresa')->where('id', $enlace['id'])->update(['expira_en' => now()->subMinute()->format('Y-m-d H:i:sP')]);
        $this->enviar($enlace, $this->mercado())->assertStatus(410);
        $renovado = app(EnlaceEmpresa::class)->renovar($enlace['id']);
        $this->enviar($enlace, $this->mercado())->assertNotFound();
        $this->enviar($renovado, $this->mercado())->assertSessionHasNoErrors()->assertRedirect();
        $this->enviar($renovado, $this->mercado())->assertStatus(409);
        $this->assertDatabaseCount('seguimiento_egresado.respuesta_empresa', 1);
    }

    public function test_enlaces_anteriores_conservan_su_cuestionario_y_pueden_responder(): void
    {
        $viejo = $this->invitacionEmpresa($this->campania());
        $this->get(route('seguimiento-egresado.encuesta-empresa', $viejo['token']))->assertOk()->assertDontSee('name="satisfaccion_formacion"', false);
        $this->enviar($viejo, $this->datosEncuesta())->assertSessionHasNoErrors()->assertRedirect();
    }

    public function test_no_genera_evaluacion_para_un_egresado_de_otra_empresa(): void
    {
        $empresa = $this->empresa();
        $egresado = $this->evaluable($empresa);
        app(GestionDeEmpresas::class)->desvincular($empresa, $egresado);
        $this->expectException(EnlaceNoGenerable::class);
        $this->enlace('DESEMPENO', ['id_empresa' => $empresa, 'id_egresado' => $egresado]);
    }

    public function test_ficha_genera_enlace_personalizado_y_muestra_respuesta_de_mercado(): void
    {
        $empresa = $this->empresa();
        $this->entrarComo();
        $this->post(route('seguimiento-egresado.empresas.enlaces'), ['id_empresa' => $empresa, 'id_campania' => $this->campania(), 'contexto' => 'MERCADO'])
            ->assertSessionHasNoErrors()->assertSessionHas('enlaces_empresa');
        $url = session('enlaces_empresa')[0]['url'];
        $token = basename(parse_url($url, PHP_URL_PATH));
        $this->assertDatabaseHas('seguimiento_egresado.invitacion_empresa', ['id_empresa' => $empresa, 'organizacion' => 'Empleador de prueba', 'version_formulario' => 2]);
        $this->enviar(['token' => $token], $this->mercado())->assertSessionHasNoErrors();
        $this->get(route('seguimiento-egresado.empresa.detalle', $empresa))->assertOk()
            ->assertSee('De 1 a 5')->assertSee('Desarrollo de software')->assertSee('Muy satisfecho');
    }

    public function test_no_genera_ni_acepta_en_campania_futura_aunque_diga_abierta(): void
    {
        $campania = $this->campania(['fecha_inicio' => now()->addDays(2)->toDateString()]);
        $invitacion = $this->invitacionEmpresa($campania);
        $this->enviar($invitacion, $this->datosEncuesta())->assertStatus(410);
        $this->expectException(EnlaceNoGenerable::class);
        $this->enlace(datos: ['id_campania' => $campania]);
    }

    public function test_desempeno_no_admite_duplicado_ni_preguntas_exclusivas_de_mercado(): void
    {
        $empresa = $this->empresa();
        $enlace = $this->enlace('DESEMPENO', ['id_empresa' => $empresa, 'id_egresado' => $this->evaluable($empresa)]);
        $this->enviar($enlace, $this->desempeno(['satisfaccion_formacion' => 'SATISFECHO']))->assertSessionHasErrors('satisfaccion_formacion');
        $this->enviar($enlace, $this->desempeno())->assertSessionHasNoErrors()->assertRedirect();
        $this->enviar($enlace, $this->desempeno(['comunicacion' => 1]))->assertStatus(409);
        $this->assertDatabaseCount('seguimiento_egresado.respuesta_empresa', 1);
        $this->assertDatabaseHas('seguimiento_egresado.respuesta_empresa', ['comunicacion' => 4]);
    }
}
