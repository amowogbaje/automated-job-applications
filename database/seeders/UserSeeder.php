<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * Creates the single login. Set ADMIN_EMAIL and ADMIN_PASSWORD in .env, then:
 *   php artisan db:seed --class=UserSeeder
 */
class UserSeeder extends Seeder
{
    public function run(): void
    {
        $email = env('ADMIN_EMAIL');
        $password = env('ADMIN_PASSWORD');

        if (! $email || ! $password) {
            $this->command?->error('Set ADMIN_EMAIL and ADMIN_PASSWORD in .env first.');

            return;
        }

        User::updateOrCreate(
            ['email' => $email],
            ['name' => env('ADMIN_NAME', 'Admin'), 'password' => Hash::make($password)]
        );
    }
}
