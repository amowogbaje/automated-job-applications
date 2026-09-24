# Job Feed Aggregator (Laravel)

Pulls fresh job listings from Arbeitnow, RemoteOK, WeWorkRemotely (RSS),
Adzuna, and Himalayas — all via legitimate, free/cheap APIs (no social
media scraping, no ToS risk). Filters by your keywords, dedups across
sources, and shows everything in one dashboard.

Your resume lives in the database, not as a PDF you hand-edit. It's already
seeded for you (Section 2) or imported via a web form/CLI, parsed into
structured tables, and every application — cover letter, tailored resume
PDF — is **compiled fresh from that data** for each job rather than
touching a file on disk.

The app sits behind native email/password sign-up and sign-in — no social
login. See Section 2 for how your existing resume gets automatically
attached to the first account you create.

AI calls run on **free providers by default** — no card required anywhere.
Cover letters/emails use **Groq**; resume parsing and per-job tailoring use
**Agnes AI**. Each is swappable independently via `.env` (down to paid
Anthropic if you want higher quality later) without touching any code.

## 1. Install

```bash
composer update   # not `composer install` — composer.lock was removed
                   # because this package adds two new dependencies
                   # (barryvdh/laravel-dompdf, smalot/pdfparser)
cp .env.example .env   # or keep your existing .env — this repo's .env
                        # already has the job-aggregator/AI/mail blocks merged in
php artisan key:generate
```

Then:

1. Run the migrations:
   ```bash
   php artisan migrate
   ```
2. Get a **free** Groq API key at https://console.groq.com/keys (no card
   needed) and set it in `.env`:
   ```env
   AI_PROVIDER=groq
   GROQ_API_KEY=gsk_...
   GROQ_MODEL=llama-3.3-70b-versatile
   ```
   This is used for cover letters and application emails.
   Want to use Anthropic instead (paid, higher quality)? Set
   `AI_PROVIDER=anthropic` and fill in `ANTHROPIC_API_KEY` — nothing else
   in the codebase changes; every command asks for `AiClientInterface`,
   not a concrete class.
3. Get a **free** Agnes AI key at https://agnes-ai.com (no card needed) and
   set it in `.env`:
   ```env
   RESUME_AI_PROVIDER=agnes
   AGNES_API_KEY=...
   AGNES_MODEL=agnes-2.0-flash
   ```
   This is used specifically for the resume pipeline — `resume:import`
   parsing and the per-job tailoring in `ResumeTailor` — independently of
   whatever `AI_PROVIDER` is set to above. Set `RESUME_AI_PROVIDER=groq` or
   `anthropic` instead if you'd rather point resume work at one of those.
