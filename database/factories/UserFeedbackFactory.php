<?php

namespace Database\Factories;

use App\Models\BlindUser;
use App\Models\User;
use App\Models\UserFeedback;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<UserFeedback>
 */
class UserFeedbackFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'blind_user_id' => BlindUser::factory(),
            'submitted_by' => User::factory()->family(),
            'rating' => fake()->numberBetween(1, 5),
            'comment' => fake()->optional()->sentence(),
            'period_start' => now()->subMonth()->toDateString(),
            'period_end' => now()->toDateString(),
        ];
    }
}
