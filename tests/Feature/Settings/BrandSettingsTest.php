<?php

use App\Enums\OperatorStatus;
use App\Enums\OperatorUserRole;
use App\Models\Operator;
use App\Models\User;
use App\Services\MediaStore;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->operator = Operator::factory()->create([
        'name' => 'Original Agency',
        'status' => OperatorStatus::Approved,
        'bio' => 'Original bio description.',
        'contact_whatsapp' => '+628123456789',
        'booking_notification_email' => 'bookings@original.com',
        'billing_email' => 'finance@original.com',
    ]);
    $this->operator->users()->attach($this->user->id, ['role' => OperatorUserRole::Owner]);
    $this->actingAs($this->user);
});

test('brand settings page is displayed', function () {
    config(['services.google.places_key' => 'test-places-key']);

    $this->get(route('brand.edit'))
        ->assertOk()
        ->assertSee('Social Media Links')
        ->assertSee('Booking code prefix')
        ->assertDontSee('Official Website Link')
        ->assertDontSee('Website & Social Media Links')
        ->assertDontSee('Find listing')
        ->assertDontSee('Review Platform (e.g. Google/Tripadvisor)')
        ->assertSee('Reviews');
});

test('incomplete brand fields are highlighted for setup', function () {
    Storage::fake(MediaStore::diskName());

    $user = User::factory()->create();
    $operator = Operator::factory()->incompleteSetup()->create([
        'name' => 'Needs Bio Tours',
        'contact_whatsapp' => '+628123456789',
    ]);
    $operator->users()->attach($user->id, ['role' => OperatorUserRole::Owner]);
    $this->actingAs($user);

    $logo = UploadedFile::fake()->image('needs-bio-logo.png', 400, 400);

    Livewire::test('pages::settings.brand')
        ->assertSee('Finish the highlighted fields')
        ->assertSee('Needed for bookings')
        ->assertSee('Storefront Introduction / Bio')
        ->assertSeeHtml('id="setup-logo"')
        ->assertSeeHtml('id="setup-bio"')
        ->assertSeeHtml('data-setup-needed="true"')
        ->assertSeeHtml('id="setup-billing-email"')
        ->assertSeeHtml('id="setup-booking-notification-email"')
        ->set('logo', $logo)
        ->set('bio', 'We run snorkel trips from Sanur every morning.')
        ->set('booking_notification_email', 'bookings@needsbio.test')
        ->set('billing_email', 'finance@needsbio.test')
        ->call('updateBrandSettings')
        ->assertHasNoErrors()
        ->assertDispatched('setup-progress-updated')
        ->assertDontSee('Finish the highlighted fields')
        ->assertDontSeeHtml('data-setup-needed="true"');
});

test('brand settings can be updated with logo and social media links', function () {
    Storage::fake(MediaStore::diskName());

    $file = UploadedFile::fake()->image('brand-logo.png', 400, 400);

    Livewire::test('pages::settings.brand')
        ->set('agency_name', 'Sunrise Excursions')
        ->set('bio', 'Premier marine and outdoor adventure operator.')
        ->set('contact_whatsapp', '+628987654321')
        ->set('brand_color', '#0ea5e9')
        ->set('logo', $file)
        ->set('instagram_url', 'https://instagram.com/sunriseexcursions')
        ->set('facebook_url', 'https://facebook.com/sunriseexcursions')
        ->set('tiktok_url', 'https://tiktok.com/@sunriseexcursions')
        ->set('youtube_url', 'https://youtube.com/@sunriseexcursions')
        ->set('whatsapp_prefilled_message', 'Hello Sunrise team!')
        ->set('whatsapp_schedule_mode', 'schedule')
        ->set('whatsapp_timezone', 'Asia/Makassar')
        ->set('whatsapp_start_time', '08:00')
        ->set('whatsapp_end_time', '18:00')
        ->set('whatsapp_days', ['mon', 'tue', 'wed', 'thu', 'fri'])
        ->set('booking_notification_email', 'reservations@sunrise.com')
        ->set('billing_email', 'accounting@sunrise.com')
        ->call('updateBrandSettings')
        ->assertHasNoErrors();

    $this->operator->refresh();

    expect($this->operator->name)->toBe('Sunrise Excursions')
        ->and($this->operator->bio)->toBe('Premier marine and outdoor adventure operator.')
        ->and($this->operator->contact_whatsapp)->toBe('+628987654321')
        ->and($this->operator->booking_notification_email)->toBe('reservations@sunrise.com')
        ->and($this->operator->billing_email)->toBe('accounting@sunrise.com')
        ->and($this->operator->logo_path)->toStartWith('operators/'.$this->operator->id.'/brand/')
        ->and($this->operator->logo_path)->toEndWith('.webp')
        ->and($this->operator->settings['brand_color'])->toBe('#0ea5e9')
        ->and($this->operator->settings['whatsapp_schedule']['timezone'])->toBe('Asia/Makassar')
        ->and($this->operator->settings['whatsapp_schedule']['start_time'])->toBe('08:00')
        ->and($this->operator->settings['whatsapp_schedule']['end_time'])->toBe('18:00')
        ->and($this->operator->settings['whatsapp_schedule']['days'])->toBe(['mon', 'tue', 'wed', 'thu', 'fri'])
        ->and($this->operator->settings['social_links'])->not->toHaveKey('website')
        ->and($this->operator->settings['social_links']['instagram'])->toBe('https://instagram.com/sunriseexcursions')
        ->and($this->operator->settings['social_links']['tiktok'])->toBe('https://tiktok.com/@sunriseexcursions');

    expect($this->operator->getWhatsAppScheduleSummary())->toContain('08:00 - 18:00 (WITA');

    Storage::disk(MediaStore::diskName())->assertExists($this->operator->logo_path);
});

test('saving brand settings drops a leftover official website link', function () {
    $this->operator->update([
        'settings' => array_merge($this->operator->settings ?? [], [
            'social_links' => [
                'website' => 'https://old-site.example',
                'instagram' => 'https://instagram.com/original',
            ],
        ]),
    ]);

    Livewire::test('pages::settings.brand')
        ->call('updateBrandSettings')
        ->assertHasNoErrors();

    expect($this->operator->fresh()->settings['social_links'])->not->toHaveKey('website')
        ->and($this->operator->fresh()->settings['social_links']['instagram'])->toBe('https://instagram.com/original');
});

test('brand settings validation enforces required fields and valid hex color', function () {
    Livewire::test('pages::settings.brand')
        ->set('agency_name', '')
        ->set('booking_notification_email', 'invalid-email')
        ->set('brand_color', 'invalid-hex')
        ->call('updateBrandSettings')
        ->assertHasErrors(['agency_name', 'booking_notification_email', 'brand_color']);
});
