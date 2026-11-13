<?php

namespace Database\Factories;

use App\Models\Transaction;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Transaction>
 */
class TransactionFactory extends Factory
{
    public function definition(): array
    {
        $date = fake()->dateTimeBetween('-20 days', 'now');

        return [
            'type' => 'expense',
            'description' => fake()->words(2, true),
            'amount' => fake()->numberBetween(500, 50000),
            'date' => $date,
            'paid_at' => $date,
        ];
    }

    public function income(): static
    {
        return $this->state(['type' => 'income']);
    }

    public function pending(): static
    {
        return $this->state(['paid_at' => null]);
    }
}
