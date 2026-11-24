@php
  $prefijo = $prefijo ?? '';
  $clavePreferencia = fn ($campo) => $prefijo === '' ? $campo : $prefijo.'.'.$campo;
  $nombrePreferencia = fn ($campo) => $prefijo === '' ? $campo : $prefijo.'['.$campo.']';
  $contactoRequest = \App\Modules\SeguimientoEgresado\Requests\ContactoRequest::class;
@endphp
@include('modules.seguimiento-egresado.egresado._lista', [
  'id' => 'preferencia-canal', 'nombre' => $nombrePreferencia('canal_preferido'), 'etiqueta' => 'Canal de contacto preferido',
  'requerido' => true, 'opciones' => $contactoRequest::CANALES,
  'seleccion' => old($clavePreferencia('canal_preferido'), $contacto['canal_preferido'] ?? 'CORREO_PERSONAL'),
])
@include('modules.seguimiento-egresado.egresado._lista', [
  'id' => 'preferencia-horario', 'nombre' => $nombrePreferencia('horario_contacto_preferido'), 'etiqueta' => 'Horario de contacto preferido',
  'requerido' => true, 'opciones' => $contactoRequest::HORARIOS,
  'seleccion' => old($clavePreferencia('horario_contacto_preferido'), $contacto['horario_contacto_preferido'] ?? 'SIN_PREFERENCIA'),
])
@include('modules.seguimiento-egresado.egresado._lista', [
  'id' => 'preferencia-mensajes', 'nombre' => $nombrePreferencia('autoriza_mensajes'), 'etiqueta' => '¿Autorizas mensajes de seguimiento?',
  'requerido' => true, 'opciones' => ['1' => 'Sí, autorizo', '0' => 'No autorizo'],
  'seleccion' => old($clavePreferencia('autoriza_mensajes'), (int) ($contacto['autoriza_mensajes'] ?? true)),
])
