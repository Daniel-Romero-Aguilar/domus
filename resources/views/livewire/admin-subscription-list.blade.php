<div>
    @if(session('trial_updated'))
        <div class="da-notice da-notice--success" role="status">{{ session('trial_updated') }}</div>
    @endif
    <section class="da-family-card" aria-label="Filtros de usuarios">
        <div class="da-panel">
            <div class="da-badge-form">
                <label class="da-file-field">
                    <span>Buscar por nombre o correo</span>
                    <input type="search" wire:model.live.debounce.300ms="search" placeholder="Nombre o correo" autocomplete="off">
                </label>
                <label class="da-file-field">
                    <span>Estado</span>
                    <select wire:model.live="status">
                        <option value="all">Todos</option>
                        <option value="subscribed">Suscrito</option>
                        <option value="not_subscribed">No suscrito</option>
                        <option value="free">Cuentas gratis</option>
                    </select>
                </label>
            </div>
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
                    <div class="da-member" wire:key="subscription-user-{{ $entry['user']->id }}">
                        <span class="da-member__avatar">{{ mb_strtoupper(mb_substr($entry['user']->name, 0, 1)) }}</span>
                        <div class="da-member__identity">
                            <strong>{{ $entry['user']->name }}</strong>
                            <small>ID {{ $entry['user']->id }} &middot; {{ $entry['user']->email ?: ($entry['user']->username ? '@'.$entry['user']->username : 'Sin correo') }}</small>
                            <small>{{ $entry['product_id'] ? 'Producto: '.$entry['product_id'] : 'Sin compra real verificada' }}</small>
                            @if($entry['expires_at'])
                                <small>{{ $entry['expires_at']->isFuture() ? 'Vence' : 'Venci&oacute;' }} {{ $entry['expires_at']->format('d/m/Y H:i') }} UTC</small>
                            @endif
                            @if($entry['verified_at'])
                                <small>Verificado {{ $entry['verified_at']->format('d/m/Y H:i') }} UTC</small>
                            @endif
                            @if($entry['trial_ends_at'])
                                <small>Gratis hasta {{ $entry['trial_ends_at']->format('d/m/Y H:i:s') }} UTC &middot; {{ $entry['trial_ends_at']->isFuture() ? 'Prueba vigente' : 'Prueba vencida' }}</small>
                            @elseif($entry['user']->role !== 'parent')
                                <small>El acceso depende de su cuenta familiar.</small>
                            @endif
                            @if($editingUserId === $entry['user']->id)
                                <form wire:submit="saveTrial" class="da-badge-form">
                                    <label class="da-file-field">
                                        <span>Vencimiento gratuito (UTC)</span>
                                        <input type="datetime-local" wire:model="trialEndsAt" step="1" required>
                                    </label>
                                    @error('trialEndsAt')
                                        <small role="alert">{{ $message }}</small>
                                    @enderror
                                    <button class="da-badge-submit" type="submit" wire:loading.attr="disabled">Guardar vencimiento</button>
                                    <button class="da-badge-submit" type="button" wire:click="cancelEdit">Cancelar</button>
                                </form>
                            @endif
                        </div>
                        <span class="da-member__meta">
                            <span class="da-status {{ $entry['status_class'] }}">{{ $entry['status'] }}</span>
                            @if($entry['user']->role === 'parent')
                                <small>{{ $entry['user']->isEnabledFunctions() ? 'Funciones habilitadas' : 'Funciones bloqueadas' }}</small>
                                <button class="da-badge-submit" type="button" wire:click="editTrial({{ $entry['user']->id }})">Editar vencimiento</button>
                            @endif
                        </span>
                    </div>
                @empty
                    <p class="da-empty da-empty--compact">No hay usuarios con esos filtros.</p>
                @endforelse
            </div>
        </div>
    </section>

    <nav class="da-pagination" aria-label="Paginaci&oacute;n de usuarios">
        @if($users->onFirstPage())
            <span class="is-disabled">Anterior</span>
        @else
            <button type="button" wire:click="previousPage">Anterior</button>
        @endif
        <span class="da-pagination__current">P&aacute;gina {{ $users->currentPage() }} de {{ $users->lastPage() }}</span>
        @if($users->hasMorePages())
            <button type="button" wire:click="nextPage">Siguiente</button>
        @else
            <span class="is-disabled">Siguiente</span>
        @endif
    </nav>
</div>
