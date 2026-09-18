<?php

namespace Database\Factories;

use App\Enums\Language;
use App\Enums\UserRole;
use App\Models\Ngo;
use App\Models\User;
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
            'phone' => '+20'.fake()->unique()->numerify('1#########'),
            'role' => UserRole::Family,
            'preferred_language' => Language::English,
            'is_active' => true,
            'email_verified_at' => now(),
            'phone_verified_at' => now(),
            'sms_opt_in_at' => now(),
            'password' => static::$password ??= Hash::make('password'),
            'remember_token' => Str::random(10),
        ];
    }

    public function superAdmin(): static
    {
        return $this->state(fn (array $attributes) => [
            'ngo_id' => null,
            'role' => UserRole::SuperAdmin,
        ]);
    }

    public function ngoStaff(?Ngo $ngo = null): static
    {
        return $this->state(fn (array $attributes) => [
            'ngo_id' => $ngo ?? Ngo::factory(),
            'role' => UserRole::NgoStaff,
        ]);
    }

    public function family(): static
    {
        return $this->state(fn (array $attributes) => [
            'ngo_id' => null,
            'role' => UserRole::Family,
        ]);
    }

    public function arabic(): static
    {
        return $this->state(fn (array $attributes) => [
            'preferred_language' => Language::Arabic,
        ]);
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
        ]);
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
