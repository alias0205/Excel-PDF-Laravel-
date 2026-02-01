<?php

namespace Database\Factories;

use App\Models\Company;
use App\Models\Staff;
use Illuminate\Database\Eloquent\Factories\Factory;

class StaffFactory extends Factory
{
    protected $model = Staff::class;

    public function definition(): array
    {
        return [
            'company_id' => Company::factory(),
            'employee_id' => strtoupper($this->faker->bothify('EMP-####')),
            'first_name' => $this->faker->firstName(),
            'last_name' => $this->faker->lastName(),
            'email' => $this->faker->unique()->safeEmail(),
            'phone' => $this->faker->phoneNumber(),
            'department' => $this->faker->randomElement(['Sales', 'HR', 'Finance', 'IT', 'Operations']),
            'title' => $this->faker->jobTitle(),
            'hire_date' => $this->faker->dateTimeBetween('-5 years', 'now')->format('Y-m-d'),
            'status' => $this->faker->randomElement(['Active', 'On Leave', 'Contract']),
            'notes' => $this->faker->optional()->sentence(),
        ];
    }
}
