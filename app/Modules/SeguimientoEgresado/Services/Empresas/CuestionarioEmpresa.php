<?php

namespace App\Modules\SeguimientoEgresado\Services\Empresas;

use App\Modules\SeguimientoEgresado\Support\Esquema;
use Illuminate\Validation\Rule;

/** Vocabulario compartido por validación y vistas. No modifica los catálogos ni el cálculo de I5. */
final class CuestionarioEmpresa
{
    public const PERFILES = [
        'DESARROLLO' => 'Desarrollo de software',
        'DATOS_IA' => 'Datos e inteligencia artificial',
        'INFRAESTRUCTURA' => 'Redes e infraestructura',
        'SEGURIDAD' => 'Seguridad informática',
        'GESTION_TI' => 'Gestión de proyectos TI',
        'SOPORTE' => 'Soporte técnico',
        'OTRO' => 'Otro perfil',
    ];

    public const DEMANDA = [
        'NINGUNA' => 'Ninguno', 'UNO_CINCO' => 'De 1 a 5',
        'SEIS_DIEZ' => 'De 6 a 10', 'MAS_DIEZ' => 'Más de 10',
    ];

    public const DIMENSIONES = [
        'desempeno_laboral' => 'Desempeño laboral',
        'dominio_conocimientos' => 'Dominio de conocimientos',
        'resolucion_problemas' => 'Resolución de problemas',
        'trabajo_equipo' => 'Trabajo en equipo',
        'comunicacion' => 'Comunicación',
    ];

    public const ESCALA = [1 => 'Muy bajo', 2 => 'Bajo', 3 => 'Adecuado', 4 => 'Alto', 5 => 'Muy alto'];

    public static function reglas(string $contexto, int $version): array
    {
        $mercado = $contexto === 'MERCADO' && $version >= 2;
        $desempeno = $contexto === 'DESEMPENO' && $version >= 2;
        $reglas = [
            // La identidad y el tipo vienen exclusivamente de la invitación, nunca del navegador.
            'id_egresado' => ['prohibited'], 'id_empresa' => ['prohibited'],
            'contexto' => ['prohibited'], 'id_campania' => ['prohibited'], 'version_formulario' => ['prohibited'],
            'sectores_contratacion' => $mercado ? ['required', 'array', 'min:1', 'max:10'] : ['prohibited'],
            'sectores_contratacion.*' => ['string', 'distinct', Rule::exists(Esquema::validar('cat_sector_empresa'), 'codigo')->where('activo', true)],
            'perfiles_requeridos' => $mercado ? ['required', 'array', 'min:1', 'max:7'] : ['prohibited'],
            'perfiles_requeridos.*' => ['string', 'distinct', Rule::in(array_keys(self::PERFILES))],
            'demanda_profesionales' => $mercado ? ['required', Rule::in(array_keys(self::DEMANDA))] : ['prohibited'],
            'satisfaccion_formacion' => $mercado
                ? ['required', 'string', Rule::exists(Esquema::validar('cat_nivel_satisfaccion'), 'codigo')->where('activo', true)]
                : ['prohibited'],
        ];
        foreach (self::DIMENSIONES as $campo => $etiqueta) {
            $reglas[$campo] = $desempeno ? ['required', 'integer', 'between:1,5'] : ['prohibited'];
        }

        return $reglas;
    }
}
