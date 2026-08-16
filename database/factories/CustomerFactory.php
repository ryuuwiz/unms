<?php

namespace Database\Factories;

use App\Enums\CustomerStatus;
use App\Models\Customer;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Customer>
 */
class CustomerFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'customer_code' => Customer::generateNextCustomerCode(),
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'phone' => '628'.fake()->numerify('##########'),
            'address' => fake()->streetAddress().', '.fake()->city(),
            'installation_address' => fake()->streetAddress().', '.fake()->city(),
            'lat' => fake()->latitude(-6.3, -6.1),
            'lng' => fake()->longitude(106.7, 106.9),
            'status' => CustomerStatus::Active,
            'created_by' => User::factory(),
        ];
    }

    /**
     * Indicate that the customer is inactive.
     */
    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => CustomerStatus::Inactive,
        ]);
    }
}
