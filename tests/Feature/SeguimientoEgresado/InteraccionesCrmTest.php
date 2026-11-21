<?php

namespace Tests\Feature\SeguimientoEgresado;

use App\Modules\SeguimientoEgresado\Database\Seeders\DatosPruebaSeeder;
use App\Modules\SeguimientoEgresado\Models\ContactoEgresado;
use App\Modules\SeguimientoEgresado\Models\Egresado;
use App\Modules\SeguimientoEgresado\Models\InteraccionCrm;
use Tests\Support\SeguimientoEgresado\ComoPersonal;
use Tests\Support\SeguimientoEgresado\UsaBaseDeDatos;
use Tests\TestCase;

class InteraccionesCrmTest extends TestCase
{
    use ComoPersonal, UsaBaseDeDatos;

    protected function setUp(): void
    {
        parent::setUp();
        $this->actingAs($this->secretaria());
        $this->seed(DatosPruebaSeeder::class);
    }

    public function test_secretaria_puede_registrar_interaccion_crm_y_actualizar_segmento(): void
    {
        $egresado = Egresado::first();
        $this->assertNotNull($egresado);

        $payload = [
            'canal' => 'LLAMADA',
            'estado' => 'CONTACTADO',
            'resumen' => 'Llamada telefónica exitosa. Se confirmó actualización de datos laborales.',
            'observacion' => 'El egresado labora actualmente como Senior Backend Developer.',
            'fecha_interaccion' => now()->format('Y-m-d H:i:s'),
            'proximo_contacto_en' => now()->addMonths(6)->format('Y-m-d H:i:s'),
            'actualizar_segmento' => 'ACTUALIZADO',
        ];

        $response = $this->postJson("/seguimiento-egresado/padron/egresado/{$egresado->id}/interaccion", $payload);

        $response->assertStatus(200);
        $response->assertJson([
            'success' => true,
            'message' => 'Interacción CRM registrada correctamente.',
        ]);

        $this->assertDatabaseHas('seguimiento_egresado.interaccion_crm', [
            'id_egresado' => $egresado->id,
            'canal' => 'LLAMADA',
            'estado' => 'CONTACTADO',
            'resumen' => 'Llamada telefónica exitosa. Se confirmó actualización de datos laborales.',
        ]);

        // Verificar que el segmento del egresado se actualizó
        $egresado->refresh();
        $this->assertSame('ACTUALIZADO', $egresado->segmento);
    }

