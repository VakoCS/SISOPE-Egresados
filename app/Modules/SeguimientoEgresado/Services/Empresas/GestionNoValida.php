<?php

namespace App\Modules\SeguimientoEgresado\Services\Empresas;

use RuntimeException;

/** El dato de la empresa no se puede guardar; el mensaje se muestra junto al campo indicado. */
final class GestionNoValida extends RuntimeException
{
    public function __construct(string $mensaje, public readonly string $campo = 'general')
    {
        parent::__construct($mensaje);
    }
}
