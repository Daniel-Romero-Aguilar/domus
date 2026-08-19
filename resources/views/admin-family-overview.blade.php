<!DOCTYPE html>
<html lang="es-MX">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="theme-color" content="#0d1b3d">
    <title>Familias | Admin DOMUS</title>
    <link rel="stylesheet" href="{{ asset('css/admin.css') }}">
</head>
<body class="domus-admin">
@php
    $moneyFromCents = static fn (int $cents): string => '$'.number_format($cents / 100, 2, '.', ',');
    $moneyFromPesos = static fn (int $pesos): string => '$'.number_format($pesos, 2, '.', ',');
    $loanStatusLabels = [
        'pending' => 'Solicitado',
        'offered' => 'Ofrecido',
        'approved' => 'Activo',
        'paid' => 'Pagado',
        'rejected' => 'Rechazado',
    ];
    $boxStatusLabels = ['active' => 'Activa', 'completed' => 'Finalizada'];
@endphp

<header class="da-header">
    <div class="da-shell da-header__inner">
        <a class="da-brand" href="{{ route('admin.families.index') }}" aria-label="DOMUS administración">
            <img src="{{ asset('img/domus_logo.png') }}" alt="DOMUS">
            <span>Administración</span>
        </a>
        <nav class="da-navigation" aria-label="Navegación administrativa">
            <a class="is-active" href="{{ route('admin.families.index') }}">Familias</a>
            <a href="{{ route('admin.dashboard') }}">Insignias</a>
            <a href="{{ route('admin.landing-emails') }}">Correos</a>
        </nav>
        <form method="POST" action="{{ route('admin.logout') }}">
            @csrf
            <button class="da-logout" type="submit">Cerrar sesión</button>
        </form>
    </div>
</header>

