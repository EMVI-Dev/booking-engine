<?php

namespace Database\Factories;

use App\Models\AdminAuditLog;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AdminAuditLog>
 */
class AdminAuditLogFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory()->admin(),
            'actor_email' => fake()->safeEmail(),
            'action' => 'operator.status_changed',
            'context' => ['from' => 'Approved', 'to' => 'Suspended'],
            'ip_address' => fake()->ipv4(),
        ];
    }
}
