<?php

use App\Models\PlatformSetting;

/**
 * @return list<string>
 */
function secretEnvKeys(): array
{
    $example = (string) file_get_contents(base_path('.env.example'));
    $secrets = substr($example, (int) strpos($example, '# 2. SECRETS'));

    preg_match_all('/^([A-Z0-9_]+)=(.*)$/m', $secrets, $matches);

    return $matches[1];
}

test('the env example separates general settings from secrets and leaves secrets empty', function () {
    $example = (string) file_get_contents(base_path('.env.example'));

    expect($example)->toContain('# 1. GENERAL')->toContain('# 2. SECRETS');

    preg_match_all('/^([A-Z0-9_]+)=(.*)$/m', substr($example, (int) strpos($example, '# 2. SECRETS')), $matches, PREG_SET_ORDER);

    expect($matches)->not->toBeEmpty();

    foreach ($matches as [, $key, $value]) {
        expect(trim($value))->toBe('', "{$key} must be empty in .env.example");
    }

    foreach (['APP_KEY', 'DB_PASSWORD', 'R2_SECRET_ACCESS_KEY', 'DOKU_LIVE_SECRET_KEY', 'LARAVEL_CLOUD_API_TOKEN'] as $key) {
        expect(secretEnvKeys())->toContain($key);
    }
});

test('no config file ships a default value for a secret', function () {
    $configSource = collect(glob(config_path('*.php')))->map(fn (string $file): string => (string) file_get_contents($file))->implode("\n");

    foreach (secretEnvKeys() as $key) {
        if ($key === 'APP_KEY') {
            continue;
        }

        preg_match_all("/env\\('".$key."'\\s*,\\s*([^)]+)\\)/", $configSource, $defaults);

        foreach ($defaults[1] as $default) {
            expect(trim($default))->toBeIn(['null', "''", '""'], "{$key} has a default in config");
        }
    }
});

test('payment secrets are never copied into the platform settings table', function () {
    config(['doku.sandbox.secret_key' => 'never-in-db', 'doku.live.secret_key' => 'never-in-db']);

    PlatformSetting::query()->delete();

    expect(json_encode(PlatformSetting::current()->settings))->not->toContain('never-in-db');
});
