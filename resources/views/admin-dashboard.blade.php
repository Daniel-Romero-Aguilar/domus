<!DOCTYPE html>
<html lang="es-MX">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="theme-color" content="#0d1b3d">
    <title>Insignias | Admin DOMUS</title>
    <link rel="stylesheet" href="{{ asset('css/admin.css') }}">
</head>
<body class="domus-admin">
<header class="da-header">
    <div class="da-shell da-header__inner">
        <a class="da-brand" href="{{ route('admin.dashboard') }}" aria-label="DOMUS administraci&oacute;n">
            <img src="{{ asset('img/domus_logo.png') }}" alt="DOMUS">
            <span>Administraci&oacute;n</span>
        </a>
        <nav class="da-navigation" aria-label="Navegaci&oacute;n administrativa">
            <a href="{{ route('admin.families.index') }}">Familias</a>
            <a href="{{ route('admin.subscriptions.index') }}">Suscripciones</a>
            <a class="is-active" href="{{ route('admin.dashboard') }}">Insignias</a>
            <a href="{{ route('admin.landing-emails') }}">Correos</a>
        </nav>
        <form method="POST" action="{{ route('admin.logout') }}">
            @csrf
            <button class="da-logout" type="submit">Cerrar sesi&oacute;n</button>
        </form>
    </div>
</header>

<main class="da-shell da-main da-badges-main">
    <section class="da-hero da-badges-hero" aria-labelledby="admin-badges-title">
        <div>
            <span class="da-eyebrow"><span aria-hidden="true">&#10022;</span> Cat&aacute;logo de logros</span>
            <h1 id="admin-badges-title">Insignias de DOMUS</h1>
            <p>Administra la imagen p&uacute;blica de cada logro sin alterar las reglas que lo desbloquean.</p>
        </div>
        <aside class="da-badge-overview" aria-label="Resumen de insignias">
            <span class="da-badge-overview__icon" aria-hidden="true">&#9671;</span>
            <span>
                <small>Insignias configuradas</small>
                <strong>{{ number_format($badges->count()) }}</strong>
            </span>
        </aside>
    </section>

    @if(session('status'))
        <div class="da-notice da-notice--success" role="status">{{ session('status') }}</div>
    @endif
    @if($errors->any())
        <div class="da-notice da-notice--error" role="alert">{{ $errors->first() }}</div>
    @endif

    <section class="da-section-heading">
        <div>
            <span class="da-kicker">Configuraci&oacute;n visual</span>
            <h2>Im&aacute;genes p&uacute;blicas</h2>
        </div>
        <span class="da-result-count">{{ $badges->count() }} {{ $badges->count() === 1 ? 'insignia' : 'insignias' }}</span>
    </section>

    <section class="da-badge-grid" aria-label="Insignias configuradas">
        @foreach($badges as $badge)
            @php
                $howToEarn = match ($badge->slug) {
                    'primer-abono' => 'El integrante recibe su primer abono o carga saldo por primera vez.',
                    'primer-pago-prestamo' => 'El integrante paga por primera vez una cuota de prestamo.',
                    'primera-tarea-completada' => 'El padre confirma por primera vez una tarea completada por el integrante.',
                    default => 'Esta insignia se desbloquea automaticamente cuando se cumple su regla en el sistema.',
                };
            @endphp
            <article class="da-badge-card">
                <div class="da-badge-card__visual">
                    @if($badge->image_path)
                        <img src="{{ route('badges.image', ['badge' => $badge->slug]) }}" alt="{{ $badge->title }}">
                    @else
                        <div class="da-badge-placeholder">
                            <span aria-hidden="true">&#9671;</span>
                            <small>Sin imagen</small>
                        </div>
                    @endif
                </div>

                <div class="da-badge-card__content">
                    <div class="da-badge-card__heading">
                        <span class="da-label">Logro autom&aacute;tico</span>
                        <span class="da-badge-points">{{ $badge->points_reward }} puntos</span>
                    </div>
                    <h3>{{ $badge->title }}</h3>
                    <p class="da-badge-description">{{ $badge->description }}</p>

                    <div class="da-badge-rule">
                        <span>C&oacute;mo se consigue</span>
                        <p>{{ $howToEarn }}</p>
                    </div>

                    <form method="POST" action="{{ route('admin.badges.image.update', ['badge' => $badge->id]) }}" enctype="multipart/form-data" class="da-badge-form">
                        @csrf
                        <label class="da-file-field">
                            <span>Nueva imagen</span>
                            <input type="file" name="image" accept="image/png,image/jpeg,image/webp" required>
                            <small>PNG, JPG o WEBP. M&aacute;ximo 1 MB.</small>
                        </label>
                        <button class="da-badge-submit" type="submit">Cambiar imagen</button>
                    </form>
                </div>
            </article>
        @endforeach
    </section>
</main>
</body>
</html>
