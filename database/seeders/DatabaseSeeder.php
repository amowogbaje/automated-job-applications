<?php

namespace Database\Seeders;

use App\Models\Project;
use App\Models\Task;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database with a couple of sample projects and
     * tasks so the app is easy to explore right after installation.
     */
    public function run(): void
    {
        $website = Project::create(['name' => 'Website Redesign']);
        $launch = Project::create(['name' => 'Product Launch']);

        $this->createTasks($website, [
            'Audit existing landing pages',
            'Design new homepage mockups',
            'Migrate blog content',
        ]);

        $this->createTasks($launch, [
            'Finalize pricing page',
            'Write launch announcement',
            'Brief the support team',
        ]);

        // A couple of tasks with no project, to demonstrate the "All tasks" view.
        $this->createTasks(null, [
            'Renew SSL certificate',
            'Weekly backup check',
        ]);

        // Loads your actual resume (Gideon Amowogbaje) as structured rows —
        // no AI call needed, and you won't have to upload the PDF again.
        // It stays unowned until you explicitly run `php artisan resume:claim`
        // (see ResumeSeeder / ClaimResume command) — nothing attaches it
        // to an account automatically.
        $this->call(ResumeSeeder::class);
    }

    /**
     * @param  array<int, string>  $names
     */
    private function createTasks(?Project $project, array $names): void
    {
        foreach ($names as $index => $name) {
            Task::create([
                'project_id' => $project?->id,
                'name' => $name,
                'priority' => $index + 1,
            ]);
        }
    }
}
