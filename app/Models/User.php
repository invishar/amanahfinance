<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

#[Fillable(['email', 'phone', 'full_name', 'avatar_url', 'password_hash'])]
#[Hidden(['password_hash', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, HasUuids, Notifiable;

    /**
     * Users only have a created_at column; there is no updated_at.
     */
    const UPDATED_AT = null;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'last_login_at' => 'datetime',
            'created_at' => 'datetime',
            'is_admin' => 'boolean',
        ];
    }

    /**
     * Deliberately not in #[Fillable] -- this is a privilege flag with no
     * self-service update endpoint. Only ever set directly (tinker/seeder).
     */
    public function isAdmin(): bool
    {
        return $this->is_admin === true;
    }

    /**
     * Get the password for the user, which is stored in password_hash.
     */
    public function getAuthPassword(): ?string
    {
        return $this->password_hash;
    }

    /**
     * @return HasMany<FamilyMember, $this>
     */
    public function familyMemberships(): HasMany
    {
        return $this->hasMany(FamilyMember::class);
    }

    /**
     * @return HasMany<FamilyInvite, $this>
     */
    public function sentInvites(): HasMany
    {
        return $this->hasMany(FamilyInvite::class, 'invited_by');
    }
}