4. If you want the Adzuna source too, add `ADZUNA_APP_ID` /
   `ADZUNA_APP_KEY` (free at https://developer.adzuna.com/). Every other
   source needs no keys.

## 2. Sign in and get your resume

The whole app sits behind native email/password auth — no Google/social
login, just `/register` and `/login`.

**You don't need to re-upload your resume.** It's already been seeded into
the database as structured rows (`database/seeders/ResumeSeeder.php` — your
real experience, skills, and projects, no AI call involved), loaded
*unowned*. Attaching it to your account is one click, no terminal needed:

1. Whoever runs the seeder once (`php artisan db:seed --class=ResumeSeeder`
   — an operator/deploy step, not something an everyday user should ever
   need to touch).
2. Go to `/register` and create your account, **using the same email the
   resume was written for** (Gideon: `amowogbajegideon@gmail.com`).
3. `/resume/upload` will show a **"Claim this resume"** button instead of
   the upload form, the moment there's an unowned resume matching your
   email. Click it — that's the whole flow.

That button is backed by the same guard as before, so it's not a
general-purpose "grab any unowned resume" button:
- It refuses if the resume already has an owner. Ownership never moves
  once set, no reassignment, no override.
- It only shows up at all if the resume's own `email` field matches your
  account's email — so only an account registered as
  `amowogbajegideon@gmail.com` will ever see it. Someone else registering
  first, or with a different email, never sees a claim button for it.

So in practice: **only you can claim your own resume.** Everyone else who
signs up gets a normal, independent account with nothing attached — they
just see the plain upload form (below) and their resume ties straight to
their account, no claim step involved at all.

A `php artisan resume:claim your@email.com` command still exists doing the
exact same thing under the hood, kept only as a scripting/ops fallback —
nothing in the day-to-day flow requires it.

**Scope note:** resumes, job matching, applied/dismissed status, drafts,
and email automation are all per-account now (see §6 and §8) — the only
thing still shared across the whole install is the underlying pool of
fetched `job_listings` itself, which is the point: everyone's accounts
score and filter that same shared pool independently, rather than each
running their own separate fetch.

## 3. Import/update a resume (form or CLI)

```bash
php artisan resume:import --pdf=/path/to/Gideon_Amowogbaje_Resume_General.pdf --website=amowogbaje.com
```

- `--pdf` is parsed with `smalot/pdfparser` (pure PHP — no `pdftotext`
  binary needed) and is the **only source of facts**: employers, dates,
  skills, projects.
- `--website` is fetched and stripped to plain text, but the AI is
  explicitly instructed to use it **only for tone and summary phrasing**
  — it will not pull in a skill, employer, or project from your site
  unless that same fact is already in the PDF. This stops a marketing
  blurb ("AI integration expert") from turning into an invented resume
  line.
- You can pass either flag alone. Re-run any time (e.g. after updating
  your PDF) — each import is a new row; the newest one becomes active
  automatically unless you pass `--no-activate`.
- `--user=` (ID or email) sets who owns the import — useful if more than
  one account exists on this install. Omit it and it defaults to the
  only/first user account, which covers the normal single-operator case.

This calls your configured AI provider to structure the text into the
tables below. It's instructed never to invent anything not present in
your actual resume text.

## 4. The `resumes` database schema

```
resumes
  id, user_id (nullable — see Section 2), source(pdf_upload|website|merged|manual), is_active,
  full_name, headline, email, phone, location,
  website_url, linkedin_url, github_url,
  summary,
  raw_pdf_text, raw_website_text,        -- kept for audit / re-parsing
  timestamps

resume_experiences
  id, resume_id, job_title, company, location,
  start_date, end_date, is_current,       -- end_date null + is_current = "Present"
  bullets (json array of strings),
  sort_order, timestamps

resume_education
  id, resume_id, institution, degree, field,
  start_date, end_date, sort_order, timestamps

resume_skills
  id, resume_id, category, name, sort_order, timestamps
  -- category is free-text (backend, frontend, database, apis_integrations,
  -- applied_ai, testing_devops, other, ...) so new buckets don't need a migration
  -- unique(resume_id, category, name)

resume_projects
  id, resume_id, name, description, tech_stack (json array), url,
  sort_order, timestamps

resume_certifications
  id, resume_id, name, sort_order, timestamps
```

Only one `resumes` row is `is_active` **per user** at a time — that's the
one every command below reads from (`Resume::current()`, in
`app/Models/Resume.php`, which defaults to the logged-in user and falls
back to the only/first account for CLI/scheduler runs). Old imports stay
in the table so you can compare or roll back; nothing is overwritten in
place.

`application_drafts` also gained two columns:
`resume_snapshot` (json — which real skills/projects the AI chose to lead
with for that specific job, plus a tailored one-line headline) and
`resume_pdf_path` (the PDF compiled from that snapshot).

## 5. Compile your resume back out as a PDF

```bash
php artisan resume:compile                 # plain, untailored PDF
php artisan resume:compile --job=42        # tailored for job listing #42
```

Or from the browser: **`/resume/download`** always compiles a fresh plain
PDF on demand; each row on **`/drafts`** has a "Download the resume
tailored for this job" link once a draft has been generated.

Tailoring (`App\Services\Resume\ResumeTailor`) asks the AI which of your
**real** skills and projects to lead with for a given job description and
what to call out as a gap — every name it returns is checked against what's
actually in `resume_skills`/`resume_projects` before use, so it can reorder
and select but never invent. `App\Services\Resume\ResumeCompiler` then
renders `resources/views/resume/pdf.blade.php` with that ordering via
`barryvdh/laravel-dompdf` and saves the PDF to `storage/app/resumes/`.

## 6. Career profile (job matching, per-user)

This used to be four `.env` vars shared across the whole install
(`JOB_KEYWORDS`, `JOB_REQUIRED_SKILLS`, `JOB_MIN_REQUIRED_MATCHES`,
`JOB_EXCLUDED_KEYWORDS`) — everyone who logged in saw the same filtered
feed, tuned for one person's stack. Now it's `/profile`, per account:

- **Required skills** — a listing must mention at least "minimum
  required matches" of these or it's filtered out of *your* feed
  entirely. Leave empty to skip this gate (falls back to needing one
  keyword match instead, same as the old behavior).
- **Minimum required matches** — how many of the above have to hit.
- **Nice-to-have keywords** — boost a listing's score for you and show
  as gold tags, without being required.
- **Excluded keywords** — one hit anywhere in the listing drops it
  from your view outright, regardless of everything else.

**Set it two ways:** type it in by hand (same comma-separated format as
the old env vars), or click **"Populate from resume"** to pull every
skill off your active resume into required skills + keywords in one
go — review and adjust from there, since a resume can't tell the app
which terms to exclude or how strict to be.

**What changed under the hood:** `jobs:fetch` no longer filters by
anyone's specific skills at ingestion — it stores broadly (a small
built-in, non-configurable backstop just keeps out obviously non-tech
postings from general boards like Adzuna) so nothing potentially
relevant to *any* account gets discarded before they've even logged
in. Each account's `/jobs` page then scores and filters that same
shared pool live, against that account's own profile — two people on
one install genuinely see different, personally-relevant feeds from
the same underlying data.

