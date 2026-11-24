<?php

namespace App\Modules\SeguimientoEgresado\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** Contacto del egresado: columnas de contacto_egresado (correo y celular obligatorios; teléfono fijo y dirección opcionales). */
class ContactoRequest extends FormRequest
{
    use MensajesDeValidacion;

    public const CANALES = [
        'CORREO_INSTITUCIONAL' => 'Correo institucional', 'CORREO_PERSONAL' => 'Correo personal',
        'WHATSAPP' => 'WhatsApp', 'LLAMADA' => 'Llamada telefónica',
    ];

    public const HORARIOS = [
        'SIN_PREFERENCIA' => 'Sin preferencia', 'MANANA' => 'Mañana', 'TARDE' => 'Tarde', 'NOCHE' => 'Noche',
    ];

    public function rules(): array
    {
        return self::reglas();
    }

    public static function reglas(): array
    {
        return [
            'correo_personal' => ['required', ...Formatos::correo()],
            'celular_whatsapp' => ['required', 'string', 'max:20', Formatos::celular()],
            'telefono_fijo' => ['nullable', 'string', 'max:20', Formatos::telefonoFijo()],
            'direccion' => ['nullable', 'string', 'min:5', 'max:250', Formatos::texto()],
            'canal_preferido' => ['sometimes', 'required', Rule::in(array_keys(self::CANALES))],
            'horario_contacto_preferido' => ['sometimes', 'required', Rule::in(array_keys(self::HORARIOS))],
            'autoriza_mensajes' => ['sometimes', 'required', 'boolean'],
            'id_egresado' => ['prohibited'], 'egresado_id' => ['prohibited'], 'id_invitacion' => ['prohibited'],
        ];
    }

    public function messages(): array
    {
        return self::mensajesBase() + ['prohibited' => 'El expediente se obtiene del enlace personal, no del formulario.'];
    }

    public function attributes(): array
    {
        return [
            'correo_personal' => 'correo personal',
            'celular_whatsapp' => 'celular o WhatsApp',
            'telefono_fijo' => 'teléfono fijo',
            'direccion' => 'dirección',
            'canal_preferido' => 'canal preferido',
            'horario_contacto_preferido' => 'horario de contacto',
            'autoriza_mensajes' => 'autorización de mensajes',
        ];
    }
}
