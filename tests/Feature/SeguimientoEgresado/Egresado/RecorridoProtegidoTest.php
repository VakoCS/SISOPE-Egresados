<?php

namespace Tests\Feature\SeguimientoEgresado\Egresado;

use App\Modules\SeguimientoEgresado\Services\Egresado\ContactoService;
use App\Modules\SeguimientoEgresado\Services\Egresado\EncuestaService;
use App\Modules\SeguimientoEgresado\Services\Egresado\InvitacionNoDisponible;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\SeguimientoEgresado\ConEnlaceEgresado;
use Tests\Support\SeguimientoEgresado\FabricaSeguimiento;
use Tests\Support\SeguimientoEgresado\UsaBaseDeDatos;
use Tests\TestCase;

class RecorridoProtegidoTest extends TestCase
{
    use ConEnlaceEgresado, FabricaSeguimiento, UsaBaseDeDatos;

    private const CLAVE = 'seguimiento-egresado.identidad';

    private function escenario(): array
    {
        $egresado = $this->crearEgresado();
        $invitacion = $this->crearInvitacion($egresado, $this->crearCampania());
        $this->entrarPorInvitacion($invitacion)->assertRedirect(route('seguimiento-egresado.egresado.inicio'));

        return [$egresado, $invitacion];
    }

    private function contacto(array $cambios = []): array
    {
        return $cambios + [
            'correo_personal' => 'alternativo@correo.test', 'celular_whatsapp' => '+51 900 123 456',
            'telefono_fijo' => '044 123456', 'direccion' => 'Av. Universidad 123, Trujillo',
            'canal_preferido' => 'WHATSAPP', 'horario_contacto_preferido' => 'TARDE', 'autoriza_mensajes' => '0',
        ];
    }

    private function respuesta(array $cambios = []): array
    {
        return $this->datosDeEncuesta($cambios) + ['contacto' => $this->contacto()];
    }

    public function test_el_area_y_los_enlaces_no_se_cachean_ni_filtran_el_token_por_referer(): void
    {
        $egresado = $this->crearEgresado();
        $invitacion = $this->crearInvitacion($egresado, null);
        $this->entrarPorInvitacion($invitacion)->assertHeader('Referrer-Policy', 'no-referrer')->assertHeader('X-Robots-Tag', 'noindex, nofollow');
        foreach (['inicio', 'contacto', 'historial'] as $pagina) {
            $r = $this->get(route('seguimiento-egresado.egresado.'.$pagina))->assertOk()->assertHeader('Referrer-Policy', 'no-referrer');
            $this->assertStringContainsString('no-store', $r->headers->get('Cache-Control'));
            $r->assertDontSee('Padrón y promociones')->assertDontSee('Campañas e invitaciones');
        }
    }

    #[DataProvider('estadosSinAcceso')]
    public function test_revocar_un_enlace_invalida_la_sesion_ya_abierta(string $estado): void
    {
        [, $invitacion] = $this->escenario();
        DB::table('seguimiento_egresado.invitacion_egresado')->where('id', $invitacion)->update(['estado' => $estado]);
        $this->post(route('seguimiento-egresado.egresado.encuesta.responder'), $this->respuesta())->assertRedirect(route('seguimiento-egresado.acceso'));
        $this->assertNull(session(self::CLAVE));
        $this->assertDatabaseCount('seguimiento_egresado.medicion_laboral', 0);
    }

    public static function estadosSinAcceso(): iterable
    {
        yield 'anulada' => ['ANULADA'];
        yield 'expirada' => ['EXPIRADA'];
    }

    public function test_acortar_el_vencimiento_en_la_bd_revoca_la_sesion_aunque_la_cookie_siga_vigente(): void
    {
        [, $invitacion] = $this->escenario();
        DB::table('seguimiento_egresado.invitacion_egresado')->where('id', $invitacion)->update(['expira_en' => now()->subMinute()->format('Y-m-d H:i:sP')]);
        $this->post(route('seguimiento-egresado.egresado.contacto.guardar'), $this->contacto())->assertRedirect(route('seguimiento-egresado.acceso'));
        $this->assertDatabaseCount('seguimiento_egresado.contacto_egresado', 0);
    }

