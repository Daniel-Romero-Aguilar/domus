<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LoanPayment extends Model
{
    protected $fillable = [
        'loan_id',
        'installment_number',
        'due_date',
        'status',
        'total_amount_cents',
        'principal_amount_cents',
        'interest_amount_cents',
        'paid_at',
    ];

    protected $casts = [
        'installment_number' => 'integer',
        'due_date' => 'date',
        'total_amount_cents' => 'integer',
        'principal_amount_cents' => 'integer',
        'interest_amount_cents' => 'integer',
        'paid_at' => 'datetime',
    ];

    protected $appends = [
        'total_amount',
        'principal_amount',
        'interest_amount',
        'total_amount_display',
        'principal_amount_display',
        'interest_amount_display',
        'status_label',
        'is_payable_today',
    ];

    public function loan(): BelongsTo
    {
        return $this->belongsTo(Loan::class);
    }

    public function getTotalAmountAttribute(): float
    {
        return $this->total_amount_cents / 100;
    }

    public function getPrincipalAmountAttribute(): float
    {
        return $this->principal_amount_cents / 100;
    }

    public function getInterestAmountAttribute(): float
    {
        return $this->interest_amount_cents / 100;
    }

    public function getTotalAmountDisplayAttribute(): string
    {
        return $this->moneyFromCents((int) $this->total_amount_cents);
    }

    public function getPrincipalAmountDisplayAttribute(): string
    {
        return $this->moneyFromCents((int) $this->principal_amount_cents);
    }

    public function getInterestAmountDisplayAttribute(): string
    {
        return $this->moneyFromCents((int) $this->interest_amount_cents);
    }

    public function getStatusLabelAttribute(): string
    {
        return match ($this->status) {
            'paid' => 'Pagado',
            'overdue' => 'Vencido',
            default => 'Pendiente',
        };
    }

    public function getIsPayableTodayAttribute(): bool
    {
        if ($this->status === 'paid' || ! $this->due_date) {
            return false;
        }

        return $this->due_date->startOfDay()->lessThanOrEqualTo(now()->startOfDay());
    }

    private function moneyFromCents(int $cents): string
    {
        return '$'.number_format($cents / 100, 2, '.', ',');
    }
}
