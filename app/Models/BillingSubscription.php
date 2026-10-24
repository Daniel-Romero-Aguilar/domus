<?php

namespace App\Models;

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

    protected $hidden = ['purchase_token', 'purchase_token_hash'];

    protected function casts(): array
    {
        return [
            'acknowledged' => 'boolean',
            'expires_at' => 'immutable_datetime',
            'verified_at' => 'immutable_datetime',
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
        return in_array($this->google_state, self::ELIGIBLE_STATES, true)
            && ($this->expires_at?->isFuture() ?? false);
    }

    public function isPremium(): bool
    {
        return $this->acknowledged === true && $this->isEligible();
    }
}