    public function test_cambiar_el_hash_revoca_la_sesion_abierta_con_el_token_anterior(): void
    {
        [, $invitacion] = $this->escenario();
        DB::table('seguimiento_egresado.invitacion_egresado')->where('id', $invitacion)->update(['token_hash' => hash('sha256', 'otro-token')]);
        $this->getJson(route('seguimiento-egresado.egresado.historial'))->assertUnauthorized();
        $this->assertNull(session(self::CLAVE));
    }

    #[DataProvider('sesionesManipuladas')]
    public function test_manipular_el_alcance_o_fecha_de_la_sesion_no_da_acceso(string $campo, mixed $valor): void
    {
        $this->escenario();
        $datos = session(self::CLAVE);
        $datos[$campo] = $valor;
        $this->withSession([self::CLAVE => $datos])->get(route('seguimiento-egresado.egresado.historial'))
            ->assertRedirect(route('seguimiento-egresado.acceso'));
        $this->assertNull(session(self::CLAVE));
    }

    public static function sesionesManipuladas(): iterable
    {
        yield 'otro expediente' => ['egresado_id', 999999];
        yield 'otra invitación' => ['invitacion_id', 999999];
        yield 'extender vencimiento' => ['vence_en', '2099-01-01T00:00:00+00:00'];
        yield 'fecha corrupta' => ['vence_en', 'fecha inexistente'];
        yield 'fecha no escalar' => ['vence_en', ['2099-01-01']];
        yield 'id no escalar' => ['egresado_id', ['1']];
    }

    public function test_una_identidad_inventada_sin_haber_abierto_el_enlace_no_es_suficiente(): void
    {
        [$egresado, $invitacion] = $this->escenario();
        $this->flushSession();
        $this->withSession([self::CLAVE => ['perfil' => 'EGRESADO', 'egresado_id' => $egresado, 'invitacion_id' => $invitacion, 'vence_en' => now()->addDay()->toIso8601String()]])
            ->get(route('seguimiento-egresado.egresado.inicio'))->assertRedirect(route('seguimiento-egresado.acceso'));
    }

    public function test_un_enlace_invalido_no_reutiliza_la_identidad_del_egresado_anterior(): void
    {
        $this->escenario();
        $this->get(route('seguimiento-egresado.acceso.enlace', str_repeat('0', 64)))->assertNotFound();
        $this->assertNull(session(self::CLAVE));
        $this->get(route('seguimiento-egresado.egresado.inicio'))->assertRedirect(route('seguimiento-egresado.acceso'));
    }

    public function test_el_contacto_versiona_preferencias_y_no_reactiva_el_consentimiento_al_omitirlo(): void
    {
        [$egresado] = $this->escenario();
        $this->get(route('seguimiento-egresado.egresado.contacto'))->assertOk()->assertSee('horario_contacto_preferido', false);
        $this->post(route('seguimiento-egresado.egresado.contacto.guardar'), $this->contacto())->assertSessionHasNoErrors()->assertRedirect();
        $datos = $this->contacto(['canal_preferido' => 'CORREO_PERSONAL', 'horario_contacto_preferido' => 'MANANA']);
        $this->post(route('seguimiento-egresado.egresado.contacto.guardar'), $datos)->assertSessionHasNoErrors();
        app(ContactoService::class)->guardar($egresado, ['correo_personal' => 'nuevo@correo.test', 'celular_whatsapp' => '+51 900 123 456']);
        $versiones = DB::table('seguimiento_egresado.contacto_egresado')->where('id_egresado', $egresado)->orderBy('vigente_desde')->get();
        $this->assertCount(3, $versiones);
        $this->assertSame('WHATSAPP', $versiones[0]->canal_preferido);
        $this->assertSame('CORREO_PERSONAL', $versiones[2]->canal_preferido);
        $this->assertSame('MANANA', $versiones[2]->horario_contacto_preferido);
        $this->assertFalse($versiones[2]->autoriza_mensajes);
        $this->assertCount(1, $versiones->whereNull('vigente_hasta'));
    }

