<?php

namespace App\Actions\Fortify;

use App\Concerns\PasswordValidationRules;
use App\Concerns\ProfileValidationRules;
use App\Models\PlatformSetting;
use App\Models\User;
use App\Services\OperatorOnboardingService;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Laravel\Fortify\Contracts\CreatesNewUsers;

class CreateNewUser implements CreatesNewUsers
{
    use PasswordValidationRules, ProfileValidationRules;

    public function __construct(
        protected OperatorOnboardingService $onboardingService
    ) {}

    /**
     * Validate and create a newly registered user and their operator storefront.
     *
     * @param  array<string, string>  $input
     */
    public function create(array $input): User
    {
        abort_unless(PlatformSetting::current()->operatorRegistrationAllowed(), 403);

        $businessName = $input['operator_name'] ?? $input['agency_name'] ?? '';

        Validator::make(array_merge($input, ['business_name' => $businessName]), [
            ...$this->profileRules(),
            'password' => $this->passwordRules(),
            'business_name' => ['required', 'string', 'max:255'],
            'slug' => ['nullable', 'string', 'max:64', 'alpha_dash', Rule::unique('operators', 'slug')],
            'contact_whatsapp' => ['nullable', 'string', 'max:30'],
            'bio' => ['nullable', 'string', 'max:1000'],
            'bank_account_ref' => ['nullable', 'string', 'max:100'],
            'terms' => ['accepted'],
        ], [
            'business_name.required' => 'Please provide your tour operator or guide business name.',
            'slug.unique' => 'That page address is already taken. Please choose another.',
            'terms.accepted' => 'Please agree to the terms before creating your account.',
        ])->validate();

        $result = $this->onboardingService->registerOperator([
            'name' => $input['name'],
            'email' => $input['email'],
            'password' => $input['password'],
            'operator_name' => $businessName,
            'agency_name' => $businessName,
            'slug' => $input['slug'] ?? null,
            'contact_whatsapp' => $input['contact_whatsapp'] ?? null,
            'bio' => $input['bio'] ?? null,
            'bank_account_ref' => $input['bank_account_ref'] ?? null,
        ]);

        session()->flash('welcome_onboarding', true);
        session()->flash('status', 'Your booking page is ready. Add a trip, then add your payout bank account when you want to get paid.');

        return $result['user'];
    }
}
