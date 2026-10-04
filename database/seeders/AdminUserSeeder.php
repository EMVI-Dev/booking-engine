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
        $email = (string) (config('platform.admin_email') ?: 'admin@travelengine.id');
        $password = config('platform.admin_password');

        // The well-known fallback password is only for local machines and tests; staging,
        // preview and production must set a real one.
        if (! is_string($password) || $password === '') {
            if (! app()->environment(['local', 'testing'])) {
                throw new RuntimeException('Set ADMIN_PASSWORD before seeding this environment.');
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
