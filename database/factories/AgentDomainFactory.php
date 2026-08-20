<?php

namespace Database\Factories;

use App\Enums\DomainStatus;
use App\Enums\DomainType;
use App\Models\Agent;
use App\Models\AgentDomain;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AgentDomain>
 */
class AgentDomainFactory extends Factory
{
    protected $model = AgentDomain::class;

    public function definition(): array
    {
        return [
            'agent_id' => Agent::factory(),
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
