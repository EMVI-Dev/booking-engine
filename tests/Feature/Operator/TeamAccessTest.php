<?php

use App\Enums\OperatorStatus;
use App\Enums\OperatorUserRole;
use App\Models\Operator;
use App\Models\Plan;
use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;

beforeEach(function () {
    Plan::seedDefaultPlans();

    $this->owner = User::factory()->create(['name' => 'Owner Person']);
    $this->operator = Operator::factory()->create([
        'name' => 'Plain Language Tours',
        'status' => OperatorStatus::Approved,
        'plan_id' => Plan::query()->where('slug', 'starter')->value('id'),
        'bank_provider' => 'BCA',
        'bank_account_name' => 'Owner Person',
        'bank_account_number' => '1111222233',
    ]);
    $this->operator->users()->attach($this->owner->id, ['role' => OperatorUserRole::Owner]);
});

test('each job lists which desk areas it can and cannot use', function () {
    expect(collect(OperatorUserRole::Reservation->deskScope())->where('allowed', true)->pluck('key')->all())
        ->toBe(['reservations'])
        ->and(collect(OperatorUserRole::Finance->deskScope())->where('allowed', true)->pluck('key')->all())
        ->toBe(['wallet', 'billing'])
        ->and(collect(OperatorUserRole::Admin->deskScope())->where('allowed', false)->pluck('key')->all())
        ->toBe([]);
});

test('the team page shows desk coverage and a people table', function () {
    $this->operator->update([
        'plan_id' => Plan::query()->where('slug', 'growth')->value('id'),
    ]);

    $helper = User::factory()->create(['name' => 'Ayu Bookings']);
    $this->operator->users()->attach($helper->id, ['role' => OperatorUserRole::Reservation]);

    $this->actingAs($this->owner);

    Livewire::test('pages::settings.team')
        ->assertSee('Person')
        ->assertSee('Desk access')
        ->assertSee('Bookings, calendar & guests')
        ->assertSee('Activities, packages & coupons')
        ->assertSee('Brand & storefront')
        ->assertSee('Wallet & payouts')
        ->assertSee('Plan & payout bank')
        ->assertSee('Team invites')
        ->assertSee('Owner Person')
        ->assertSee('Ayu Bookings')
        ->assertSee('Remove')
        ->assertSee('Review invite')
        ->assertSee('inviteOpen: false', false);
});

test('reviewing an invite asks for confirmation and does not send yet', function () {
    Notification::fake();

    $this->actingAs($this->owner);

    Livewire::test('pages::settings.team')
        ->set('invite_name', 'Ayu Bookings')
        ->set('invite_email', 'ayu@example.com')
        ->set('invite_role', OperatorUserRole::Reservation->value)
        ->call('promptInvite')
        ->assertHasNoErrors()
        ->assertDispatched('open-modal', 'confirm-team-invite')
        ->assertSee('Send this invite?');

    expect(User::query()->where('email', 'ayu@example.com')->exists())->toBeFalse();

    Notification::assertNothingSent();
});

test('an incomplete invite is not opened for confirmation', function () {
    $this->actingAs($this->owner);

    Livewire::test('pages::settings.team')
        ->call('promptInvite')
        ->assertHasErrors(['invite_name', 'invite_email'])
        ->assertNotDispatched('open-modal')
        ->assertSee('inviteOpen: true', false);
});

test('the owner can invite a teammate who is not another owner', function () {
    Notification::fake();

    $this->actingAs($this->owner);

    Livewire::test('pages::settings.team')
        ->set('invite_name', 'Ayu Bookings')
        ->set('invite_email', 'ayu@example.com')
        ->set('invite_role', OperatorUserRole::Reservation->value)
        ->call('inviteTeammate')
        ->assertHasNoErrors()
        ->assertSee('We emailed them a link to set a password and join.');

    $invited = User::query()->where('email', 'ayu@example.com')->first();

    expect($invited)->not->toBeNull()
        ->and($invited->roleOn($this->operator))->toBe(OperatorUserRole::Reservation);

    Notification::assertSentTo($invited, ResetPassword::class);
});

