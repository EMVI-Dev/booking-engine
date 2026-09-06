<?php

namespace App\Services;

use App\Enums\DomainStatus;
use App\Enums\DomainType;
use App\Enums\OperatorStatus;
use App\Enums\OperatorUserRole;
use App\Models\Operator;
use App\Models\OperatorDomain;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class OperatorOnboardingService
{
    /**
     * Register a new Operator along with its owner User account and default subdomain.
     *
     * @param  array{
     *     name: string,
     *     email: string,
     *     password: string,
     *     agency_name?: string|null,
     *     operator_name?: string|null,
     *     slug?: string|null,
     *     contact_whatsapp?: string|null,
     *     bio?: string|null,
     *     bank_account_ref?: string|null,
     *     booking_notification_email?: string|null,
     *     billing_email?: string|null,
     *     brand_color?: string|null
     * } $data
     * @return array{user: User, operator: Operator, domain: OperatorDomain, agent: Operator}
     */
    public function registerOperator(array $data): array
    {
        return DB::transaction(function () use ($data) {
            // 1. Create Owner User
            $user = User::create([
                'name' => $data['name'],
                'email' => $data['email'],
                'password' => Hash::needsRehash($data['password']) ? Hash::make($data['password']) : $data['password'],
            ]);

            $businessName = $data['operator_name'] ?? $data['agency_name'] ?? $data['name'];

            // 2. Generate and sanitize slug
            $slug = ! empty($data['slug']) ? Str::slug($data['slug']) : Str::slug($businessName);
            if (empty($slug)) {
                $slug = 'operator-'.Str::lower(Str::random(6));
            }

            // Ensure slug uniqueness
            $baseSlug = $slug;
            $counter = 1;
            while (Operator::where('slug', $slug)->exists()) {
                $slug = $baseSlug.'-'.$counter++;
            }

            // 3. Create Operator
            $operator = Operator::create([
                'name' => $businessName,
                'slug' => $slug,
                'bio' => $data['bio'] ?? null,
                'photo' => null,
                'contact_whatsapp' => $data['contact_whatsapp'] ?? null,
                'booking_notification_email' => $data['booking_notification_email'] ?? $data['email'],
                'billing_email' => $data['billing_email'] ?? $data['email'],
                'status' => OperatorStatus::Approved,
                'terms_and_conditions' => null,
                'bank_account_ref' => $data['bank_account_ref'] ?? null,
                'settings' => [
                    'sellable_standalone_default' => true,
                    'brand_color' => $data['brand_color'] ?? '#0f172a',
                    'display_name' => $businessName,
                ],
            ]);

            // 4. Attach Owner to Operator
            $operator->users()->attach($user->id, [
                'role' => OperatorUserRole::Owner->value,
            ]);

            // 5. Create default Subdomain record
            $appUrlHost = parse_url((string) config('app.url', 'http://localhost'), PHP_URL_HOST);
            $platformDomain = is_string($appUrlHost) && $appUrlHost !== 'localhost' ? $appUrlHost : 'booking.test';
            $fullSubdomain = "{$slug}.{$platformDomain}";

            $domain = OperatorDomain::create([
                'operator_id' => $operator->id,
                'domain' => $fullSubdomain,
                'type' => DomainType::Subdomain,
                'is_primary' => true,
                'status' => DomainStatus::Active,
                'verified_at' => now(),
                'ssl_issued_at' => now(),
            ]);

            return [
                'user' => $user,
                'operator' => $operator,
                'agent' => $operator, // For backward compatibility
                'domain' => $domain,
            ];
        });
    }

    /**
     * @deprecated Use registerOperator() instead.
     *
     * @param  array<string, mixed>  $data
     * @return array{user: User, operator: Operator, domain: OperatorDomain, agent: Operator}
     */
    public function registerAgent(array $data): array
    {
        return $this->registerOperator($data);
    }
}
