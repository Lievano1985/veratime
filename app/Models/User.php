<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Str;

class User extends Authenticatable // implements MustVerifyEmail
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'status',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
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

    public function companies(): BelongsToMany
    {
        return $this->belongsToMany(Company::class)
            ->withPivot(['role_id', 'status', 'is_default'])
            ->withTimestamps();
    }

    public function activeCompanies(): BelongsToMany
    {
        return $this->companies()
            ->wherePivot('status', 'active')
            ->where('companies.status', 'active');
    }

    public function belongsToCompany(Company $company): bool
    {
        return $this->activeCompanies()->whereKey($company->id)->exists();
    }

    public function defaultCompany(): ?Company
    {
        return $this->activeCompanies()
            ->wherePivot('is_default', true)
            ->first()
            ?? $this->activeCompanies()->first();
    }

    public function roleKeyForCompany(Company $company): ?string
    {
        $company = $this->activeCompanies()
            ->whereKey($company->id)
            ->first();

        if (! $company?->pivot?->role_id) {
            return null;
        }

        return Role::query()->whereKey($company->pivot->role_id)->value('key');
    }

    /**
     * Get the user's initials
     */
    public function initials(): string
    {
        return Str::of($this->name)
            ->explode(' ')
            ->map(fn (string $name) => Str::of($name)->substr(0, 1))
            ->implode('');
    }

    public function sourceTimeEvents(): HasMany
    {
        return $this->hasMany(TimeEvent::class, 'source_user_id');
    }
    public function operationalScopeAssignments(): HasMany
    {
        return $this->hasMany(OperationalScopeAssignment::class);
    }

    public function createdScheduleProfileAssignments(): HasMany
    {
        return $this->hasMany(ScheduleProfileAssignment::class, 'created_by');
    }
}
