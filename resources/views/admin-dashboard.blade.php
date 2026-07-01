<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard</title>
    <link rel="stylesheet" href="/css/styles.css">
    <style>
        .admin-badge-list{display:grid;gap:1rem;margin-top:1.5rem;}
        .admin-badge-card{padding:1rem;border:1px solid #d8e0f2;border-radius:.85rem;background:#f8fafc;color:#10234a;}
        .admin-badge-row{display:grid;grid-template-columns:96px minmax(0,1fr);gap:1rem;align-items:start;}
        .admin-badge-image{width:96px;height:96px;border-radius:.9rem;object-fit:cover;background:#e9eef8;border:1px solid #d8e0f2;}
        .admin-badge-placeholder{display:grid;place-items:center;text-align:center;color:#6b7a99;font-size:.78rem;font-weight:700;}
        .admin-badge-title{margin:0;color:#001f5b;font-size:1rem;font-weight:800;}
        .admin-badge-meta{margin:.25rem 0 0;color:#5d6b85;font-size:.9rem;line-height:1.4;}
        .admin-badge-form{margin-top:.85rem;display:flex;flex-wrap:wrap;gap:.65rem;align-items:center;}
        .admin-badge-form .input{max-width:280px;}
        @media (max-width:520px){.admin-badge-row{grid-template-columns:1fr}.admin-badge-image{width:100%;height:150px;}}
    </style>
</head>
<body class="app-bg">
<section class="page card">
    <p class="brand">DOMUS</p>
    <h1>Admin Dashboard</h1>
    <p class="subtitle">Sesion web robusta activa para administracion interna.</p>
    @if(session('status'))
        <div class="feedback-box feedback-success mt-1">{{ session('status') }}</div>
    @endif
    @if($errors->any())
        <div class="feedback-box feedback-error mt-1">{{ $errors->first() }}</div>
    @endif

    <section class="admin-badge-list">
        <div>
            <h2 class="quick-card-title">Insignias</h2>
            <p class="quick-card-subtitle">Estas reglas no se editan aqui porque dependen del codigo. El superadmin solo puede cambiar la imagen publica.</p>
        </div>
        @foreach($badges as $badge)
            @php
                $howToEarn = match ($badge->slug) {
                    'primer-abono' => 'El integrante recibe su primer abono o carga saldo por primera vez.',
                    'primer-pago-prestamo' => 'El integrante paga por primera vez una cuota de prestamo.',
                    'primera-tarea-completada' => 'El padre confirma por primera vez una tarea completada por el integrante.',
                    default => 'Esta insignia se desbloquea automaticamente cuando se cumple su regla en el sistema.',
                };
            @endphp
            <article class="admin-badge-card">
                <div class="admin-badge-row">
                    @if($badge->image_path)
                        <img class="admin-badge-image" src="{{ route('badges.image', ['badge' => $badge->slug]) }}" alt="{{ $badge->title }}">
                    @else
                        <div class="admin-badge-image admin-badge-placeholder">Sin imagen</div>
                    @endif
                    <div>
                        <p class="admin-badge-title">{{ $badge->title }}</p>
                        <p class="admin-badge-meta">{{ $badge->description }}</p>
                        <p class="admin-badge-meta"><strong>Como se consigue:</strong> {{ $howToEarn }}</p>
                        <p class="admin-badge-meta"><strong>Puntos:</strong> {{ $badge->points_reward }}</p>
                        <form method="POST" action="{{ route('admin.badges.image.update', ['badge' => $badge->id]) }}" enctype="multipart/form-data" class="admin-badge-form">
                            @csrf
                            <input class="input" type="file" name="image" accept="image/png,image/jpeg,image/webp" required>
                            <button class="btn gold-btn btn-inline" type="submit">Cambiar imagen</button>
                        </form>
                    </div>
                </div>
            </article>
        @endforeach
    </section>

    <form method="POST" action="{{ route('admin.logout') }}" class="mt-2">
        @csrf
        <button class="btn gold-btn btn-inline" type="submit">Cerrar sesion admin</button>
    </form>
</section>
</body>
</html>
