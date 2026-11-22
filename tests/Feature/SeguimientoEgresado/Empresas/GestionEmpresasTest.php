<?php

namespace Tests\Feature\SeguimientoEgresado\Empresas;

use App\Modules\SeguimientoEgresado\Database\Seeders\SectoresEconomicosSeeder;
use Illuminate\Support\Facades\DB;
use Tests\Support\SeguimientoEgresado\FabricaEmpresasIndicadores;
use Tests\Support\SeguimientoEgresado\UsaBaseDeDatos;
use Tests\TestCase;

/**
 * Gestión de empleadores: el catálogo de empresas vive en `comun.empresa` y el vínculo con un egresado en
 * `comun.contacto_empresa` (a través de `core.persona`), sin tablas ni migraciones nuevas.
 */
class GestionEmpresasTest extends TestCase
{
    use UsaBaseDeDatos;
    use FabricaEmpresasIndicadores;

    protected function setUp(): void
    {
        parent::setUp();
        $this->entrarComo('SECRETARIA');
    }

    public function test_registra_una_empresa_y_se_ve_su_detalle(): void
    {
        $this->post(route('seguimiento-egresado.empresa.guardar'), $this->datos())
            ->assertSessionHasNoErrors();

        $empresa = DB::table('comun.empresa')->where('ruc', '20512345678')->first();

        $this->assertNotNull($empresa, 'la empresa se guarda en comun.empresa');
        $this->assertDatabaseHas('comun.empresa', [
            'id_empresa' => $empresa->id_empresa,
            'razon_social' => 'Empresa Andina S.A.C.',
            'estado' => 'Activo',
        ]);

        $this->get(route('seguimiento-egresado.empresa'))
            ->assertOk()
            ->assertSee('Empresa Andina S.A.C.')
            ->assertSee('20512345678');

        $this->get(route('seguimiento-egresado.empresa.detalle', $empresa->id_empresa))
            ->assertOk()
            ->assertSee('Empresa Andina S.A.C.')
            ->assertSee('contacto@empresa.test');
    }

    public function test_no_acepta_un_ruc_ni_una_razon_social_repetidos(): void
    {
        $this->post(route('seguimiento-egresado.empresa.guardar'), $this->datos())->assertSessionHasNoErrors();

        $this->post(route('seguimiento-egresado.empresa.guardar'), $this->datos(['razon_social' => 'Otra Razón Social']))
            ->assertSessionHasErrors('ruc');

        // La razón social se compara sin distinguir mayúsculas: la misma con otro RUC tampoco entra.
        $this->post(route('seguimiento-egresado.empresa.guardar'), $this->datos([
            'ruc' => '20876543210',
            'razon_social' => 'EMPRESA ANDINA S.A.C.',
        ]))->assertSessionHasErrors('razon_social');

        $this->assertSame(1, DB::table('comun.empresa')->count());
    }

    public function test_exige_los_campos_obligatorios_y_un_ruc_de_11_digitos(): void
    {
        $this->post(route('seguimiento-egresado.empresa.guardar'), $this->datos(['ruc' => '12345', 'razon_social' => '']))
            ->assertSessionHasErrors(['ruc', 'razon_social']);

        $this->post(route('seguimiento-egresado.empresa.guardar'), $this->datos(['estado' => 'Borrador']))
            ->assertSessionHasErrors('estado');

        $this->assertSame(0, DB::table('comun.empresa')->count());
    }

    public function test_actualiza_los_datos_y_los_filtra_por_texto(): void
    {
        $this->post(route('seguimiento-egresado.empresa.guardar'), $this->datos())->assertSessionHasNoErrors();
        $this->post(route('seguimiento-egresado.empresa.guardar'), $this->datos([
            'ruc' => '20111222333',
            'razon_social' => 'Constructora del Valle S.A.',
        ]))->assertSessionHasNoErrors();

        $empresa = (int) DB::table('comun.empresa')->where('ruc', '20512345678')->value('id_empresa');

        $this->put(route('seguimiento-egresado.empresa.actualizar', $empresa), $this->datos([
            'razon_social' => 'Empresa Andina del Norte S.A.C.',
            'nombre_comercial' => 'Andina Norte',
        ]))->assertRedirect(route('seguimiento-egresado.empresa.detalle', $empresa));

        $this->assertDatabaseHas('comun.empresa', ['id_empresa' => $empresa, 'razon_social' => 'Empresa Andina del Norte S.A.C.']);

        $this->get(route('seguimiento-egresado.empresa', ['q' => 'Andina']))
            ->assertOk()
            ->assertSee('Empresa Andina del Norte S.A.C.')
            ->assertDontSee('Constructora del Valle S.A.');

        $this->get(route('seguimiento-egresado.empresa', ['q' => '20111222333']))
            ->assertOk()
            ->assertSee('Constructora del Valle S.A.')
            ->assertDontSee('Empresa Andina del Norte S.A.C.');
    }

