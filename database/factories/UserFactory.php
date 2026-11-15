<?php

namespace Database\Factories;

use App\Models\User;
use App\Modules\Access\Models\Role;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    /**
     * The current password being used by the factory.
     */
    protected static ?string $password;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password' => static::$password ??= Hash::make('password'),
            'remember_token' => Str::random(10),
            'login_type' => 'staff',
            'role_id' => fn () => Role::where('name', 'Viewer')->value('id'),
            'is_active' => true,
        ];
    }

    /** One of the roles the install starts with: owner, accounts, data_entry, viewer, or medical_rep (a login type). */
    public function role(string $role): static
    {
        if ($role === 'medical_rep') {
            return $this->state(fn () => ['login_type' => 'medical_rep', 'role_id' => null]);
        }

        $name = ['owner' => 'Owner', 'accounts' => 'Accounts', 'data_entry' => 'Data entry', 'viewer' => 'Viewer'][$role] ?? $role;

        return $this->state(fn () => ['login_type' => 'staff', 'role_id' => Role::where('name', $name)->value('id')]);
    }

    /** Staff with a given role model and, optionally, extra permissions (added after create). */
    public function withRole(Role $role): static
    {
        return $this->state(fn () => ['login_type' => 'staff', 'role_id' => $role->id]);
    }

    /**
     * Indicate that the model's email address should be unverified.
     */
    public function unverified(): static
    {
        return $this->state(fn (array $attributes) => [
            'email_verified_at' => null,
        ]);
    }
}
