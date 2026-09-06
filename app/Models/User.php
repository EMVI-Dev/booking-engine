<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Enums\OperatorUserRole;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Laravel\Fortify\Contracts\PasskeyUser;
use Laravel\Fortify\PasskeyAuthenticatable;
use Laravel\Fortify\TwoFactorAuthenticatable;

/**
 * @property string $id
 * @property string $name
 * @property string $email
 * @property Carbon|null $email_verified_at
 * @property string $password
 * @property bool $is_admin
 * @property string|null $two_factor_secret
 * @property string|null $two_factor_recovery_codes
 * @property Carbon|null $two_factor_confirmed_at
 * @property string|null $remember_token
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['name', 'email', 'password', 'is_admin'])]
#[Hidden(['password', 'two_factor_secret', 'two_factor_recovery_codes', 'remember_token'])]
class User extends Authenticatable implements PasskeyUser
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, HasUlids, Notifiable, PasskeyAuthenticatable, TwoFactorAuthenticatable;

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
            'is_admin' => 'boolean',
        ];
    }

    /**
     * Check if user is a platform administrator.
     */
    public function isAdmin(): bool
    {
        return (bool) ($this->is_admin ?? false) || $this->email === 'admin@emvi.dev';
    }

    /**
     * Get the user's initials
     */
    public function initials(): string
    {
        $initials = Str::initials($this->name, true);

        return Str::length($initials) > 1
            ? Str::substr($initials, 0, 1).Str::substr($initials, -1)
            : $initials;
    }

    /**
     * @return BelongsToMany<Operator, $this, OperatorUser>
     */
    public function operators(): BelongsToMany
    {
        return $this->belongsToMany(Operator::class, 'operator_users')
            ->using(OperatorUser::class)
            ->withPivot('role')
            ->withTimestamps();
    }

    /**
     * Resolve the active operator for the current session.
     * Supports session-based operator selection and impersonation for platform administrators.
     */
    public function currentOperator(): ?Operator
    {
        if ($this->isAdmin() && session()->has('admin_impersonated_operator_id')) {
            $operator = Operator::whereKey(session('admin_impersonated_operator_id'))->first();
            if ($operator) {
                return $operator;
            }
        }

        return $this->operators()->first() ?? ($this->isAdmin() ? Operator::query()->first() : null);
    }

    /**
     * @deprecated Use operators() instead.
     *
     * @return BelongsToMany<Operator, $this, OperatorUser>
     */
    public function agents(): BelongsToMany
    {
        return $this->operators();
    }

    /**
     * @deprecated Use currentOperator() instead.
     */
    public function currentAgent(): ?Operator
    {
        return $this->currentOperator();
    }

    public function roleOn(?Operator $operator): ?OperatorUserRole
    {
        if (! $operator) {
            return null;
        }

        if ($this->isAdmin()) {
            return OperatorUserRole::Owner;
        }

        $membership = $this->operators()->whereKey($operator->id)->first();

        $role = $membership?->pivot?->role;

        return $role instanceof OperatorUserRole ? $role : null;
    }

    public function canOperate(?Operator $operator, string $ability): bool
    {
        if ($this->isAdmin()) {
            return true;
        }

        return $this->roleOn($operator)?->allows($ability) ?? false;
    }
}
