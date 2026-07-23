<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Correos de landing | Admin</title>
    <link rel="stylesheet" href="/css/styles.css">
    <style>
        .admin-menu{display:flex;flex-wrap:wrap;gap:.65rem;margin:1rem 0 1.5rem;padding-bottom:1rem;border-bottom:1px solid #d8e0f2;}
        .admin-menu a{color:#003c91;font-weight:700;text-decoration:none;}
        .admin-menu a:hover{text-decoration:underline;}
        .admin-table-wrap{overflow-x:auto;margin-top:1.5rem;}
        .admin-table{width:100%;border-collapse:collapse;color:#10234a;font-size:.92rem;}
        .admin-table th,.admin-table td{padding:.75rem;border-bottom:1px solid #d8e0f2;text-align:left;vertical-align:top;}
        .admin-table th{background:#f1f5fb;font-size:.8rem;text-transform:uppercase;letter-spacing:.03em;}
        .admin-status{font-weight:700;white-space:nowrap;}
        .admin-status--yes{color:#168548;}
        .admin-status--no{color:#b42318;}
        .admin-pagination{display:flex;flex-wrap:wrap;gap:.4rem;margin-top:1rem;}
        .admin-pagination a,.admin-pagination span{padding:.4rem .65rem;border:1px solid #d8e0f2;border-radius:.35rem;color:#003c91;text-decoration:none;}
        .admin-pagination .active{background:#003c91;color:#fff;}
        .admin-actions{display:flex;flex-wrap:wrap;gap:.7rem;align-items:center;}
    </style>
</head>
<body class="app-bg">
<section class="page card">
    <p class="brand">DOMUS</p>
    <h1>Correos de landing</h1>
    <p class="subtitle">Registros de familias interesadas en DOMUS.</p>

    <nav class="admin-menu" aria-label="Menu de administracion">
        <a href="{{ route('admin.dashboard') }}">Insignias</a>
        <a href="{{ route('admin.landing-emails') }}">Correos de landing</a>
    </nav>

    <div class="admin-actions">
        <a class="btn gold-btn btn-inline" href="{{ route('admin.landing-emails.export') }}">Descargar CSV</a>
        <span class="quick-card-subtitle">{{ $emails->total() }} registro(s)</span>
    </div>

    <div class="admin-table-wrap">
        <table class="admin-table">
            <thead>
                <tr>
                    <th>Correo</th>
                    <th>Registrado</th>
                    <th>Cuenta creada</th>
                </tr>
            </thead>
            <tbody>
                @forelse($emails as $landingEmail)
                    <tr>
                        <td>{{ $landingEmail->email }}</td>
                        <td>{{ optional($landingEmail->created_at)->format('d/m/Y H:i') }}</td>
                        <td class="admin-status {{ $landingEmail->account_created ? 'admin-status--yes' : 'admin-status--no' }}">
                            {{ $landingEmail->account_created ? '✓ Sí' : '✕ No' }}
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="3">Todavía no hay correos registrados.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @if($emails->hasPages())
        <nav class="admin-pagination" aria-label="Paginacion">
            @if($emails->onFirstPage())
                <span>Anterior</span>
            @else
                <a href="{{ $emails->previousPageUrl() }}">Anterior</a>
            @endif

            @foreach(range(max(1, $emails->currentPage() - 2), min($emails->lastPage(), $emails->currentPage() + 2)) as $page)
                @if($page == $emails->currentPage())
                    <span class="active">{{ $page }}</span>
                @else
                    <a href="{{ $emails->url($page) }}">{{ $page }}</a>
                @endif
            @endforeach

            @if($emails->hasMorePages())
                <a href="{{ $emails->nextPageUrl() }}">Siguiente</a>
            @else
                <span>Siguiente</span>
            @endif
        </nav>
    @endif

    <form method="POST" action="{{ route('admin.logout') }}" class="mt-2">
        @csrf
        <button class="btn gold-btn btn-inline" type="submit">Cerrar sesion admin</button>
    </form>
</section>
</body>
</html>
