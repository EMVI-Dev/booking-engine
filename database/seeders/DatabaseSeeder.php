<?php

namespace Database\Seeders;

use App\Models\Operator;
use App\Models\Plan;
use App\Models\User;
use App\Services\MediaStore;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

/**
 * First-run seed: plans, the platform admin and the public demo shop.
 *
 * Safe to run again on a live database: anything that already exists is skipped, so a
 * second db:seed never resets the admin password, plan edits or the demo shop. Media storage
 * is verified before the demo uploads its photos (no separate media:check needed).
 * To rebuild only the demo shop, use demo:refresh.
 */
class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        if (Plan::ensureDefaultPlans()) {
            $this->say('Plans created.');
        } else {
            $this->say('Plans already exist: skipped (Admin → Plans edits are kept).');
        }

        if ($this->adminExists()) {
            $this->say('Platform admin already exists: skipped (password unchanged).');
        } else {
            $this->call(AdminUserSeeder::class);
        }

        if ($this->demoOperatorExists()) {
            $this->say('Demo shop already exists: skipped (use demo:refresh to rebuild it).');
        } else {
            $storage = MediaStore::verifyWritable();
            $this->say("Media storage OK ({$storage['disk']}): files are served from {$storage['base_url']}");
            $this->call(DemoOperatorSeeder::class);
        }
    }

    private function adminExists(): bool
    {
        $email = (string) (config('platform.admin_email') ?: 'admin@travelengine.id');

        return User::query()->where('email', $email)->where('is_admin', true)->exists();
    }

    private function demoOperatorExists(): bool
    {
        return Operator::query()
            ->where(fn ($query) => $query->where('is_demo', true)->orWhere('slug', config('demo.slug', 'demo')))
            ->exists();
    }

    private function say(string $message): void
    {
        $this->command->info($message);
    }
}
