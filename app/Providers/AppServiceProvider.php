<?php

namespace App\Providers;

use App\Http\Middleware\EnsureOnPlatformDomain;
use App\Http\Middleware\EnsureUserIsAdmin;
use App\Models\Package;
use App\Models\Product;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Http\Request;
use Illuminate\Pagination\Paginator;
use Illuminate\Routing\UrlGenerator;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;
use Livewire\Livewire;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Paginator::defaultView('vendor.pagination.tailwind');
        Paginator::defaultSimpleView('vendor.pagination.tailwind');

        $this->ensureSqliteDatabaseExists();
        $this->configureDefaults();

        // Livewire re-runs only "persistent" middleware on component actions. Without this,
        // admin-only and platform-only checks ran on page load but not on later button clicks.
        $this->configureEmailVerificationLinks();

        Livewire::addPersistentMiddleware([
            EnsureUserIsAdmin::class,
            EnsureOnPlatformDomain::class,
        ]);
    }

    /**
     * Operators are signed in on their slug host, not the platform host they registered on,
     * so the verification link must open there or it would land on a logged-out session.
     */
    protected function configureEmailVerificationLinks(): void
    {
        VerifyEmail::createUrlUsing(function (User $notifiable): string {
            $parameters = [
                'id' => $notifiable->getKey(),
                'hash' => sha1($notifiable->getEmailForVerification()),
            ];
            $expires = now()->addMinutes((int) config('auth.verification.expire', 60));

            $operator = $notifiable->currentOperator();

            if ($operator === null || blank($operator->slug)) {
                return URL::temporarySignedRoute('verification.verify', $expires, $parameters);
            }

            // A generator bound to the desk host, so the signature covers the URL the operator opens.
            $generator = new UrlGenerator(app('router')->getRoutes(), Request::create($operator->slugDeskRoot()));
            $generator->setKeyResolver(fn (): array => [config('app.key'), ...(config('app.previous_keys') ?? [])]);

            return $generator->temporarySignedRoute('verification.verify', $expires, $parameters);
        });
    }

    /**
     * Ensure the SQLite database file exists on disk if configured.
     */
    protected function ensureSqliteDatabaseExists(): void
    {
        if (config('database.default') === 'sqlite') {
            $dbPath = config('database.connections.sqlite.database');
            if ($dbPath && $dbPath !== ':memory:' && ! file_exists($dbPath)) {
                @mkdir(dirname($dbPath), 0755, true);
                @touch($dbPath);
            }
        }
    }

    /**
     * Configure default behaviors for production-ready applications.
     */
    protected function configureDefaults(): void
    {
        Date::use(CarbonImmutable::class);

        Model::preventLazyLoading(app()->isLocal());

        Relation::morphMap([
            'package' => Package::class,
            'product' => Product::class,
        ]);

        DB::prohibitDestructiveCommands(
            app()->isProduction(),
        );

        Password::defaults(fn (): ?Password => app()->isProduction()
            ? Password::min(12)
                ->mixedCase()
                ->letters()
                ->numbers()
                ->symbols()
                ->uncompromised()
            : null,
        );
    }
}
