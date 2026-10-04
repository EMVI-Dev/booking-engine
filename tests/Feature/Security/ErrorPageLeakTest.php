<?php

use App\Models\Reservation;
use Illuminate\Support\Facades\Route;

beforeEach(function () {
    config(['app.debug' => false]);

    Route::get('/__boom-sql', fn () => throw new RuntimeException('SQLSTATE[23000]: insert into guests values (secret@example.com)'));
    Route::get('/__missing-model', fn () => Reservation::query()->findOrFail('01missingmodel'));
    Route::get('/__our-abort', fn () => abort(403, 'Only the shop owner can do this.'));
});

test('a server error never shows the exception message', function () {
    $this->get('/__boom-sql')
        ->assertStatus(500)
        ->assertDontSee('SQLSTATE')
        ->assertDontSee('secret@example.com');
});

test('a missing model does not reveal model names or ids', function () {
    $this->get('/__missing-model')
        ->assertNotFound()
        ->assertDontSee('No query results')
        ->assertDontSee('App\\Models');
});

test('messages we abort with ourselves are still shown', function () {
    $this->get('/__our-abort')->assertForbidden()->assertSee('Only the shop owner can do this.');
});
