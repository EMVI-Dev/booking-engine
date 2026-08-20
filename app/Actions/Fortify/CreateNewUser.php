<?php

namespace App\Actions\Fortify;

use App\Concerns\PasswordValidationRules;
use App\Concerns\ProfileValidationRules;
use App\Models\User;
use App\Services\AgentOnboardingService;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Laravel\Fortify\Contracts\CreatesNewUsers;

class CreateNewUser implements CreatesNewUsers
{
    use PasswordValidationRules, ProfileValidationRules;

    public function __construct(
        protected AgentOnboardingService $onboardingService
    ) {}

    /**
     * Validate and create a newly registered user and their agent storefront.
     *
     * @param  array<string, string>  $input
     */
    public function create(array $input): User
    {
        Validator::make($input, [
            ...$this->profileRules(),
            'password' => $this->passwordRules(),
            'agency_name' => ['required', 'string', 'max:255'],
            'slug' => ['nullable', 'string', 'max:64', 'alpha_dash', Rule::unique('agents', 'slug')],
            'contact_whatsapp' => ['nullable', 'string', 'max:30'],
            'bio' => ['nullable', 'string', 'max:1000'],
            'bank_account_ref' => ['nullable', 'string', 'max:100'],
            'terms' => ['accepted'],
        ], [
            'agency_name.required' => 'Please provide your agency or tour business name.',
            'slug.unique' => 'This storefront subdomain is already taken. Please choose another.',
            'terms.accepted' => 'You must accept the Storefront Terms & Protection to launch your storefront.',
        ])->validate();

        $result = $this->onboardingService->registerAgent([
            'name' => $input['name'],
            'email' => $input['email'],
            'password' => $input['password'],
            'agency_name' => $input['agency_name'],
            'slug' => $input['slug'] ?? null,
            'contact_whatsapp' => $input['contact_whatsapp'] ?? null,
            'bio' => $input['bio'] ?? null,
            'bank_account_ref' => $input['bank_account_ref'] ?? null,
        ]);

        session()->flash('welcome_onboarding', true);
        session()->flash('status', 'Welcome to your tour portal! Please complete your brand logo, theme color, and business details.');

        return $result['user'];
    }
}