**Applied/dismissed status, drafts, and email automation are now
per-account too** — this used to be a flagged limitation (one shared
`is_applied`/`is_dismissed` per job, one shared draft, one shared
`MAIL_DIGEST_TO`), fixed as of this pass:
- A new `job_listing_user_states` table holds applied/dismissed/notified
  **per (job, user) pair** instead of flat columns on `job_listings`.
  Dismissing a job only hides it from you.
- `application_drafts` gained a `user_id` — each account gets its own
  draft for a shared job listing, not one draft everyone fights over.
- `applications:process` (the hourly auto-apply/digest command) now
  loops over every account with an active resume, scores against *that
  account's* profile, and sends to *that account's* notification email
  — see the **Email automation** section on `/profile` for the digest
  toggle, the auto-send toggle (off by default, same safety reasoning
  as before), and an optional override address.

## 6a. A tailored resume for any listing, not just drafts

Every job fetched already has its full `description` stored (every
source maps it — Arbeitnow, RemoteOK, WeWorkRemotely, Adzuna, Himalayas
all populate it), and `/jobs` now shows it inline via an expandable
"Job description" toggle.

Because the description is there, **any listing gets a "Generate
tailored resume" button** — not just the ones that turn into email
drafts. Clicking it runs the same `ResumeTailor` + `ResumeCompiler`
pipeline the auto-apply flow uses (reorders your real skills/projects
around that specific description, never invents anything), opens the
PDF inline in a new tab to review, with a separate "Download" link next
to it. The result is cached on the job row (`tailored_resume_path`) so
re-clicking is instant — it only re-tailors if your active resume has
changed since, or you visit with `?regenerate=1`.

Right next to it is **"Generate cover letter"** — same idea, same
tailoring snapshot reused (no duplicate AI call for the pair), written
by a new shared `CoverLetterWriter` service that both this button and
`applications:generate` now call, so the letter you'd get from either
path is identical. Shows as a plain read-only page with a copy button
and a "Download as .txt" link. Both are cached independently, so viewing
one doesn't force-regenerate the other.

## 7. AI-assisted application drafts (compose messages + tailor resume)

Two related but separate commands, both reading from the `resumes` tables:

- `applications:generate` — manual-review drafts for your top-scoring
  jobs regardless of apply method, shown on `/drafts` for you to edit
  and send yourself, each with its own tailored resume PDF attached.
- `applications:process` — the hourly automated pass below, which
  auto-sends for email-apply jobs and digests everything else.

This does **not** auto-submit applications anywhere. LinkedIn, Indeed,
and most ATS platforms explicitly prohibit automated applications and
will ban accounts that try it — and a blast of identical AI applications
tends to read as spam to recruiters anyway. What it does instead:

1. **Import your resume once** (Section 2 or 3 above).
2. **Generate tailored drafts:**
   ```bash
   php artisan applications:generate --limit=10 --min-score=2
   ```
   For each job above the score threshold: `ResumeTailor` picks which
   real skills/projects to lead with, the AI writes a cover letter built
   from that tailored emphasis (never inventing anything beyond your
   actual profile), and `ResumeCompiler` renders a matching resume PDF.
3. **Review at `/drafts`** — edit the letter inline, download the
   tailored resume, then click "I've sent this" once you've actually
   applied (via the platform's real apply flow or email) to mark it
   applied. Or discard it.

### On the third-party resume/apply APIs you mentioned

**EvalCV**, **maxcv**, and **Workopia** couldn't be verified as
established services when checked, so they weren't wired in blind —
worth confirming their docs/uptime yourself before trusting a production
workflow to them. ApiLayer's "Resume Parser API" is real (their free
tier is 100 parses/month as of that check, not 50). Since the AI
provider already handles resume parsing and cover-letter generation well
from raw text with no extra signup, that's used directly instead of
adding another API dependency — swap in one of those services later if
you want a second opinion on parsing accuracy; they'd slot into
`ResumeParser` alongside the AI call.