    public function test_secretaria_puede_listar_interacciones_crm_de_un_egresado(): void
    {
        $egresado = Egresado::first();
        $usuarioId = \Illuminate\Support\Facades\DB::table('seguimiento_egresado.usuario_interno')->value('id');

        // Registrar dos interacciones
        InteraccionCrm::create([
            'id_egresado' => $egresado->id,
            'id_usuario_registro' => $usuarioId,
            'canal' => 'CORREO',
            'estado' => 'NO_CONTACTADO',
            'resumen' => 'Correo de recordatorio rebotado.',
            'fecha_interaccion' => now()->subDays(2),
        ]);

        InteraccionCrm::create([
            'id_egresado' => $egresado->id,
            'id_usuario_registro' => $usuarioId,
            'canal' => 'WHATSAPP',
            'estado' => 'CONTACTADO',
            'resumen' => 'Confirmó recepción de invitación por WhatsApp.',
            'fecha_interaccion' => now(),
        ]);

        $response = $this->getJson("/seguimiento-egresado/padron/egresado/{$egresado->id}/interacciones");

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'egresado_id',
            'nombre_completo',
            'segmento',
            'interacciones' => [
                '*' => ['id', 'canal', 'estado', 'resumen', 'fecha_interaccion'],
            ],
        ]);

        $json = $response->json();
        $this->assertSame($egresado->id, $json['egresado_id']);
        $this->assertCount(2, $json['interacciones']);
    }

    public function test_rechaza_registro_de_interaccion_con_datos_invalidos(): void
    {
        $egresado = Egresado::first();

        $response = $this->postJson("/seguimiento-egresado/padron/egresado/{$egresado->id}/interaccion", [
            'canal' => 'PALOMA_MENSAJERA', // canal inválido
            'estado' => 'ESTADO_INEXISTENTE',
            'resumen' => '', // requerido
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['canal', 'estado', 'resumen']);
    }

    public function test_edicion_de_egresado_valida_duplicados_estrictamente(): void
    {
        $egresados = Egresado::take(2)->get();
        $this->assertCount(2, $egresados);

        $egresadoA = $egresados[0];
        $egresadoB = $egresados[1];

        // Intentar actualizar egresadoB con el mismo código de matrícula de egresadoA
        $payloadDuplicado = [
            'id' => $egresadoB->id,
            'codigo_matricula' => $egresadoA->codigo_matricula, // DUPLICADO
            'numero_documento' => '79998888',
            'nombres' => $egresadoB->nombres,
            'apellido_paterno' => $egresadoB->apellido_paterno,
            'apellido_materno' => $egresadoB->apellido_materno,
            'id_cohorte' => $egresadoB->id_cohorte,
            'grado' => 'EGRESADO',
            'apto_f1' => 'APTO',
            'correo_institucional' => $egresadoB->correo_institucional,
        ];

        $response = $this->postJson('/seguimiento-egresado/padron/egresado', $payloadDuplicado);
        $response->assertStatus(422);
        $response->assertJson([
            'success' => false,
            'message' => 'Ya existe otro egresado registrado con la matrícula o documento indicado.',
        ]);

        // Intentar actualizar egresadoB con el mismo documento de egresadoA
        $payloadDocDuplicado = [
            'id' => $egresadoB->id,
            'codigo_matricula' => '1021400999',
            'numero_documento' => $egresadoA->numero_documento, // DUPLICADO
            'nombres' => $egresadoB->nombres,
            'apellido_paterno' => $egresadoB->apellido_paterno,
            'apellido_materno' => $egresadoB->apellido_materno,
            'id_cohorte' => $egresadoB->id_cohorte,
            'grado' => 'EGRESADO',
            'apto_f1' => 'APTO',
            'correo_institucional' => $egresadoB->correo_institucional,
        ];

        $responseDoc = $this->postJson('/seguimiento-egresado/padron/egresado', $payloadDocDuplicado);
        $responseDoc->assertStatus(422);
        $responseDoc->assertJson([
            'success' => false,
            'message' => 'Ya existe otro egresado registrado con la matrícula o documento indicado.',
        ]);
    }

    public function test_persiste_preferencias_de_contacto_y_segmento_en_edicion(): void
    {
        $egresado = Egresado::first();

        $payload = [
            'id' => $egresado->id,
            'codigo_matricula' => $egresado->codigo_matricula,
            'numero_documento' => $egresado->numero_documento,
            'nombres' => $egresado->nombres,
            'apellido_paterno' => $egresado->apellido_paterno,
            'apellido_materno' => $egresado->apellido_materno,
            'id_cohorte' => $egresado->id_cohorte,
            'grado' => 'EGRESADO',
            'apto_f1' => 'APTO',
            'correo_institucional' => $egresado->correo_institucional,
            'segmento' => 'ACTUALIZADO',
            'canal_preferido' => 'WHATSAPP',
            'horario_contacto_preferido' => 'TARDES',
            'autoriza_mensajes' => true,
            'correo_personal' => 'contacto.nuevo@empresa.com',
            'telefono' => '955112233',
        ];

        $response = $this->postJson('/seguimiento-egresado/padron/egresado', $payload);
        $response->assertStatus(200);

        $egresado->refresh();
        $this->assertSame('ACTUALIZADO', $egresado->segmento);

        $contacto = $egresado->contactoVigente;
        $this->assertNotNull($contacto);
        $this->assertSame('WHATSAPP', $contacto->canal_preferido);
        $this->assertSame('TARDES', $contacto->horario_contacto_preferido);
        $this->assertTrue((bool) $contacto->autoriza_mensajes);
        $this->assertSame('contacto.nuevo@empresa.com', $contacto->correo_personal);
        $this->assertSame('955112233', $contacto->celular_whatsapp);
    }
}
