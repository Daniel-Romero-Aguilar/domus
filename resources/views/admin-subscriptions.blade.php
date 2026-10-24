<!DOCTYPE html>
<html lang="es-MX">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="theme-color" content="#0d1b3d">
    <title>Suscripciones | Admin DOMUS</title>
    <link rel="stylesheet" href="{{ asset('css/admin.css') }}">
    @livewireStyles
</head>
<body class="domus-admin">
<header class="da-header">
    <div class="da-shell da-header__inner">
        <a class="da-brand" href="{{ route('admin.families.index') }}" aria-label="DOMUS administraci&oacute;n">
            <img src="{{ asset('img/domus_logo.png') }}" alt="DOMUS">
            <span>Administraci&oacute;n</span>
        </a>
        <nav class="da-navigation" aria-label="Navegaci&oacute;n administrativa">
            <a href="{{ route('admin.families.index') }}">Familias</a>
            <a class="is-active" href="{{ route('admin.subscriptions.index') }}">Suscripciones</a>
            <a href="{{ route('admin.dashboard') }}">Insignias</a>
            <a href="{{ route('admin.landing-emails') }}">Correos</a>
        </nav>
        <form method="POST" action="{{ route('admin.logout') }}">
            @csrf
            <button class="da-logout" type="submit">Cerrar sesi&oacute;n</button>
        </form>
    </div>
</header>

<main class="da-shell da-main">
    <section class="da-hero" aria-labelledby="admin-subscriptions-title">
        <div>
            <span class="da-eyebrow">Suscripciones y acceso gratuito</span>
            <h1 id="admin-subscriptions-title">Suscripciones de DOMUS</h1>
            <p>Estado guardado tras la &uacute;ltima verificaci&oacute;n con Google Play. Esta p&aacute;gina no consulta Google en vivo. Si aparece &laquo;Pendiente de comprobar&raquo;, pulsa &laquo;Actualizar&raquo; en la app.</p>
            <p>Las cuentas tienen {{ config('access.trial_days') }} d&iacute;as gratis desde su registro. Puedes editar su vencimiento; al terminar, necesitan Premium. Los integrantes comparten el acceso de su cuenta familiar. Las fechas se muestran y editan en UTC.</p>
        </div>
        <div class="da-admin-chip">
            <span class="da-admin-chip__avatar">{{ mb_strtoupper(mb_substr($admin->name, 0, 1)) }}</span>
            <span><small>Sesi&oacute;n de administrador</small><strong>{{ $admin->name }}</strong></span>
        </div>
    </section>

    @livewire('admin-subscription-list')
</main>
@livewireScripts
</body>
</html>
