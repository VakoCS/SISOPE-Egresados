<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/** Extensión aditiva: conserva respuestas, vistas I5 y enlaces emitidos con el cuestionario anterior. */
return new class extends Migration
{
    public function up(): void
    {
        DB::unprepared("
            ALTER TABLE seguimiento_egresado.invitacion_empresa
                ADD COLUMN id_empresa BIGINT REFERENCES comun.empresa(id_empresa),
                ADD COLUMN version_formulario SMALLINT NOT NULL DEFAULT 1 CHECK (version_formulario IN (1, 2));
            CREATE INDEX idx_invitacion_empresa_empleador ON seguimiento_egresado.invitacion_empresa(id_empresa);

            ALTER TABLE seguimiento_egresado.respuesta_empresa
                ADD COLUMN demanda_profesionales VARCHAR(20) CHECK (demanda_profesionales IN ('NINGUNA', 'UNO_CINCO', 'SEIS_DIEZ', 'MAS_DIEZ')),
                ADD COLUMN id_satisfaccion_formacion BIGINT REFERENCES seguimiento_egresado.cat_nivel_satisfaccion(id),
                ADD COLUMN desempeno_laboral SMALLINT CHECK (desempeno_laboral BETWEEN 1 AND 5),
                ADD COLUMN dominio_conocimientos SMALLINT CHECK (dominio_conocimientos BETWEEN 1 AND 5),
                ADD COLUMN resolucion_problemas SMALLINT CHECK (resolucion_problemas BETWEEN 1 AND 5),
                ADD COLUMN trabajo_equipo SMALLINT CHECK (trabajo_equipo BETWEEN 1 AND 5),
                ADD COLUMN comunicacion SMALLINT CHECK (comunicacion BETWEEN 1 AND 5);

            CREATE TABLE seguimiento_egresado.respuesta_empresa_sector (
                id_respuesta BIGINT NOT NULL REFERENCES seguimiento_egresado.respuesta_empresa(id) ON DELETE CASCADE,
                id_sector BIGINT NOT NULL REFERENCES seguimiento_egresado.cat_sector_empresa(id),
                PRIMARY KEY (id_respuesta, id_sector)
            );
            CREATE TABLE seguimiento_egresado.respuesta_empresa_perfil (
                id_respuesta BIGINT NOT NULL REFERENCES seguimiento_egresado.respuesta_empresa(id) ON DELETE CASCADE,
                codigo VARCHAR(30) NOT NULL CHECK (codigo IN ('DESARROLLO', 'DATOS_IA', 'INFRAESTRUCTURA', 'SEGURIDAD', 'GESTION_TI', 'SOPORTE', 'OTRO')),
                PRIMARY KEY (id_respuesta, codigo)
            );
        ");
    }

    public function down(): void
    {
        DB::unprepared('
            DROP TABLE seguimiento_egresado.respuesta_empresa_perfil;
            DROP TABLE seguimiento_egresado.respuesta_empresa_sector;
            ALTER TABLE seguimiento_egresado.respuesta_empresa
                DROP COLUMN demanda_profesionales, DROP COLUMN id_satisfaccion_formacion,
                DROP COLUMN desempeno_laboral, DROP COLUMN dominio_conocimientos,
                DROP COLUMN resolucion_problemas, DROP COLUMN trabajo_equipo, DROP COLUMN comunicacion;
            ALTER TABLE seguimiento_egresado.invitacion_empresa DROP COLUMN id_empresa, DROP COLUMN version_formulario;
        ');
    }
};
