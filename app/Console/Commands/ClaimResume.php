<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Services\Resume\ResumeClaimer;
use Illuminate\Console\Command;

class ClaimResume extends Command
{
    protected $signature = 'resume:claim
        {user : User ID or email to attach the resume to}
        {--resume= : Resume ID to claim; defaults to the newest unowned (user_id is null) resume}';

    protected $description = 'CLI fallback for attaching an unowned resume to a user account — the same thing can be done from /resume/upload with a button, no terminal required';

    public function handle(ResumeClaimer $claimer): int
    {
        $userOpt = $this->argument('user');
        $user = is_numeric($userOpt) ? User::find($userOpt) : User::where('email', $userOpt)->first();

        if (! $user) {
            $this->error("No user found matching \"{$userOpt}\". Register at /register first, then run this with that email.");
            return self::FAILURE;
        }

        ['resume' => $resume, 'error' => $error] = $claimer->claim($user, $this->option('resume') ? (int) $this->option('resume') : null);

        if ($error) {
            $this->error($error);
            return self::FAILURE;
        }

        $this->info("Resume #{$resume->id} ({$resume->full_name}) is now owned by {$user->email} and set as their active resume.");
        $this->line('  Experiences: ' . $resume->experiences()->count());
        $this->line('  Skills: ' . $resume->skills()->count());
        $this->line('  Projects: ' . $resume->projects()->count());

        return self::SUCCESS;
    }
}