## 8. Hourly auto-apply vs. digest split

`php artisan applications:process` (scheduled hourly, right after
`jobs:fetch` — see Section 9) runs **once per account** with an active
resume, and for each one splits that account's own matching jobs into
two lanes based on how the listing itself asks to be applied to:

- **"Email your resume/CV/application to..."** jobs → detected via
  regex in `FetchJobs::detectApplyMethod()`, which deliberately only
  matches when the description *explicitly* asks for an email
  application (not just any email address mentioned in the post). The
  AI drafts a ready-to-send application email from that account's real
  profile, and — if auto-send is on for that account — it's sent
  automatically with a freshly compiled, job-tailored resume PDF
  attached. If auto-send is off, the same draft lands on `/drafts`
  instead.
- **Everything else (forms, "apply on our careers page", job board
  links)** → bundled into that account's own digest email, listing
  only jobs it hasn't already been notified about. Nothing here gets
  auto-applied — you click through and apply yourself.

**Before you turn on auto-send:** leave it off (the default, per
account) for your first day or two. In that mode, email-apply jobs
still get an AI-written draft and compiled resume, but land as a
`ready` draft on `/drafts` instead of actually being emailed — so you
can sanity-check tone, accuracy, and formatting on real jobs before
letting it send unattended. Once you're happy with a batch, flip
**"Auto-send email-apply applications"** on at `/profile`.

Set up per account, at `/profile` → Email automation:
- **Send digests/applications to** — defaults to that account's own
  login email; only set this if you want it routed somewhere else.
- **Send me the hourly digest** — on by default.
- **Auto-send email-apply applications** — off by default, same
  reasoning as above.

Still needed in `.env` (install-wide, not per-account):
- `RESUME_PDF_PATH` — only used as a **fallback** if PDF compilation
  fails for some reason; normally every send attaches a freshly
  compiled PDF from the database instead
- Standard `MAIL_*` SMTP settings — this needs real mail credentials
  (Mailgun, Postmark, SES, or even a Gmail app password) to actually
  send anything; Laravel's `log` mailer will just write emails to
  `storage/logs/laravel.log` instead, useful for testing without
  risking a real send

One thing to watch: the "email apply" detector only fires on explicit
apply-by-email instructions, on purpose — it's better to have a
handful of email-apply jobs fall into the digest by mistake than to
auto-email a company because their support address happened to be
mentioned in the post. If you find real email-apply jobs consistently
landing in the digest instead, share a couple of example descriptions
and the pattern can be tightened.

## 9. Automation (scheduler)

`routes/console.php` wires:

```php
Schedule::command('jobs:fetch')->hourly()->withoutOverlapping()->after(fn () => Artisan::call('applications:process'));
Schedule::command('jobs:prune')->daily();
```

So each hour: fetch new listings, then immediately run the auto-apply/
digest split against whatever's new. `jobs:prune` (also new — the
original README promised this but the command didn't actually exist
yet) deletes listings older than 14 days, skipping anything with a
draft still awaiting your review. Make sure your server has this cron
entry (standard Laravel requirement):

```
* * * * * cd /path-to-your-project && php artisan schedule:run >> /dev/null 2>&1
```

Uncomment the `applications:generate` line in `routes/console.php` too
if you also want manual-review drafts generated automatically for every
new job, not just the auto-apply/digest lanes.

## 10. View the dashboard

```bash
php artisan serve
```

Visit `http://localhost:8000` — filter by keyword or time window
(24h / 3 days / all time), mark jobs as applied, or dismiss ones you
don't want to see again. `/drafts` shows generated applications;
`/resume/download` always gives you a fresh plain PDF of your active
database resume.

## 11. Leads — proactive outreach, not just job boards

`/leads` is a pipeline for companies worth reaching out to directly,
before they've even posted a job — the same instinct as the "show, don't
tell" prototyping pitch (build something small, send it, follow up),
retargeted from local-business web design to backend development work.

- **`/leads/discover`** searches Hunter.io's public company directory —
  describe who you're after in plain language and/or check off a stack
  (NestJS, FastAPI, Go, Node, Laravel) and a headquarters region. This is
  **free** and only returns company names/domains, never a contact.
