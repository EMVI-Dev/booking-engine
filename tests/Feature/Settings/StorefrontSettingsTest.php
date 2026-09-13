<?php

use App\Enums\OperatorStatus;
use App\Enums\OperatorUserRole;
use App\Models\Operator;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->operator = Operator::factory()->create([
        'name' => 'Paradise Expeditions',
        'status' => OperatorStatus::Approved,
        'terms_and_conditions' => 'Original booking terms.',
        'settings' => [
            'storefront' => [
                'allow_standalone_products' => true,
                'show_inclusions_preview' => true,
                'hero_headline' => 'Original Headline',
                'hero_tagline' => 'Original Tagline',
            ],
        ],
    ]);
    $this->operator->users()->attach($this->user->id, ['role' => OperatorUserRole::Owner]);
    $this->actingAs($this->user);
});

test('empty guest booking terms are highlighted for setup', function () {
    $user = User::factory()->create();
    $operator = Operator::factory()->incompleteSetup()->create([
        'name' => 'Needs Terms Tours',
        'contact_whatsapp' => '+628123456789',
    ]);
    $operator->users()->attach($user->id, ['role' => OperatorUserRole::Owner]);
    $this->actingAs($user);

    Livewire::test('pages::settings.storefront')
        ->assertSee('Needed for bookings')
        ->assertSee('Hero Banner Copy & Marketing Text')
        ->assertSeeHtml('id="setup-hero"')
        ->assertSeeHtml('id="setup-terms"')
        ->assertSeeHtml('data-setup-needed="true"')
        ->assertSeeHtml('border-[#FFEF4D]')
        ->set('hero_headline', 'Snorkel mornings from Sanur')
        ->set('terms_and_conditions', 'Cancel up to 24 hours before departure.')
        ->assertSeeHtml('data-setup-needed="true"')
        ->call('updateStorefrontSettings')
        ->assertHasNoErrors()
        ->assertDispatched('setup-progress-updated')
        ->assertDontSeeHtml('data-setup-needed="true"');
});

test('storefront settings can be updated', function () {
    Livewire::test('pages::settings.storefront')
        ->set('allow_standalone_products', false)
        ->set('show_inclusions_preview', false)
        ->set('hero_headline', 'Exclusive Island Journeys')
        ->set('hero_tagline', 'Direct private bookings with expert crew')
        ->set('terms_and_conditions', 'Updated cancellation policy: 48h free cancellation.')
        ->call('updateStorefrontSettings')
        ->assertHasNoErrors();

    $this->operator->refresh();

    expect($this->operator->terms_and_conditions)->toBe('Updated cancellation policy: 48h free cancellation.')
        ->and($this->operator->settings['storefront']['allow_standalone_products'])->toBeFalse()
        ->and($this->operator->settings['storefront']['show_inclusions_preview'])->toBeFalse()
        ->and($this->operator->settings['storefront']['hero_headline'])->toBe('Exclusive Island Journeys')
        ->and($this->operator->settings['storefront']['hero_tagline'])->toBe('Direct private bookings with expert crew');
});
