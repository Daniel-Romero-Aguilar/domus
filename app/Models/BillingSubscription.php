<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class BillingSubscription extends Model
{
    public const ELIGIBLE_STATES = [
        'SUBSCRIPTION_STATE_ACTIVE',
        'SUBSCRIPTION_STATE_IN_GRACE_PERIOD',
        // Cancellation stops renewal; access lasts through the paid period.
        'SUBSCRIPTION_STATE_CANCELED',
    ];

    protected $table = 'billing_demo_subscriptions';

    protected $hidden = ['purchase_token', 'purchase_token_hash', 'linked_purchase_token_hash'];

    protected function casts(): array
    {
        return [
            'acknowledged' => 'boolean',
            'auto_renew_enabled' => 'boolean',
            'expires_at' => 'immutable_datetime',
            'verified_at' => 'immutable_datetime',
            'first_verified_at' => 'immutable_datetime',
            'superseded_at' => 'immutable_datetime',
            'sync_attempts' => 'integer',
            'sync_started_at' => 'immutable_datetime',
            'next_sync_at' => 'immutable_datetime',
            'sync_stopped_at' => 'immutable_datetime',
        ];
    }

    public function scopePremium(Builder $query): void
    {
        $query->where('acknowledged', true)
            ->whereIn('google_state', self::ELIGIBLE_STATES)
            ->where('expires_at', '>', now('UTC'));
    }

    public function isEligible(): bool
    {
        return $this->superseded_at === null
            && in_array($this->google_state, self::ELIGIBLE_STATES, true)
            && ($this->expires_at?->isFuture() ?? false);
    }

    public function isPremium(): bool
    {
        return $this->acknowledged === true && $this->isEligible();
    }

    public function graceExpiresAt(): ?CarbonImmutable
    {
        $days = (int) config('access.subscription_grace_days');
        if ($days <= 0 || $this->superseded_at !== null || $this->expires_at === null
            || ! in_array($this->google_state, self::ELIGIBLE_STATES, true)) {
            return null;
        }

        if ($this->acknowledged === false && $this->first_verified_at !== null) {
            // A Google-validated purchase may await acknowledgement, never a client-only token.
            return $this->first_verified_at->addDays($days)->min($this->expires_at);
        }

        if ($this->acknowledged === true && $this->auto_renew_enabled !== false
            && $this->google_state !== 'SUBSCRIPTION_STATE_CANCELED') {
            // Anchor to the paid period, so repeated failures cannot extend the allowance.
            return $this->expires_at->addDays($days);
        }

        return null;
    }

    public function isInGracePeriod(): bool
    {
        return ! $this->isPremium() && ($this->graceExpiresAt()?->isFuture() ?? false);
    }

    public function allowsAccess(): bool
    {
        return $this->isPremium() || $this->isInGracePeriod();
    }
}
