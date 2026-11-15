<?php

namespace App\Models;

use App\Modules\Access\Enums\LoginType;
use App\Modules\Access\Enums\Permission;
use App\Modules\Access\Models\Role;
use App\Modules\Access\Models\UserPermission;
use App\Modules\Distribution\Models\MedicalRep;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

#[Fillable(['name', 'email', 'password', 'login_type', 'role_id', 'is_active', 'medical_rep_id', 'distributor_ids'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, Notifiable;

    /** @var list<string>|null this request's permissions, worked out once */
    private ?array $permissionCache = null;

    public function role(): BelongsTo
    {
        return $this->belongsTo(Role::class);
    }

    public function extraPermissionRows(): HasMany
    {
        return $this->hasMany(UserPermission::class);
    }

    public function medicalRep(): BelongsTo
    {
        return $this->belongsTo(MedicalRep::class);
    }

    public function isOwner(): bool
    {
        return ! $this->isMedicalRep() && (bool) $this->role?->is_owner;
    }

    public function isMedicalRep(): bool
    {
        return $this->login_type === LoginType::MedicalRep;
    }

    /** "Owner", "Accounts", "Medical rep"… for headers and lists. */
    public function roleLabel(): string
    {
        return $this->isMedicalRep() ? LoginType::MedicalRep->label() : ($this->role?->name ?? 'No role');
    }

    /**
     * Owner: everything. Medical rep: nothing (they only get My territory). Staff: their role's
     * permissions plus any extras given to them. @return list<string>
     */
    public function permissionValues(): array
    {
        if ($this->permissionCache !== null) {
            return $this->permissionCache;
        }

        if ($this->isMedicalRep()) {
            return $this->permissionCache = [];
        }
        if ($this->isOwner()) {
            return $this->permissionCache = Permission::values();
        }

        return $this->permissionCache = Permission::normalise([
            ...($this->role?->permissionValues() ?? []),
            ...$this->extraPermissionValues(),
        ]);
    }

    /** Permissions given to this person on top of their role. @return list<string> */
    public function extraPermissionValues(): array
    {
        return $this->extraPermissionRows->pluck('permission')->all();
    }

    public function hasPermission(Permission|string $permission): bool
    {
        $value = $permission instanceof Permission ? $permission->value : $permission;

        return in_array($value, $this->permissionValues(), true);
    }

    /** Call after changing someone's role or extras in the same request. */
    public function forgetPermissions(): static
    {
        $this->permissionCache = null;
        $this->unsetRelation('role')->unsetRelation('extraPermissionRows');

        return $this;
    }

    /** Distributors this person may see, or null for all. The owner always sees all. @return list<int>|null */
    public function visibleDistributorIds(): ?array
    {
        if ($this->isOwner() || empty($this->distributor_ids)) {
            return null;
        }

        return array_map('intval', $this->distributor_ids);
    }

    public function canSeeDistributor(int $distributorId): bool
    {
        $ids = $this->visibleDistributorIds();

        return $ids === null || in_array($distributorId, $ids, true);
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'login_type' => LoginType::class,
            'is_active' => 'boolean',
            'distributor_ids' => 'array',
        ];
    }
}
