<?php

namespace App\Modules\SeguimientoEgresado\Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Sectores económicos de `comun.sector_economico`, el catálogo con el que se clasifica a una empresa empleadora.
 *
 * La tabla compartida nace vacía y sin filas el formulario de «Gestión de empleadores» no ofrece sector ni permite
 * filtrar por él, así que se cargan aquí los mismos rubros que la encuesta empresarial usa en
 * `seguimiento_egresado.cat_sector_empresa`. Se puede ejecutar más de una vez.
 */
class SectoresEconomicosSeeder extends Seeder
{
    /** @var array<string, string> código => nombre (los mismos de cat_sector_empresa) */
    private const SECTORES = [
        'BANCA_FINANZAS' => 'Banca y finanzas',
        'SOFTWARE_TI' => 'Empresa de software',
        'SECTOR_PUBLICO' => 'Sector público',
        'TELECOMUNICACIONES' => 'Telecomunicaciones',
        'EDUCACION' => 'Educación',
        'SALUD' => 'Salud',
        'INDUSTRIA' => 'Industria y manufactura',
        'COMERCIO' => 'Comercio y retail',
        'CONSULTORIA_TI' => 'Consultoría y servicios TI',
        'OTRO' => 'Otro',
    ];

    public function run(): void
    {
        foreach (self::SECTORES as $codigo => $nombre) {
            $existe = DB::table('comun.sector_economico')->where('codigo', $codigo)->exists();

            if (! $existe) {
                DB::table('comun.sector_economico')->insert(['codigo' => $codigo, 'nombre' => $nombre]);
            }
        }
    }
}
