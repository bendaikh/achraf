<?php

namespace Database\Factories;

use App\Models\Employee;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Employee>
 */
class EmployeeFactory extends Factory
{
    protected $model = Employee::class;

    public function definition(): array
    {
        return [
            'matricule' => 'EMP-'.fake()->unique()->numerify('####'),
            'last_name' => fake()->lastName(),
            'first_name' => fake()->firstName(),
            'hire_date' => '2025-09-01',
            'job_title' => 'Commercial',
            'status' => Employee::STATUS_ACTIF,
            'initial_leave_balance' => 0,
            'commission_eligible' => false,
        ];
    }
}
