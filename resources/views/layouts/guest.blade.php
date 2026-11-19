<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="description" content="Acceso al CRM de Seguimiento al Egresado - UNT.">
    <title>Acceso · CRM Seguimiento al Egresado | UNT</title>
    <link rel="icon" type="image/png" href="{{ asset('img/logo-informatica.png') }}">
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=fira-code:500,600|fira-sans:400,500,600,700&display=swap" rel="stylesheet">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @livewireStyles
</head>
<body class="auth-body">
    <a class="skip-link" href="#contenido">Saltar al formulario</a>
    <main id="contenido" class="auth-shell">
        <aside class="auth-context" aria-label="CRM Seguimiento al Egresado">
            <a class="auth-brand" href="{{ route('seguimiento-egresado.index') }}" aria-label="Volver al CRM de Egresados">
                <img class="auth-brand-logo" src="{{ asset('img/logo-informatica.png') }}" alt="">
                <span><strong>CRM Egresados</strong><small>Ingeniería Informática · UNT</small></span>
            </a>

            <div class="auth-context-copy">
                <h2>Sistema de Seguimiento al Egresado (SISOPE)</h2>
                <p>Ingresa con tu cuenta institucional para gestionar el padrón de egresados, convocatorias de seguimiento e indicadores de empleabilidad.</p>
                <ul>
                    <li><svg viewBox="0 0 24 24" aria-hidden="true"><path d="m5 12 4 4L19 6"/></svg>Padrón oficial y promociones</li>
                    <li><svg viewBox="0 0 24 24" aria-hidden="true"><path d="m5 12 4 4L19 6"/></svg>Campañas de seguimiento y correo</li>
                    <li><svg viewBox="0 0 24 24" aria-hidden="true"><path d="m5 12 4 4L19 6"/></svg>Encuestas a empleadores e indicadores I1 a I6</li>
                </ul>
            </div>

            <div class="auth-network" aria-hidden="true">
                <span></span><span></span><span></span><span></span>
                <svg viewBox="0 0 620 260"><path d="M8 209 169 73l147 94L468 38l144 93"/><path d="M8 209h604"/></svg>
            </div>
            <p class="auth-context-footer">Escuela de Ingeniería Informática · Acceso protegido</p>
        </aside>

        <section class="auth-panel">
            <a class="auth-back" href="{{ route('seguimiento-egresado.index') }}">
                <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M19 12H5m6-6-6 6 6 6"/></svg>
                Inicio CRM
            </a>
            <div class="auth-form-frame">
                {{ $slot }}
            </div>
        </section>
    </main>
@livewireScriptConfig
</body>
</html>