    public function test_vincula_un_egresado_a_la_empresa_y_lo_quita(): void
    {
        $this->post(route('seguimiento-egresado.empresa.guardar'), $this->datos())->assertSessionHasNoErrors();
        $empresa = (int) DB::table('comun.empresa')->where('ruc', '20512345678')->value('id_empresa');
        $egresado = $this->egresado($this->cohorte(), cambios: [
            'numero_documento' => '79112233',
            'apellido_paterno' => 'Quispe',
            'apellido_materno' => 'Rojas',
            'nombres' => 'María Fernanda',
        ]);

        $this->post(route('seguimiento-egresado.empresa.vincular', $empresa), ['id_egresado' => $egresado])
            ->assertRedirect(route('seguimiento-egresado.empresa.detalle', $empresa));

        $this->assertDatabaseHas('comun.contacto_empresa', ['id_empresa' => $empresa, 'estado' => 'Activo']);
        $this->assertDatabaseHas('core.persona', ['tipo_documento' => 'DNI', 'numero_documento' => '79112233']);

        $this->get(route('seguimiento-egresado.empresa.detalle', $empresa))
            ->assertOk()
            ->assertSee('Quispe Rojas, María Fernanda')
            ->assertSee('1 egresado vinculado');

        // Volver a vincularlo no duplica el vínculo.
        $this->post(route('seguimiento-egresado.empresa.vincular', $empresa), ['id_egresado' => $egresado])
            ->assertRedirect(route('seguimiento-egresado.empresa.detalle', $empresa));
        $this->assertSame(1, DB::table('comun.contacto_empresa')->where('id_empresa', $empresa)->count());

        $this->post(route('seguimiento-egresado.empresa.desvincular', [$empresa, $egresado]))
            ->assertRedirect(route('seguimiento-egresado.empresa.detalle', $empresa));

        $this->assertDatabaseHas('comun.contacto_empresa', ['id_empresa' => $empresa, 'estado' => 'Inactivo']);
        $this->get(route('seguimiento-egresado.empresa.detalle', $empresa))
            ->assertOk()
            ->assertSee('0 egresados vinculados');
    }

    public function test_ofrece_los_egresados_que_aun_no_estan_vinculados(): void
    {
        $this->post(route('seguimiento-egresado.empresa.guardar'), $this->datos())->assertSessionHasNoErrors();
        $empresa = (int) DB::table('comun.empresa')->where('ruc', '20512345678')->value('id_empresa');
        $vinculado = $this->egresado($this->cohorte(), cambios: ['apellido_paterno' => 'Vinculado', 'numero_documento' => '79900001']);
        $libre = $this->egresado($this->cohorte(), cambios: ['apellido_paterno' => 'Libre', 'numero_documento' => '79900002']);

        $this->post(route('seguimiento-egresado.empresa.vincular', $empresa), ['id_egresado' => $vinculado]);

        // El vinculado sale de la lista de opciones; el que sigue libre queda a mano para elegirlo.
        $this->get(route('seguimiento-egresado.empresa.detalle', $empresa))
            ->assertOk()
            ->assertSee('<option value="'.$libre.'">', false)
            ->assertDontSee('<option value="'.$vinculado.'">', false)
            ->assertSee('Libre, Persona');
    }

    public function test_el_sector_se_elige_y_la_directoria_consulta_sin_gestionar(): void
    {
        $this->seed(SectoresEconomicosSeeder::class);
        $sector = (int) DB::table('comun.sector_economico')->where('codigo', 'SOFTWARE_TI')->value('id_sector');

        $this->post(route('seguimiento-egresado.empresa.guardar'), $this->datos(['id_sector' => $sector]))
            ->assertSessionHasNoErrors();
        $empresa = (int) DB::table('comun.empresa')->where('ruc', '20512345678')->value('id_empresa');

        $this->get(route('seguimiento-egresado.empresa', ['sector' => $sector]))
            ->assertOk()
            ->assertSee('Empresa Andina S.A.C.');

        $this->get(route('seguimiento-egresado.empresa', ['q' => 'no existe']))
            ->assertOk()
            ->assertDontSee('Empresa Andina S.A.C.');

        // La Dirección consulta empresas y sus vínculos, pero no da de alta ni edita.
        $this->entrarComo('DIRECTOR');
        $this->get(route('seguimiento-egresado.empresa.detalle', $empresa))->assertOk();
        $this->post(route('seguimiento-egresado.empresa.guardar'), $this->datos(['ruc' => '20999888777']))->assertForbidden();
        $this->put(route('seguimiento-egresado.empresa.actualizar', $empresa), $this->datos())->assertForbidden();
    }

    public function test_una_empresa_inexistente_responde_404(): void
    {
        $this->get(route('seguimiento-egresado.empresa.detalle', 999999))->assertNotFound();
    }

    /** @return array<string, mixed> */
    private function datos(array $cambios = []): array
    {
        return array_merge([
            'ruc' => '20512345678',
            'razon_social' => 'Empresa Andina S.A.C.',
            'nombre_comercial' => 'Andes Lab',
            'direccion' => 'Av. Universidad s/n',
            'telefono' => '044 123456',
            'correo' => 'contacto@empresa.test',
            'pagina_web' => 'https://empresa.test',
            'estado' => 'Activo',
        ], $cambios);
    }
}
