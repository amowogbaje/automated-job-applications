<?php

namespace App\Http\Controllers\Agent;

use App\Http\Controllers\Controller;
use App\Models\CvVersion;
use App\Models\Opportunity;
use App\Services\Agent\Profile\OpportunityResumeService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Storage;

class OpportunityResumeController extends Controller
{
    public function generate(Opportunity $opportunity, OpportunityResumeService $svc): RedirectResponse
    {
        if (! $svc->available()) {
            return back()->withErrors(['resume' => 'Import your resume first (Resume page); the tailored resume is built from your Profile.']);
        }
        try {
            $cv = $svc->generate($opportunity, true);
        } catch (\Throwable $e) {
            report($e);

            return back()->withErrors(['resume' => 'Could not build the resume: '.mb_substr($e->getMessage(), 0, 200)]);
        }

        return back()->with('status', $cv ? 'Tailored resume ready.' : 'No resume to build from.');
    }

    public function download(Opportunity $opportunity)
    {
        $cv = CvVersion::where('opportunity_id', $opportunity->id)->latest('id')->firstOrFail();
        abort_unless($cv->file_path && Storage::disk('local')->exists($cv->file_path), 404);

        return Storage::disk('local')->download($cv->file_path, 'resume-'.\Illuminate\Support\Str::slug($opportunity->company?->name ?? 'opportunity').'.pdf');
    }
}
