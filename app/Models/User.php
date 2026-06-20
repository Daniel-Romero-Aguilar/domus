<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use App\Models\Balance;
use App\Support\BalanceHelper;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
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
        ];
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