    public function test_preferencias_invalidas_o_identidad_inyectada_no_se_guardan(): void
    {
        $this->escenario();
        $this->post(route('seguimiento-egresado.egresado.contacto.guardar'), $this->contacto([
            'canal_preferido' => 'TELEPATIA', 'horario_contacto_preferido' => 'CUANDO_SEA', 'autoriza_mensajes' => 'talvez', 'id_egresado' => 999999,
        ]))->assertSessionHasErrors(['canal_preferido', 'horario_contacto_preferido', 'autoriza_mensajes', 'id_egresado']);
        $this->assertDatabaseCount('seguimiento_egresado.contacto_egresado', 0);
    }

    public function test_encuesta_completa_con_formacion_satisfaccion_y_pertinencia_se_conserva_por_campania(): void
    {
        [$egresado, $primera] = $this->escenario();
        $datos = $this->respuesta([
            'cargo_actual' => 'Analista de sistemas', 'rango_ingreso' => '3001_5000', 'tiempo_insercion' => '4_6_MESES', 'pertinencia_curricular' => 4,
            'formaciones' => [
                ['tipo' => 'MAESTRIA', 'estado' => 'EN_CURSO', 'area_formacion' => 'Informática', 'institucion' => 'Universidad de prueba'],
                ['tipo' => 'ESPECIALIZACION', 'estado' => 'CULMINADA', 'area_formacion' => 'Seguridad', 'institucion' => 'Instituto de prueba', 'fecha_inicio' => '2025-01-01', 'fecha_fin' => '2025-06-01'],
                ['tipo' => 'CERTIFICACION', 'estado' => 'CULMINADA', 'area_formacion' => 'Cloud', 'institucion' => 'Centro de prueba', 'fecha_fin' => '2025-07-01'],
            ],
        ]);
        $this->post(route('seguimiento-egresado.egresado.encuesta.responder'), $datos)->assertSessionHasNoErrors()->assertRedirect(route('seguimiento-egresado.egresado.historial'));
        $medicion = DB::table('seguimiento_egresado.medicion_laboral')->where('id_invitacion', $primera)->first();
        $this->assertSame('Analista de sistemas', $medicion->cargo_actual);
        $this->assertSame('3001_5000', $medicion->rango_ingreso);
        $this->assertDatabaseHas('seguimiento_egresado.satisfaccion_egresado', ['id_medicion' => $medicion->id, 'pertinencia_curricular' => 4]);
        $this->assertDatabaseCount('seguimiento_egresado.formacion_posterior', 3);
        $this->get(route('seguimiento-egresado.egresado.historial.detalle', $medicion->id))->assertOk()
            ->assertSee('Analista de sistemas')->assertSee('De 4 a 6 meses')->assertSee('Pertinente')->assertSee('Maestría')->assertSee('Certificación');

        $segunda = $this->crearInvitacion($egresado, $this->crearCampania(['nombre' => 'Segunda campaña', 'periodo' => '2027-I']));
        $this->entrarPorInvitacion($segunda);
        $this->post(route('seguimiento-egresado.egresado.encuesta.responder'), $this->respuesta(['situacion' => 'NO_TRABAJA', 'formaciones' => []]))->assertSessionHasNoErrors();
        $this->get(route('seguimiento-egresado.egresado.historial'))->assertOk()->assertSee('Segunda campaña')->assertSee('2027-I');
        $this->assertDatabaseCount('seguimiento_egresado.medicion_laboral', 2);
        $this->assertDatabaseHas('seguimiento_egresado.medicion_laboral', ['id' => $medicion->id, 'cargo_actual' => 'Analista de sistemas']);
        $this->assertDatabaseCount('seguimiento_egresado.formacion_posterior', 3);
        $otro = $this->crearEgresado();
        $this->entrarPorInvitacion($this->crearInvitacion($otro, null));
        $this->get(route('seguimiento-egresado.egresado.historial.detalle', $medicion->id))->assertNotFound();
    }