- Matches get saved as leads with status `new`. Duplicates (same domain,
  same account) are skipped automatically.
- **"Reveal contact"** on an individual lead is a separate, explicit
  click — it spends one Hunter credit to look up a decision-maker/
  technical contact at that domain specifically. Nothing is revealed in
  bulk.
- Work the pipeline with the status dropdown (`new` → `contacted` →
  `replied` → `won`/`lost`) and free-text notes per lead.
- Add a lead manually any time — for a company you found yourself, no
  API involved.

Needs `HUNTER_API_KEY` in `.env` (free tier: 25 Discover calls + 50
searches/month) — get one at hunter.io. Without a key, the discover form
just tells you to set it; everything else in the app is unaffected.

**Batching the free tier:** the form's "Suggested next" buttons run one
call per business region (Americas / EMEA / Asia-Pacific) with every
stack combined into that single call via `match: any` keywords — 3 calls
covers a first pass across every stack and dozens of countries, instead
of 25 calls for every stack × country combination. A usage bar shows
calls used this calendar month, and recent searches are listed so you
don't accidentally repeat a combo. Spend whatever's left narrowing into
whichever region/stack turned up the most `results_count`.

**Where this stays legitimate, on purpose:** every company/contact
comes from Hunter's own database, which only surfaces information from
public sources — nothing here scrapes LinkedIn, Google Maps, or any site
against its terms of service, and no email is pulled until you
explicitly choose to reveal one, one company at a time. If you send
outreach based on what this finds, treat it like any cold email: identify
yourself, make it easy to opt out, and don't send at a volume or
frequency that reads as spam — CAN-SPAM/GDPR-style basics apply to B2B
outreach same as anything else.

## Adding more job sources later

Implement `App\Services\JobSources\JobSourceInterface` (one method:
`fetch()` returning a normalized array) and add an instance of it to
the `$sources` array in `FetchJobs::__construct()`. Good next
candidates: Remotive API, Jobicy API, or a targeted Adzuna search per
country if you're open to relocating.

## Why not Instagram/LinkedIn/Facebook/Twitter scraping?

LinkedIn, Facebook, and Instagram block scraping aggressively and have
pursued scrapers legally — there's no stable or low-risk way to pull
"job posted in the last 24h" from them. X/Twitter has a real search
API but it's paid for meaningful volume. The sources here give you
comparable or better coverage of actual open roles with zero legal
risk and no maintenance burden fighting anti-bot systems.

## Project structure highlights

```
app/Http/Controllers/AuthController.php     # native register/login/logout
resources/views/auth/{login,register}.blade.php
database/seeders/ResumeSeeder.php           # your resume, pre-loaded, unowned until claimed
app/Services/AI/AiClientInterface.php       # provider-agnostic contract
app/Services/AI/GroqClient.php              # free provider (default, cover letters)
app/Services/AI/AgnesClient.php             # free provider (default, resume pipeline)
app/Services/AI/AnthropicClient.php         # optional paid fallback, either role
app/Services/Resume/PdfTextExtractor.php    # PDF -> text (smalot/pdfparser)
app/Services/Resume/WebsiteResumeScraper.php# site -> text (tone context only)
app/Services/Resume/ResumeParser.php        # text(s) -> resumes DB tables
app/Services/Resume/ResumeTailor.php        # per-job skill/project selection
app/Services/Resume/ResumeCompiler.php      # resumes DB tables -> PDF
app/Console/Commands/ImportResume.php       # resume:import
app/Console/Commands/CompileResume.php      # resume:compile
app/Console/Commands/GenerateApplicationDrafts.php # applications:generate
app/Console/Commands/ProcessApplications.php       # applications:process
app/Console/Commands/PruneOldJobs.php       # jobs:prune
app/Console/Commands/ClaimResume.php        # resume:claim — attach an unowned resume to your account
app/Models/Resume.php + Resume{Experience,Education,Skill,Project,Certification}.php
resources/views/resume/pdf.blade.php        # the compiled resume's layout
resources/views/resume/upload.blade.php     # web form for importing/replacing a resume
```

## Requirements

- PHP >= 8.2 with the usual extensions Laravel needs (`pdo_mysql`, `mbstring`,
  `openssl`, `tokenizer`, `xml`, `ctype`, `json`, `bcmath`)
- Composer 2.x
- MySQL 5.7+/8.x (or MariaDB)
- A free Groq API key (or a paid Anthropic key if you switch providers)
