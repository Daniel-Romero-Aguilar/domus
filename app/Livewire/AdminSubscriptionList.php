<?php

namespace App\Livewire;

use App\Models\Admin;
use App\Models\User;
use App\Support\AdminAbilities;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

class AdminSubscriptionList extends Component
{
    use WithPagination;

    #[Url(as: 'q')]
    public string $search = '';

    #[Url]
    public string $status = 'all';

    #[Locked]
    public ?int $editingUserId = null;

    public string $trialEndsAt = '';

    public function editTrial(int $userId): void
    {
        $user = User::query()->where('role', 'parent')->findOrFail($userId);
        $this->editingUserId = $user->id;
        $this->trialEndsAt = $user->trialExpiresAt()?->utc()->format('Y-m-d\TH:i:s') ?? '';
        $this->resetValidation();
    }

    public function cancelEdit(): void
    {
        $this->editingUserId = null;
        $this->trialEndsAt = '';
        $this->resetValidation();
    }

    public function saveTrial(): void
    {
        $this->validate([
            'trialEndsAt' => ['required', 'date', 'regex:/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}(?::\d{2})?$/'],
        ], [
            'trialEndsAt.required' => 'Indica la fecha de vencimiento.',
            'trialEndsAt.date' => 'Indica una fecha y hora válidas.',
            'trialEndsAt.regex' => 'Indica una fecha y hora válidas.',
        ]);

        $user = User::query()->where('role', 'parent')->findOrFail($this->editingUserId);
        $user->trial_ends_at = CarbonImmutable::parse($this->trialEndsAt, 'UTC');
        $user->save();
        $this->cancelEdit();
        session()->flash('trial_updated', 'Vencimiento gratuito actualizado.');
    }

    public function boot(): void
    {
        $admin = Auth::guard('admin_web')->user();
        abort_unless($admin instanceof Admin, 403);
        Gate::forUser($admin)->authorize(AdminAbilities::VIEW_SUBSCRIPTIONS);
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedStatus(): void
    {
        $this->resetPage();
    }

    public function render(): View
    {
        $query = User::query()->with('currentSubscription');
        $search = mb_substr(trim($this->search), 0, 100);

        if ($search !== '') {
            $query->where(function (Builder $query) use ($search): void {
                $like = '%'.$search.'%';
                $query->where('name', 'like', $like)
                    ->orWhere('email', 'like', $like);
            });
        }

        if (in_array($this->status, ['subscribed', 'not_subscribed', 'free'], true)) {
            $subscribed = fn (Builder $query) => $query->premium();
            if ($this->status === 'subscribed') {
                $query->whereHas('currentSubscription', $subscribed);
            } else {
                $query->whereDoesntHave('currentSubscription', $subscribed);
                if ($this->status === 'free') {
                    $query->where('role', 'parent');
                }
            }
        }

        $users = $query->orderByDesc('id')->paginate(10);
        $entries = $users->getCollection()->map(function (User $user): array {
            $subscription = $user->currentSubscription;
            $expiresAt = $subscription?->expires_at;
            $eligible = $subscription?->isEligible() ?? false;

            if ($subscription?->google_state === 'DEMO_CONFIRMED') {
                $status = 'Simulada';
                $statusClass = 'da-status--pending';
            } elseif ($eligible && $subscription->acknowledged === null) {
                $status = 'Pendiente de comprobar';
                $statusClass = 'da-status--pending';
            } elseif ($subscription?->isPremium()) {
                $status = 'Suscrito';
                $statusClass = 'da-status--active';
            } else {
                $status = 'No suscrito';
                $statusClass = 'da-status--rejected';
            }

            return [
                'user' => $user,
                'status' => $status,
                'status_class' => $statusClass,
                'trial_ends_at' => $user->role === 'parent' ? $user->trialExpiresAt() : null,
                'product_id' => $subscription?->google_state === 'DEMO_CONFIRMED' ? null : $subscription?->product_id,
                'expires_at' => $subscription?->google_state === 'DEMO_CONFIRMED' ? null : $expiresAt,
                'verified_at' => $subscription?->google_state === 'DEMO_CONFIRMED' || ! $subscription?->verified_at
                    ? null
                    : CarbonImmutable::parse($subscription->verified_at, 'UTC'),
            ];
        });

        return view('livewire.admin-subscription-list', [
            'users' => $users,
            'entries' => $entries,
        ]);
    }
}
