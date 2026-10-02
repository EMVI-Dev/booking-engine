<?php

namespace App\Console\Commands;

use App\Services\Integrations\LaravelCloudClient;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Throwable;

#[Signature('cloud:environments')]
#[Description('Check the Laravel Cloud API token and list environment ids (for LARAVEL_CLOUD_ENVIRONMENT_ID).')]
class ListCloudEnvironmentsCommand extends Command
{
    public function handle(LaravelCloudClient $cloud): int
    {
        try {
            $environments = $cloud->environments();
        } catch (Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        if ($environments === []) {
            $this->warn('The token works, but it cannot see any environments.');

            return self::SUCCESS;
        }

        $this->table(
            ['Application', 'Environment', 'LARAVEL_CLOUD_ENVIRONMENT_ID', 'Vanity domain', 'Status'],
            array_map(fn (array $row): array => [$row['application'], $row['environment'], $row['environment_id'], $row['vanity_domain'] ?? '-', $row['status'] ?? '-'], $environments),
        );

        $current = (string) config('services.laravel_cloud.environment');
        $this->line($current === '' ? 'LARAVEL_CLOUD_ENVIRONMENT_ID is not set yet. Copy the production id into .env on Cloud.' : "LARAVEL_CLOUD_ENVIRONMENT_ID is set to {$current}.");

        return self::SUCCESS;
    }
}
