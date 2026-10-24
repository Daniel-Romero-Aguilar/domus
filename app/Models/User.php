<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Support\BalanceHelper;
use Carbon\CarbonImmutable;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Str;
use Laravel\Fortify\TwoFactorAuthenticatable;
use Laravel\Jetstream\HasProfilePhoto;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens;

    /** @use HasFactory<UserFactory> */
    use HasFactory;

    use HasProfilePhoto;
    use Notifiable;
    use TwoFactorAuthenticatable;

    protected $attributes = [
        'role' => 'parent',
    ];

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'email',
        'username',
        'role',
        'password',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
        'remember_token',
        'two_factor_recovery_codes',
        'two_factor_secret',
        'trial_ends_at',
        'currentSubscription',
    ];

    /**
     * The accessors to append to the model's array form.
     *
     * @var array<int, string>
     */
    protected $appends = [
        'profile_photo_url',
        'balance_cents',
        'balance_display',
    ];

    protected static function booted(): void
    {
        static::creating(function (User $user) {
            $user->billing_account_id = (string) Str::uuid();
        });
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'trial_ends_at' => 'immutable_datetime',
        ];
    }

    public function currentSubscription(): HasOne
    {
        return $this->hasOne(BillingSubscription::class)->ofMany(
            ['expires_at' => 'max', 'verified_at' => 'max', 'id' => 'max'],
            fn (Builder $query) => $query->where('product_id', config('billing_demo.product_id'))->whereNull('superseded_at'),
        );
    }

    public function accessAccount(): ?self
    {
        if ($this->role === 'parent') {
            return $this;
        }

        // Family members share the owner's access and never start a new trial.
        return FamilyMember::query()->where('user_id', $this->id)->with('parent')->first()?->parent;
    }

    public function trialExpiresAt(): ?CarbonImmutable
    {
        return $this->trial_ends_at
            ?? $this->created_at?->toImmutable()->addDays(max(0, (int) config('access.trial_days')));
    }

    public function isEnabledFunctions(): bool
    {
        if (! $this->exists) {
            return false;
        }

        $account = $this->accessAccount();

        return $account !== null && (
            ($account->trialExpiresAt()?->isFuture() ?? false)
            || ($account->currentSubscription?->allowsAccess() ?? false)
        );
    }

    public function getIsEnabledFunctionsAttribute(): bool
    {
        return $this->isEnabledFunctions();
    }

    public function getTrialExpiresAtAttribute(): ?string
    {
        return $this->accessAccount()?->trialExpiresAt()?->toIso8601String();
    }

    public function getHasActiveSubscriptionAttribute(): bool
    {
        return $this->accessAccount()?->currentSubscription?->isPremium() ?? false;
    }

    public function getAutoRenewEnabledAttribute(): ?bool
    {
        $subscription = $this->accessAccount()?->currentSubscription;

        return $subscription?->isPremium() ? $subscription->auto_renew_enabled : null;
    }

    public function getIsPremiumGracePeriodAttribute(): bool
    {
        return $this->accessAccount()?->currentSubscription?->isInGracePeriod() ?? false;
    }

    public function getPremiumGraceEndsAtAttribute(): ?string
    {
        $subscription = $this->accessAccount()?->currentSubscription;

        return $subscription?->isInGracePeriod() ? $subscription->graceExpiresAt()->toIso8601String() : null;
    }

    public function getPremiumExpiresAtAttribute(): ?string
    {
        $account = $this->accessAccount();
        if ($account === null) {
            return null;
        }

        // Keep the last known real purchase date, including expired purchases.
        $subscription = BillingSubscription::query()
            ->where('user_id', $account->getKey())
            ->where('product_id', config('billing_demo.product_id'))
            ->where('google_state', '!=', 'DEMO_CONFIRMED')
            ->whereNull('superseded_at')
            ->whereNotNull('expires_at')
            ->orderByDesc('expires_at')
            ->orderByDesc('verified_at')
            ->orderByDesc('id')
            ->first();

        return $subscription?->expires_at?->toIso8601String();
    }

    public function balance(): HasOne
    {
        return $this->hasOne(Balance::class);
    }

    public function domusNotifications(): HasMany
    {
        return $this->hasMany(DomusNotification::class);
    }

    public function lessonCompletions(): HasMany
    {
        return $this->hasMany(LessonCompletion::class);
    }

    public function domusMissions(): BelongsToMany
    {
        return $this->belongsToMany(DomusMission::class, 'domus_mission_user')
            ->withPivot(['awarded_points', 'completed_at', 'meta'])
            ->withTimestamps();
    }

    public function domusMissionCompletions(): HasMany
    {
        return $this->hasMany(DomusMissionUser::class);
    }

    public function getBalanceCentsAttribute(): int
    {
        return BalanceHelper::cents($this);
    }

    public function getBalanceDisplayAttribute(): string
    {
        return BalanceHelper::display($this);
    }

    public function balanceCents(): int
    {
        return $this->balance_cents;
    }
}
