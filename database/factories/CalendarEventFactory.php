<?php

namespace Database\Factories;

use App\Enums\EventRepeat;
use App\Models\CalendarEvent;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CalendarEvent>
 */
class CalendarEventFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'title' => fake()->sentence(3),
            'starts_on' => today()->addDay(),
            'start_time' => '10:00:00',
            'end_time' => '11:00:00',
            'all_day' => false,
            'customer_id' => null,
            'location' => null,
            'notes' => null,
            'remind_minutes' => null,
            'repeat' => EventRepeat::None,
            'repeat_until' => null,
            'created_by' => User::factory(),
        ];
    }

    /**
     * Indicate that the event lasts the whole day.
     */
    public function allDay(): static
    {
        return $this->state(fn (array $attributes) => [
            'all_day' => true,
            'start_time' => null,
            'end_time' => null,
        ]);
    }

    /**
     * Indicate that the event repeats.
     */
    public function repeating(EventRepeat $repeat): static
    {
        return $this->state(fn (array $attributes) => ['repeat' => $repeat]);
    }
}
