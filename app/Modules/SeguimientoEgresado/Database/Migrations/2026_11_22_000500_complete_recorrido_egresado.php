<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/** Datos adicionales opcionales: las mediciones anteriores y las vistas de indicadores no se reescriben. */
return new class extends Migration
{
    public function up(): void
    {
        DB::unprepared("
            ALTER TABLE seguimiento_egresado.medicion_laboral
                ADD COLUMN cargo_actual VARCHAR(120),
                ADD COLUMN rango_ingreso VARCHAR(25) CHECK (rango_ingreso IN ('HASTA_1500', '1501_3000', '3001_5000', 'MAS_5000', 'NO_DECLARA')),
                ADD COLUMN tiempo_insercion VARCHAR(25) CHECK (tiempo_insercion IN ('ANTES_EGRESAR', 'HASTA_3_MESES', '4_6_MESES', '7_12_MESES', 'MAS_12_MESES', 'NO_APLICA'));
            ALTER TABLE seguimiento_egresado.satisfaccion_egresado
                ADD COLUMN pertinencia_curricular SMALLINT CHECK (pertinencia_curricular BETWEEN 1 AND 5);
        ");
    }

    public function down(): void
    {
        DB::unprepared('ALTER TABLE seguimiento_egresado.satisfaccion_egresado DROP COLUMN pertinencia_curricular');
        DB::unprepared('ALTER TABLE seguimiento_egresado.medicion_laboral DROP COLUMN cargo_actual, DROP COLUMN rango_ingreso, DROP COLUMN tiempo_insercion');
    }
};
