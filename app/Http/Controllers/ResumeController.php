<?php

namespace App\Http\Controllers;

use App\Models\ApplicationDraft;
use App\Models\Resume;
use App\Services\Resume\PdfTextExtractor;
use App\Services\Resume\ResumeCompiler;
use App\Services\Resume\ResumeParser;
use App\Services\Resume\WebsiteResumeScraper;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Response;

class ResumeController extends Controller
{
    // GET /resume/upload — the form. Nothing here if you were seeded a
    // resume already (see ResumeSeeder) and it got claimed on your first
    // login — this is only for uploading a new one or replacing it.
    public function showUploadForm()
    {
        return view('resume.upload', ['resume' => Resume::current()]);
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
            abort(404, 'No compiled resume for this draft yet — run `php artisan applications:generate` or `resume:compile --job=' . $draft->job_listing_id . '`.');
        }

        $company = str($draft->job->company ?? 'application')->slug();

        return Response::download($draft->resume_pdf_path, "resume-{$company}.pdf");
    }
}
