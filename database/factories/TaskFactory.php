<?php

namespace Database\Factories;

use App\Models\Task;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Task>
 */
class TaskFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'title' => fake()->sentence(4),
            'notes' => null,
            'customer_id' => null,
            'assigned_to' => User::factory(),
            'due_date' => null,
        ];
    }

    /**
     * Indicate that the task is done.
     */
    public function completed(): static
    {
        return $this->state(fn (array $attributes) => [
            'completed_at' => now(),
            'completed_by' => User::factory(),
        ]);
    }

    /**
     * Indicate that the task is open and was due before today.
     */
    public function overdue(): static
    {
        return $this->state(fn (array $attributes) => [
            'due_date' => today()->subDays(3),
        ]);
    }

    /**
     * Indicate that the task is due today.
     */
    public function dueToday(): static
    {
        return $this->state(fn (array $attributes) => [
            'due_date' => today(),
        ]);
    }
}
