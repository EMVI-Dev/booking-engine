<?php

namespace Database\Factories;

use App\Enums\DomainStatus;
use App\Enums\DomainType;
use App\Models\Operator;
use App\Models\OperatorDomain;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<OperatorDomain>
 */
class OperatorDomainFactory extends Factory
{
    protected $model = OperatorDomain::class;

    public function definition(): array
    {
        return [
            'operator_id' => Operator::factory(),
            'domain' => fake()->unique()->domainName(),
            'type' => DomainType::Subdomain,
            'is_primary' => true,
            'status' => DomainStatus::Active,
            'verified_at' => now(),
            'ssl_issued_at' => now(),
        ];
    }

    public function custom(): static
    {
        return $this->state(fn () => [
            'type' => DomainType::Custom,
            'is_primary' => true,
        ]);
    }
}
