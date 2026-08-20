<?php

use App\Enums\AgentStatus;
use App\Enums\AgentUserRole;
use App\Models\Agent;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->agent = Agent::factory()->create([
        'name' => 'Paradise Expeditions',
        'status' => AgentStatus::Approved,
        'terms_and_conditions' => 'Original booking terms.',
        'settings' => [
            'storefront' => [
                'allow_standalone_products' => true,
                'show_reviews' => true,
                'show_inclusions_preview' => true,
                'hero_headline' => 'Original Headline',
                'hero_tagline' => 'Original Tagline',
            ],
        ],
    ]);
    $this->agent->users()->attach($this->user->id, ['role' => AgentUserRole::Owner]);
    $this->actingAs($this->user);
});

test('storefront settings page is displayed', function () {
    $this->get(route('storefront-settings.edit'))->assertOk();
});

test('storefront settings can be updated', function () {
    Livewire::test('pages::settings.storefront')
        ->set('allow_standalone_products', false)
        ->set('show_reviews', true)
        ->set('show_inclusions_preview', false)
        ->set('hero_headline', 'Exclusive Island Journeys')
        ->set('hero_tagline', 'Direct private bookings with expert crew')
        ->set('terms_and_conditions', 'Updated cancellation policy: 48h free cancellation.')
        ->call('updateStorefrontSettings')
        ->assertHasNoErrors();

    $this->agent->refresh();

    expect($this->agent->terms_and_conditions)->toBe('Updated cancellation policy: 48h free cancellation.')
        ->and($this->agent->settings['storefront']['allow_standalone_products'])->toBeFalse()
        ->and($this->agent->settings['storefront']['show_reviews'])->toBeTrue()
        ->and($this->agent->settings['storefront']['show_inclusions_preview'])->toBeFalse()
        ->and($this->agent->settings['storefront']['hero_headline'])->toBe('Exclusive Island Journeys')
        ->and($this->agent->settings['storefront']['hero_tagline'])->toBe('Direct private bookings with expert crew');
});
