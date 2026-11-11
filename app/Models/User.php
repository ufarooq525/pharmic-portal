<?php

namespace App\Models;

use App\Modules\Access\Enums\Role;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

#[Fillable(['name', 'email', 'password', 'role', 'is_active', 'medical_rep_id', 'distributor_ids'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, Notifiable;

    /** The owner can do everything; other roles only what is listed. Usage: $user->hasRole('accounts', 'data_entry'). */
    public function hasRole(string ...$roles): bool
    {
        return $this->role === Role::Owner || in_array($this->role?->value, $roles, true);
    }

    /** Distributors this person may see, or null for all. The owner always sees all. @return list<int>|null */
    public function visibleDistributorIds(): ?array
    {
        if ($this->role === Role::Owner || empty($this->distributor_ids)) {
            return null;
        }

        return array_map('intval', $this->distributor_ids);
    }

    public function canSeeDistributor(int $distributorId): bool
    {
        $ids = $this->visibleDistributorIds();

        return $ids === null || in_array($distributorId, $ids, true);
    }

    public function medicalRep(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(\App\Modules\Distribution\Models\MedicalRep::class);
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'role' => Role::class,
            'is_active' => 'boolean',
            'distributor_ids' => 'array',
        ];
    }
}
