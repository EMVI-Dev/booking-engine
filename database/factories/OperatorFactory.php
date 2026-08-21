<?php

namespace Database\Factories;

use App\Enums\OperatorStatus;
use App\Models\Operator;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Operator>
 */
class OperatorFactory extends Factory
{
    protected $model = Operator::class;

    public function definition(): array
    {
        $name = fake()->company().' Tours';

        return [
            'name' => $name,
            'slug' => Str::slug($name).'-'.fake()->unique()->numberBetween(100, 999),
            'bio' => fake()->paragraph(),
            'photo' => null,
            'contact_whatsapp' => fake()->phoneNumber(),
            'booking_notification_email' => fake()->safeEmail(),
            'billing_email' => fake()->safeEmail(),
            'status' => OperatorStatus::Approved,
            'terms_and_conditions' => 'Standard tour operator terms and conditions.',
            'bank_account_ref' => 'ID_BANK_'.fake()->numerify('##########'),
            'logo_path' => null,
            'favicon_path' => null,
            'banner_path' => null,
            'settings' => [
                'sellable_standalone_default' => true,
                'brand_color' => '#0f172a',
                'display_name' => $name,
            ],
        ];
    }

    public function pending(): static
    {
        return $this->state(fn () => [
            'status' => OperatorStatus::Pending,
        ]);
    }

    public function suspended(): static
    {
        return $this->state(fn () => [
            'status' => OperatorStatus::Suspended,
        ]);
    }
}
