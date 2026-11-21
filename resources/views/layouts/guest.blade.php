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
            <div class="auth-brand" aria-label="Universidad Nacional de Trujillo">
                <img class="auth-brand-logo" src="{{ asset('img/logo-informatica.png') }}" alt="Logo UNT">
                <span><strong>UNIVERSIDAD NACIONAL DE TRUJILLO</strong><small>Ingeniería Informática</small></span>
            </div>

            <div class="auth-context-copy">
                <h2>Seguimiento al Egresado</h2>
                <p>Ingresa con tu cuenta institucional para continuar.</p>
            </div>

            <p class="auth-context-footer">Escuela de Ingeniería Informática · UNT</p>
        </aside>

        <section class="auth-panel">
            <div class="auth-form-frame">
                {{ $slot }}
            </div>
        </section>
    </main>
@livewireScriptConfig
</body>
</html>
