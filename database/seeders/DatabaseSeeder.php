<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // Your actual resume as structured rows (no AI call). It stays unowned until you claim it:
        //   php artisan resume:claim you@example.com
        $this->call(ResumeSeeder::class);

        // Starter services for the Opportunity Agent.
        $this->call(ServiceSeeder::class);
    }
}
