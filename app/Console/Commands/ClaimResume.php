<?php

namespace App\Console\Commands;

use App\Models\Resume;
use App\Models\User;
use Illuminate\Console\Command;

class ClaimResume extends Command
{
    protected $signature = 'resume:claim
        {user : User ID or email to attach the resume to}
        {--resume= : Resume ID to claim; defaults to the newest unowned (user_id is null) resume}';

    protected $description = 'Attach your own unowned resume (e.g. one loaded by ResumeSeeder) to your account — only works if the resume\'s email matches yours; nobody can claim someone else\'s resume with this';

    public function handle(): int
    {
        $userOpt = $this->argument('user');
        $user = is_numeric($userOpt) ? User::find($userOpt) : User::where('email', $userOpt)->first();

        if (! $user) {
            $this->error("No user found matching \"{$userOpt}\". Register at /register first, then run this with that email.");
            return self::FAILURE;
        }

        $resume = $this->option('resume')
            ? Resume::find($this->option('resume'))
            : Resume::whereNull('user_id')->latest('id')->first();

        if (! $resume) {
            $this->error($this->option('resume')
                ? "Resume #{$this->option('resume')} not found."
                : 'No unowned resume to claim. Run `php artisan db:seed --class=ResumeSeeder` first, or import one with `resume:import`/`/resume/upload`.');
            return self::FAILURE;
        }

        // A resume, once owned, is locked — this command never reassigns
        // someone else's resume, no override, no confirm prompt. Ownership
        // only ever moves by uploading a *new* resume as that other person.
        if ($resume->user_id) {
            $this->error($resume->user_id === $user->id
                ? "Resume #{$resume->id} is already yours."
                : "Resume #{$resume->id} ({$resume->full_name}) already belongs to another account. This command can't reassign someone else's resume — if you need your own, upload it at /resume/upload or with `resume:import --user={$user->email}`.");
            return self::FAILURE;
        }

        // The real ownership check: the resume's own email has to match the
        // account trying to claim it. This is what stops anyone who isn't
        // Gideon from claiming Gideon's seeded resume just by registering
        // first — the resume identifies its owner by the email on it.
        if ($resume->email && strcasecmp($resume->email, $user->email) !== 0) {
            $this->error("Resume #{$resume->id} belongs to {$resume->email}, not {$user->email} — you can only claim a resume whose email matches your account. Upload your own resume instead at /resume/upload or with `resume:import --user={$user->email}`.");
            return self::FAILURE;
        }

        $resume->update(['user_id' => $user->id]);
        $resume->activate(); // makes it this user's active resume, deactivating any others they already have

        $this->info("Resume #{$resume->id} ({$resume->full_name}) is now owned by {$user->email} and set as their active resume.");
        $this->line('  Experiences: ' . $resume->experiences()->count());
        $this->line('  Skills: ' . $resume->skills()->count());
        $this->line('  Projects: ' . $resume->projects()->count());

        return self::SUCCESS;
    }
}
