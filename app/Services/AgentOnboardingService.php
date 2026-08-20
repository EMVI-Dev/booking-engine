<?php

namespace App\Services;

use App\Enums\AgentStatus;
use App\Enums\AgentUserRole;
use App\Enums\DomainStatus;
use App\Enums\DomainType;
use App\Models\Agent;
use App\Models\AgentDomain;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class AgentOnboardingService
{
    /**
     * Register a new Agent along with its owner User account and default subdomain.
     *
     * @param  array{
     *     name: string,
     *     email: string,
     *     password: string,
     *     agency_name: string,
     *     slug?: string|null,
     *     contact_whatsapp?: string|null,
     *     bio?: string|null,
     *     bank_account_ref?: string|null,
     *     booking_notification_email?: string|null,
     *     billing_email?: string|null,
     *     brand_color?: string|null
     * } $data
     * @return array{user: User, agent: Agent, domain: AgentDomain}
     */
    public function registerAgent(array $data): array
    {
        return DB::transaction(function () use ($data) {
            // 1. Create Owner User
            $user = User::create([
                'name' => $data['name'],
                'email' => $data['email'],
                'password' => Hash::needsRehash($data['password']) ? Hash::make($data['password']) : $data['password'],
            ]);

            // 2. Generate and sanitize slug
            $slug = ! empty($data['slug']) ? Str::slug($data['slug']) : Str::slug($data['agency_name']);
            if (empty($slug)) {
                $slug = 'agent-'.Str::lower(Str::random(6));
            }

            // Ensure slug uniqueness
            $baseSlug = $slug;
            $counter = 1;
            while (Agent::where('slug', $slug)->exists()) {
                $slug = $baseSlug.'-'.$counter++;
            }

            // 3. Create Agent
            $agent = Agent::create([
                'name' => $data['agency_name'],
                'slug' => $slug,
                'bio' => $data['bio'] ?? null,
                'photo' => null,
                'contact_whatsapp' => $data['contact_whatsapp'] ?? null,
                'booking_notification_email' => $data['booking_notification_email'] ?? $data['email'],
                'billing_email' => $data['billing_email'] ?? $data['email'],
                'status' => AgentStatus::Approved,
                'terms_and_conditions' => 'Standard tour agent terms & conditions.',
                'bank_account_ref' => $data['bank_account_ref'] ?? null,
                'settings' => [
                    'sellable_standalone_default' => true,
                    'brand_color' => $data['brand_color'] ?? '#0f172a',
                    'display_name' => $data['agency_name'],
                ],
            ]);

            // 4. Attach Owner to Agent
            $agent->users()->attach($user->id, [
                'role' => AgentUserRole::Owner->value,
            ]);

            // 5. Create default Subdomain record
            $appUrlHost = parse_url((string) config('app.url', 'http://localhost'), PHP_URL_HOST);
            $platformDomain = is_string($appUrlHost) && $appUrlHost !== 'localhost' ? $appUrlHost : 'booking.test';
            $fullSubdomain = "{$slug}.{$platformDomain}";

            $domain = AgentDomain::create([
                'agent_id' => $agent->id,
                'domain' => $fullSubdomain,
                'type' => DomainType::Subdomain,
                'is_primary' => true,
                'status' => DomainStatus::Active,
                'verified_at' => now(),
                'ssl_issued_at' => now(),
            ]);

            return [
                'user' => $user,
                'agent' => $agent,
                'domain' => $domain,
            ];
        });
    }
}
