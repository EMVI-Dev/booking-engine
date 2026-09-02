<?php

use App\Models\Operator;
use App\Models\PlatformAnnouncement;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->admin = User::factory()->create([
        'email' => 'admin@emvi.dev',
        'is_admin' => true,
    ]);

    $this->operatorUser = User::factory()->create([
        'email' => 'operator@agency.com',
        'is_admin' => false,
    ]);

    $this->operator = Operator::factory()->create([
        'name' => 'Sunset Sail Co',
        'slug' => 'sunset-sail',
    ]);
    $this->operator->users()->attach($this->operatorUser->id, ['role' => 'owner']);
});

test('admin can view broadcast notices page and create announcement', function () {
    Livewire::actingAs($this->admin)
        ->test('pages::admin.announcements')
        ->assertOk()
        ->assertSee('Platform Broadcasts & Announcements')
        ->call('openCreateModal')
        ->set('title', 'Scheduled Maintenance Notice')
        ->set('message', 'Platform servers will undergo maintenance at 2 AM UTC.')
        ->set('type', 'warning')
        ->call('saveAnnouncement')
        ->assertHasNoErrors();

    expect(PlatformAnnouncement::where('title', 'Scheduled Maintenance Notice')->exists())->toBeTrue();
});

test('admin can toggle active status and delete announcement via confirmation modals', function () {
    $announcement = PlatformAnnouncement::factory()->create([
        'title' => 'Important Policy Update',
        'is_active' => true,
    ]);

    Livewire::actingAs($this->admin)
        ->test('pages::admin.announcements')
        ->call('confirmToggleActive', $announcement->id)
        ->assertSet('showConfirmToggleModal', true)
        ->assertSet('toggleAnnouncementId', $announcement->id)
        ->call('executeToggleActive')
        ->assertSet('showConfirmToggleModal', false)
        ->assertHasNoErrors();

    $announcement->refresh();
    expect($announcement->is_active)->toBeFalse();

    Livewire::actingAs($this->admin)
        ->test('pages::admin.announcements')
        ->call('confirmDelete', $announcement->id)
        ->assertSet('showConfirmDeleteModal', true)
        ->assertSet('deleteAnnouncementId', $announcement->id)
        ->call('executeDelete')
        ->assertSet('showConfirmDeleteModal', false)
        ->assertHasNoErrors();

    expect(PlatformAnnouncement::find($announcement->id))->toBeNull();
});

test('operator dashboard displays active platform announcement banner', function () {
    PlatformAnnouncement::factory()->create([
        'title' => 'Welcome to Version 2.0',
        'message' => 'New capacity heatmap and calendar integrations are live.',
        'type' => 'success',
        'is_active' => true,
        'starts_at' => now()->subHour(),
        'ends_at' => now()->addDays(2),
    ]);

    $this->actingAs($this->operatorUser)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertSee('Welcome to Version 2.0')
        ->assertSee('New capacity heatmap and calendar integrations are live.');
});