test('an owner cannot invite someone as another owner', function () {
    $this->actingAs($this->owner);

    Livewire::test('pages::settings.team')
        ->set('invite_name', 'Second Owner')
        ->set('invite_email', 'second@example.com')
        ->set('invite_role', OperatorUserRole::Owner->value)
        ->call('inviteTeammate')
        ->assertHasErrors(['invite_role']);

    expect(User::query()->where('email', 'second@example.com')->exists())->toBeFalse();
});

test('the last owner cannot be removed from the team', function () {
    $this->actingAs($this->owner);

    Livewire::test('pages::settings.team')
        ->call('removeTeammate', $this->owner->id)
        ->assertHasErrors(['team']);

    expect($this->operator->users()->whereKey($this->owner->id)->exists())->toBeTrue();
});

test('a bookings teammate cannot change the payout bank account or the plan', function () {
    $bookings = User::factory()->create();
    $this->operator->users()->attach($bookings->id, ['role' => OperatorUserRole::Reservation]);

    $this->actingAs($bookings);

    Livewire::test('pages::settings.payments')
        ->set('bank_provider', 'Mandiri')
        ->set('bank_account_name', 'Not Allowed')
        ->set('bank_account_number', '9999888877')
        ->call('updatePaymentSettings')
        ->assertForbidden();

    Livewire::test('pages::settings.plan')
        ->call('confirmPlanSwitch')
        ->assertForbidden();

    Livewire::test('pages::wallet.index')
        ->set('payoutAmount', '100000')
        ->call('submitPayoutRequest')
        ->assertForbidden();

    Livewire::test('pages::settings.team')->assertForbidden();

    $this->operator->refresh();

    expect($this->operator->bank_account_name)->toBe('Owner Person');
});

test('a money teammate can save the payout bank account but cannot invite people', function () {
    $money = User::factory()->create();
    $this->operator->users()->attach($money->id, ['role' => OperatorUserRole::Finance]);

    $this->actingAs($money);

    Livewire::test('pages::settings.payments')
        ->set('bank_provider', 'BCA')
        ->set('bank_account_name', 'Money Person')
        ->set('bank_account_number', '4444555566')
        ->set('payment_mode', 'platform')
        ->call('updatePaymentSettings')
        ->assertSuccessful()
        ->assertHasNoErrors();

    Livewire::test('pages::settings.team')->assertForbidden();

    $this->get(route('dashboard'))
        ->assertOk()
        ->assertDontSee(route('settings.team', absolute: false), false);

    $this->operator->refresh();

    expect($this->operator->bank_account_name)->toBe('Money Person');
});

test('a starter owner cannot invite a third person', function () {
    $helper = User::factory()->create();
    $this->operator->users()->attach($helper->id, ['role' => OperatorUserRole::Reservation]);

    $this->actingAs($this->owner);

    Livewire::test('pages::settings.team')
        ->assertSee('Your team is full on this plan')
        ->set('invite_name', 'Third Person')
        ->set('invite_email', 'third@example.com')
        ->set('invite_role', OperatorUserRole::Reservation->value)
        ->call('inviteTeammate')
        ->assertHasErrors(['invite_email']);

    expect(User::query()->where('email', 'third@example.com')->exists())->toBeFalse();
});

test('a pro owner can invite more than one helper', function () {
    $this->operator->update([
        'plan_id' => Plan::query()->where('slug', 'growth')->value('id'),
    ]);

    Notification::fake();

    $this->actingAs($this->owner);

    Livewire::test('pages::settings.team')
        ->set('invite_name', 'Ayu Bookings')
        ->set('invite_email', 'ayu@example.com')
        ->set('invite_role', OperatorUserRole::Reservation->value)
        ->call('inviteTeammate')
        ->assertHasNoErrors();

    Livewire::test('pages::settings.team')
        ->set('invite_name', 'Made Money')
        ->set('invite_email', 'made@example.com')
        ->set('invite_role', OperatorUserRole::Finance->value)
        ->call('inviteTeammate')
        ->assertHasNoErrors();

    expect($this->operator->users()->count())->toBe(3);
});
