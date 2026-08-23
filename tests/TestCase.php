<?php

namespace Tests;

use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\DB;
use Laravel\Fortify\Features;
use RuntimeException;

abstract class TestCase extends BaseTestCase
{
    use RefreshDatabase;

    /**
     * Boot the testing environment and force SQLite in-memory before RefreshDatabase runs.
     */
    public function createApplication()
    {
        $app = parent::createApplication();

        // Hard-enforce sqlite :memory: regardless of cached config or environment
        $app['config']->set('database.default', 'sqlite');
        $app['config']->set('database.connections.sqlite.database', ':memory:');

        return $app;
    }

    protected function setUp(): void
    {
        parent::setUp();

        // Fail-safe protection: ensure tests NEVER run against MySQL or production databases
        if (DB::connection()->getDriverName() !== 'sqlite' || DB::connection()->getDatabaseName() !== ':memory:') {
            throw new RuntimeException(
                'CRITICAL SAFETY ERROR: Tests must only execute against in-memory SQLite (:memory:). Prevented destructive operation on: '.DB::connection()->getDatabaseName()
            );
        }

        $this->withoutMiddleware([
            PreventRequestForgery::class,
            ValidateCsrfToken::class,
        ]);
    }

    protected function skipUnlessFortifyHas(string $feature, ?string $message = null): void
    {
        if (! Features::enabled($feature)) {
            $this->markTestSkipped($message ?? "Fortify feature [{$feature}] is not enabled.");
        }
    }
}
