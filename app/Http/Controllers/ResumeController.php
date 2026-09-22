<?php

namespace App\Http\Controllers;

use App\Models\ApplicationDraft;
use App\Models\Resume;
use App\Services\Resume\PdfTextExtractor;
use App\Services\Resume\ResumeClaimer;
use App\Services\Resume\ResumeCompiler;
use App\Services\Resume\ResumeParser;
use App\Services\Resume\WebsiteResumeScraper;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Response;

class ResumeController extends Controller
{
    // GET /resume/upload — the form. If there's a seeded resume whose email
    // matches this account and nobody's claimed it yet, show a claim button
    // instead of making anyone touch a terminal.
    public function showUploadForm(ResumeClaimer $claimer)
    {
        return view('resume.upload', [
            'resume' => Resume::current(),
            'claimable' => Resume::current() ? null : $claimer->claimableFor(request()->user()),
        ]);
    }

    // POST /resume/claim — attaches the one unowned resume matching your
    // account email, and makes it active. No CLI step involved.
    public function claim(Request $request, ResumeClaimer $claimer)
    {
        ['resume' => $resume, 'error' => $error] = $claimer->claim($request->user());

        if ($error) {
            return back()->withErrors(['resume_pdf' => $error]);
        }

        return redirect()->route('resume.upload')->with('status', "Claimed — {$resume->experiences->count()} roles, {$resume->skills->count()} skills, {$resume->projects->count()} projects.");
    }

    // POST /resume/upload
    public function upload(Request $request, PdfTextExtractor $pdfExtractor, WebsiteResumeScraper $scraper, ResumeParser $parser)
    {
        $data = $request->validate([
            'resume_pdf' => ['nullable', 'file', 'mimes:pdf', 'max:10240'],
            'website_url' => ['nullable', 'string', 'max:255'],
        ]);

        if (empty($data['resume_pdf']) && empty($data['website_url'])) {
            return back()->withErrors(['resume_pdf' => 'Upload a PDF and/or give your website URL.']);
        }

        $pdfText = null;
        $websiteText = null;

        if ($request->hasFile('resume_pdf')) {
            $storedPath = $request->file('resume_pdf')->store('resume-uploads', 'local');
            try {
                $pdfText = $pdfExtractor->extract(storage_path("app/{$storedPath}"));
            } catch (\Throwable $e) {
                return back()->withErrors(['resume_pdf' => $e->getMessage()]);
            }
        }

        if (! empty($data['website_url'])) {
            try {
                $websiteText = $scraper->scrape($data['website_url']);
            } catch (\Throwable $e) {
                // Non-fatal — we can still import from the PDF alone.
                $websiteText = null;
            }
        }

        ['resume' => $resume] = $parser->parseAndStore(
            pdfText: $pdfText,
            websiteText: $websiteText,
            activate: true,
            userId: $request->user()->id,
        );

        if (! $resume) {
            return back()->withErrors(['resume_pdf' => 'AI parsing failed — check RESUME_AI_PROVIDER/AGNES_API_KEY in .env and try again.']);
        }

        return redirect()->route('resume.upload')->with('status', "Saved — {$resume->experiences->count()} roles, {$resume->skills->count()} skills, {$resume->projects->count()} projects imported.");
    }

    // GET /resume/download — the plain, untailored active resume.
    public function download(ResumeCompiler $compiler)
    {
        $resume = Resume::current();

        if (! $resume) {
            abort(404, 'No active resume found. Upload one at /resume/upload first.');
        }

        $path = $compiler->compile($resume);

        return Response::download($path, "{$resume->full_name}-resume.pdf")->deleteFileAfterSend();
    }

    // GET /drafts/{draft}/resume — the version already tailored for that job's draft.
    public function downloadForDraft(ApplicationDraft $draft)
    {
        if (! $draft->resume_pdf_path || ! file_exists($draft->resume_pdf_path)) {
            abort(404, 'The tailored resume for this draft isn\'t ready yet. Try again in a minute — it compiles automatically shortly after the draft is created.');
        }

        $company = str($draft->job->company ?? 'application')->slug();

        return Response::download($draft->resume_pdf_path, "resume-{$company}.pdf");
    }
}
