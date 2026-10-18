<!DOCTYPE html>
<html lang="es-MX">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="theme-color" content="#0d1b3d">
    <title>Suscripciones | Admin DOMUS</title>
    <link rel="stylesheet" href="{{ asset('css/admin.css') }}">
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
            <span class="da-eyebrow">Vista interna de solo lectura</span>
            <h1 id="admin-subscriptions-title">Suscripciones de DOMUS</h1>
            <p>Estado guardado tras la &uacute;ltima verificaci&oacute;n con Google Play. Esta p&aacute;gina no consulta Google en vivo. Si aparece &laquo;Pendiente de comprobar&raquo;, pulsa &laquo;Actualizar&raquo; en la app.</p>
        </div>
        <div class="da-admin-chip">
            <span class="da-admin-chip__avatar">{{ mb_strtoupper(mb_substr($admin->name, 0, 1)) }}</span>
            <span><small>Sesi&oacute;n de administrador</small><strong>{{ $admin->name }}</strong></span>
        </div>
    </section>

    <section class="da-section-heading">
        <div>
            <span class="da-kicker">Producto {{ config('billing_demo.product_id') }}</span>
            <h2>Usuarios y vigencia</h2>
        </div>
        <span class="da-result-count">{{ $users->total() }} {{ $users->total() === 1 ? 'usuario' : 'usuarios' }}</span>
    </section>

    <section class="da-family-card" aria-label="Usuarios y suscripciones">
        <div class="da-panel">
            <div class="da-member-list">
                @forelse($entries as $entry)
                    <div class="da-member">
                        <span class="da-member__avatar">{{ mb_strtoupper(mb_substr($entry['user']->name, 0, 1)) }}</span>
                        <span class="da-member__identity">
                            <strong>{{ $entry['user']->name }}</strong>
                            <small>ID {{ $entry['user']->id }} &middot; {{ $entry['user']->email ?: ($entry['user']->username ? '@'.$entry['user']->username : 'Sin correo') }}</small>
                            <small>{{ $entry['product_id'] ? 'Producto: '.$entry['product_id'] : 'Sin compra real verificada' }}</small>
                            @if($entry['expires_at'])
                                <small>{{ $entry['expires_at']->isFuture() ? 'Vence' : 'Venci&oacute;' }} {{ $entry['expires_at']->format('d/m/Y H:i') }} UTC</small>
                            @endif
                            @if($entry['verified_at'])
                                <small>Verificado {{ $entry['verified_at']->format('d/m/Y H:i') }} UTC</small>
                            @endif
                        </span>
                        <span class="da-member__meta">
                            <span class="da-status {{ $entry['status_class'] }}">{{ $entry['status'] }}</span>
                        </span>
                    </div>
                @empty
                    <p class="da-empty da-empty--compact">Todav&iacute;a no hay usuarios registrados.</p>
                @endforelse
            </div>
        </div>
    </section>

    @if($users->hasPages())
        <nav class="da-pagination" aria-label="Paginaci&oacute;n de usuarios">
            @if($users->onFirstPage())
                <span class="is-disabled">Anterior</span>
            @else
                <a href="{{ $users->previousPageUrl() }}">Anterior</a>
            @endif
            <span class="da-pagination__current">P&aacute;gina {{ $users->currentPage() }} de {{ $users->lastPage() }}</span>
            @if($users->hasMorePages())
                <a href="{{ $users->nextPageUrl() }}">Siguiente</a>
            @else
                <span class="is-disabled">Siguiente</span>
            @endif
        </nav>
    @endif
</main>
</body>
</html>
