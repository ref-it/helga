<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;

#[Fillable([
    'sub',
    'name',
    'given_name',
    'family_name',
    'email',
    'phone',
    'avatar',
    'groups',
])]
#[Hidden([
    'sub',
    'remember_token',
])]
class User extends Authenticatable
{
    use HasFactory;

    protected function casts(): array
    {
        return [
            'groups' => 'array',
        ];
    }

    /**
     * Plans owned by this user.
     */
    public function plans(): HasMany
    {
        return $this->hasMany(Plan::class);
    }

    /**
     * Check if the user is currently a member of the given OIDC group.
     */
    public function hasGroup(string $group): bool
    {
        return in_array($group, $this->groups ?? [], true);
    }

    /**
     * Check if the user is a member of one of the OIDC groups configured
     * (via OIDC_ADMIN_GROUPS) to have admin rights on every plan.
     */
    public function isGlobalAdmin(): bool
    {
        return count(array_intersect($this->groups ?? [], config('services.oidc.admin_groups'))) > 0;
    }
}
