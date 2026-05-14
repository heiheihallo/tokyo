<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $email = env('ADMIN_EMAIL');
        $password = env('ADMIN_PASSWORD');

        if (! $email || ! $password) {
            return;
        }

        $user = User::firstOrNew(['email' => $email]);

        $user->forceFill([
            'name' => env('ADMIN_NAME', 'Tokyo Planner Admin'),
            'email' => $email,
            'password' => $password,
            'email_verified_at' => now(),
        ])->save();
    }
}