    public function test_reenviar_no_duplica_medicion_satisfaccion_formaciones_ni_version_de_contacto(): void
    {
        $this->escenario();
        $this->post(route('seguimiento-egresado.egresado.encuesta.responder'), $this->respuesta())->assertSessionHasNoErrors();
        $cuentas = [];
        foreach (['medicion_laboral', 'satisfaccion_egresado', 'formacion_posterior', 'medicion_habilidad', 'contacto_egresado'] as $tabla) {
            $cuentas[$tabla] = DB::table('seguimiento_egresado.'.$tabla)->count();
        }
        $this->post(route('seguimiento-egresado.egresado.encuesta.responder'), $this->respuesta(['satisfaccion' => 'INSATISFECHO']))->assertSessionHas('error');
        foreach ($cuentas as $tabla => $cuenta) {
            $this->assertDatabaseCount('seguimiento_egresado.'.$tabla, $cuenta);
        }
        $this->get(route('seguimiento-egresado.egresado.historial'))->assertOk();
    }

    public function test_parametros_ajenos_o_malformados_no_cambian_la_campania_ni_el_egresado(): void
    {
        $this->escenario();
        $ajena = $this->crearInvitacion($this->crearEgresado(), $this->crearCampania());
        $this->post(route('seguimiento-egresado.egresado.encuesta.responder'), $this->respuesta() + ['invitacion' => $ajena])->assertSessionHas('error');
        $this->post(route('seguimiento-egresado.egresado.encuesta.responder'), $this->respuesta() + ['id_egresado' => 999999, 'id_campania' => 999999])->assertSessionHasErrors(['id_egresado', 'id_campania']);
        $this->post(route('seguimiento-egresado.egresado.encuesta.responder'), $this->respuesta() + ['invitacion' => [1]])->assertSessionHasErrors('invitacion');
        $this->assertDatabaseCount('seguimiento_egresado.medicion_laboral', 0);
    }

    public function test_los_campos_adicionales_se_validan_y_se_descartan_si_no_trabaja(): void
    {
        $this->escenario();
        $this->post(route('seguimiento-egresado.egresado.encuesta.responder'), $this->respuesta([
            'cargo_actual' => 'X', 'rango_ingreso' => 'MILLONES', 'tiempo_insercion' => 'AYER', 'pertinencia_curricular' => 6,
        ]))->assertSessionHasErrors(['cargo_actual', 'rango_ingreso', 'tiempo_insercion', 'pertinencia_curricular']);
        $this->assertDatabaseCount('seguimiento_egresado.medicion_laboral', 0);
        $this->post(route('seguimiento-egresado.egresado.encuesta.responder'), $this->respuesta([
            'situacion' => 'NO_TRABAJA', 'cargo_actual' => 'No guardar', 'rango_ingreso' => 'MILLONES', 'tiempo_insercion' => 'AYER',
        ]))->assertSessionHasNoErrors()->assertRedirect(route('seguimiento-egresado.egresado.historial'));
        $this->assertDatabaseHas('seguimiento_egresado.medicion_laboral', ['cargo_actual' => null, 'rango_ingreso' => null, 'tiempo_insercion' => null]);
    }

    public function test_el_servicio_rechaza_campanias_fuera_de_fechas_aunque_digan_abiertas(): void
    {
        [$egresado, $invitacion] = $this->escenario();
        $campania = DB::table('seguimiento_egresado.invitacion_egresado')->where('id', $invitacion)->value('id_campania');
        DB::table('seguimiento_egresado.campania_seguimiento')->where('id', $campania)->update(['fecha_inicio' => now()->addDays(2)->toDateString()]);
        $this->expectException(InvitacionNoDisponible::class);
        app(EncuestaService::class)->registrar($egresado, $invitacion, $this->datosDeEncuesta());
    }

    public function test_el_historial_apunta_a_la_invitacion_de_cada_campania_y_no_ofrece_enlaces_vencidos(): void
    {
        [$egresado] = $this->escenario();
        $otra = $this->crearInvitacion($egresado, $this->crearCampania(['nombre' => 'Otra campaña pendiente']));
        $vencida = $this->crearInvitacion($egresado, $this->crearCampania(['nombre' => 'Plazo individual vencido']), ['expira_en' => now()->subMinute()->format('Y-m-d H:i:sP')]);
        $this->get(route('seguimiento-egresado.egresado.historial'))->assertOk()
            ->assertSee(route('seguimiento-egresado.egresado.encuesta', ['invitacion' => $otra]), false)
            ->assertDontSee(route('seguimiento-egresado.egresado.encuesta', ['invitacion' => $vencida]), false)
            ->assertSee('No respondida');
    }
}