<main class="da-shell da-main">
    <section class="da-hero" aria-labelledby="admin-overview-title">
        <div>
            <span class="da-eyebrow"><span aria-hidden="true">✦</span> Vista interna de solo lectura</span>
            <h1 id="admin-overview-title">Familias en DOMUS</h1>
            <p>Una primera mirada a las cuentas familiares y su actividad financiera, sin acciones de edición.</p>
        </div>
        <div class="da-admin-chip">
            <span class="da-admin-chip__avatar">{{ mb_strtoupper(mb_substr($admin->name, 0, 1)) }}</span>
            <span><small>Sesión de administrador</small><strong>{{ $admin->name }}</strong></span>
        </div>
    </section>

    <section class="da-stats" aria-label="Resumen general">
        <article class="da-stat da-stat--blue">
            <span class="da-stat__icon" aria-hidden="true">⌂</span>
            <div><strong>{{ number_format($platformSummary['parents']) }}</strong><span>Padres registrados</span></div>
        </article>
        <article class="da-stat da-stat--purple">
            <span class="da-stat__icon" aria-hidden="true">◎</span>
            <div><strong>{{ number_format($platformSummary['members']) }}</strong><span>Familiares vinculados</span></div>
        </article>
        <article class="da-stat da-stat--gold">
            <span class="da-stat__icon" aria-hidden="true">↗</span>
            <div><strong>{{ number_format($platformSummary['open_loans']) }}</strong><span>Préstamos en curso</span></div>
        </article>
        <article class="da-stat da-stat--green">
            <span class="da-stat__icon" aria-hidden="true">◇</span>
            <div><strong>{{ number_format($platformSummary['active_savings_boxes']) }}</strong><span>Cajas activas</span></div>
        </article>
    </section>

    <section class="da-section-heading">
        <div>
            <span class="da-kicker">Directorio familiar</span>
            <h2>Padres y actividad reciente</h2>
        </div>
        <span class="da-result-count">{{ $families->total() }} {{ $families->total() === 1 ? 'familia' : 'familias' }}</span>
    </section>

    <section class="da-family-list" aria-label="Familias registradas">
        @forelse($families as $family)
            @php
                $parent = $family['parent'];
                $members = $family['members'];
                $loanSummary = $family['loans']['summary'];
                $recentLoans = $family['loans']['recent'];
                $boxSummary = $family['savings_boxes']['summary'];
                $recentBoxes = $family['savings_boxes']['recent'];
            @endphp
            <article class="da-family-card">
                <header class="da-family-card__header">
                    <div class="da-person">
                        <span class="da-person__avatar">{{ mb_strtoupper(mb_substr($parent->name, 0, 1)) }}</span>
                        <div>
                            <span class="da-label">Cuenta principal</span>
                            <h3>{{ $parent->name }}</h3>
                            <p>{{ $parent->email ?: 'Sin correo' }}@if($parent->username) · {{ '@'.$parent->username }}@endif</p>
                        </div>
                    </div>
                    <dl class="da-family-totals">
                        <div><dt>Familiares</dt><dd>{{ $members->count() }}</dd></div>
                        <div><dt>Préstamos</dt><dd>{{ $loanSummary['total'] ?? 0 }}</dd></div>
                        <div><dt>Cajas</dt><dd>{{ $boxSummary['total'] ?? 0 }}</dd></div>
                        <div><dt>Alta</dt><dd>{{ optional($parent->created_at)->format('d/m/Y') }}</dd></div>
                    </dl>
                </header>

                <div class="da-family-card__body">
                    <section class="da-panel da-panel--members">
                        <div class="da-panel__heading">
                            <div><span class="da-label">Integrantes</span><h4>Familia vinculada</h4></div>
                            <span class="da-pill">{{ $members->count() }}</span>
                        </div>
                        <div class="da-member-list">
                            @forelse($members as $membership)
                                @php($member = $membership->user)
                                @if($member)
                                    <div class="da-member">
                                        <span class="da-member__avatar">{{ mb_strtoupper(mb_substr($member->name, 0, 1)) }}</span>
                                        <span class="da-member__identity">
                                            <strong>{{ $member->name }}</strong>
                                            <small>{{ $member->username ? '@'.$member->username : ($member->email ?: 'Sin usuario') }}</small>
                                        </span>
                                        <span class="da-member__meta">
                                            {{ $membership->is_minor ? 'Menor' : 'Integrante' }}
                                            <small>{{ $member->balance_display }}</small>
                                        </span>
                                    </div>
                                @endif
                            @empty
                                <p class="da-empty da-empty--compact">Este padre todavía no tiene familiares vinculados.</p>
                            @endforelse
                        </div>
                    </section>

                    <section class="da-panel">
                        <div class="da-panel__heading">
                            <div><span class="da-label">Préstamos</span><h4>Actividad reciente</h4></div>
                            <span class="da-pill da-pill--gold">{{ $loanSummary['open'] ?? 0 }} en curso</span>
                        </div>
                        <div class="da-mini-summary">
                            <span><small>Monto histórico</small><strong>{{ $moneyFromPesos((int) ($loanSummary['total_amount'] ?? 0)) }}</strong></span>
                            <span><small>Monto en curso</small><strong>{{ $moneyFromPesos((int) ($loanSummary['open_amount'] ?? 0)) }}</strong></span>
                        </div>
                        <div class="da-activity-list">
                            @forelse($recentLoans as $loan)
                                <div class="da-activity">
                                    <span>
                                        <strong>{{ $loan->child_name ?: 'Integrante eliminado' }}</strong>
                                        <small>{{ $loan->installments_count }} {{ (int) $loan->installments_count === 1 ? 'pago' : 'pagos' }} · vence {{ \Illuminate\Support\Carbon::parse($loan->due_date)->format('d/m/Y') }}</small>
                                    </span>
                                    <span class="da-activity__amount">
                                        <strong>{{ $moneyFromPesos((int) $loan->total_amount) }}</strong>
                                        <small class="da-status da-status--{{ $loan->status }}">{{ $loanStatusLabels[$loan->status] ?? ucfirst($loan->status) }}</small>
                                    </span>
                                </div>
                            @empty
                                <p class="da-empty da-empty--compact">Sin préstamos registrados.</p>
                            @endforelse
                        </div>
                    </section>

                    <section class="da-panel">
                        <div class="da-panel__heading">
                            <div><span class="da-label">Cajas de ahorro</span><h4>Planes recientes</h4></div>
                            <span class="da-pill da-pill--green">{{ $boxSummary['active'] ?? 0 }} activas</span>
                        </div>
                        <div class="da-mini-summary">
                            <span><small>Capital resguardado</small><strong>{{ $moneyFromCents((int) ($boxSummary['held_principal_cents'] ?? 0)) }}</strong></span>
                            <span><small>Participaciones</small><strong>{{ number_format((int) ($boxSummary['accounts'] ?? 0)) }}</strong></span>
                        </div>
                        <div class="da-activity-list">
                            @forelse($recentBoxes as $box)
                                @php($boxCutoff = $box->cutoff_at ?: $box->delivery_date)
                                <div class="da-activity">
                                    <span>
                                        <strong>{{ $box->name }}</strong>
                                        <small>{{ $box->account_count }} {{ (int) $box->account_count === 1 ? 'integrante' : 'integrantes' }} · corte {{ \Illuminate\Support\Carbon::parse($boxCutoff)->format('d/m/Y') }}</small>
                                    </span>
                                    <span class="da-activity__amount">
                                        <strong>{{ $moneyFromCents((int) $box->held_principal_cents) }}</strong>
                                        <small class="da-status da-status--{{ $box->status }}">{{ $boxStatusLabels[$box->status] ?? ucfirst($box->status) }}</small>
                                    </span>
                                </div>
                            @empty
                                <p class="da-empty da-empty--compact">Sin cajas de ahorro registradas.</p>
                            @endforelse
                        </div>
                    </section>
                </div>
            </article>
        @empty
            <article class="da-empty">
                <span aria-hidden="true">⌂</span>
                <h3>Todavía no hay familias</h3>
                <p>Los padres registrados aparecerán aquí con sus integrantes y resúmenes.</p>
            </article>
        @endforelse
    </section>

    @if($families->hasPages())
        <nav class="da-pagination" aria-label="Paginación de familias">
            @if($families->onFirstPage())
                <span class="is-disabled">← Anterior</span>
            @else
                <a href="{{ $families->previousPageUrl() }}">← Anterior</a>
            @endif

            <span class="da-pagination__current">Página {{ $families->currentPage() }} de {{ $families->lastPage() }}</span>

            @if($families->hasMorePages())
                <a href="{{ $families->nextPageUrl() }}">Siguiente →</a>
            @else
                <span class="is-disabled">Siguiente →</span>
            @endif
        </nav>
    @endif
</main>
</body>
</html>
