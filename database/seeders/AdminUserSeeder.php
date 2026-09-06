<?php

namespace Database\Seeders;

use App\Models\PlatformSetting;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use RuntimeException;

class AdminUserSeeder extends Seeder
{
    /**
     * Seed the platform administrator account and default platform settings.
     */
    public function run(): void
    {
        $email = (string) env('ADMIN_EMAIL', 'admin@emvi.dev');
        $password = env('ADMIN_PASSWORD');

        if (! is_string($password) || $password === '') {
            if (app()->environment('production')) {
                throw new RuntimeException('Set ADMIN_PASSWORD in .env before seeding production.');
            }

            $password = 'password';
        }

        User::updateOrCreate(
            ['email' => $email],
            [
                'name' => 'EMVI',
                'password' => Hash::make($password),
                'is_admin' => true,
                'email_verified_at' => now(),
            ]
        );

        PlatformSetting::current();
    }
}
