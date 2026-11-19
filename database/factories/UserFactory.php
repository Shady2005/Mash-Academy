<?php

namespace Database\Factories;

use App\Enums\UserRole;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\User>
 */
class UserFactory extends Factory
{
    protected static ?string $password;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'phone' => '+2010'.fake()->numerify('########'),
            'email_verified_at' => now(),
            'password' => static::$password ??= Hash::make('Password123!'),
            'role' => UserRole::Student,
            'is_active' => true,
            'must_change_password' => false,
            'remember_token' => Str::random(10),
        ];
    }

    public function student(): static
    {
        return $this->state(fn (array $attributes): array => [
            'role' => UserRole::Student,
        ]);
    }

    public function superAdmin1(): static
    {
        return $this->state(fn (array $attributes): array => [
            'role' => UserRole::SuperAdmin1,
        ]);
    }

    public function superAdmin2(): static
    {
        return $this->state(fn (array $attributes): array => [
            'role' => UserRole::SuperAdmin2,
        ]);
    }

    public function teacher(): static
    {
        return $this->state(fn (array $attributes): array => [
            'role' => UserRole::Teacher,
        ]);
    }

    public function support(): static
    {
        return $this->state(fn (array $attributes): array => [
            'role' => UserRole::Support,
        ]);
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes): array => [
            'is_active' => false,
        ]);
    }

    public function unverified(): static
    {
        return $this->state(fn (array $attributes): array => [
            'email_verified_at' => null,
        ]);
    }
}
