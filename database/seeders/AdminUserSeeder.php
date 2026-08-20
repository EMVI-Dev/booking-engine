<?php

namespace Database\Seeders;

use App\Models\PlatformSetting;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class AdminUserSeeder extends Seeder
{
    /**
     * Seed the platform administrator account and default platform settings.
     */
    public function run(): void
    {
        User::updateOrCreate(
            ['email' => 'admin@emvi.dev'],
            [
                'name' => 'Platform Admin',
                'password' => Hash::make('password'),
                'is_admin' => true,
                'email_verified_at' => now(),
            ]
        );

        // Ensure PlatformSetting current record exists with default structure
        PlatformSetting::current();
    }
}
