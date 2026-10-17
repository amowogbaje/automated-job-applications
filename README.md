# Opportunity Agent + Job Feed (Laravel 11)

One app, one interface (the Opportunity Agent sidebar look).

* **Dashboard → Overview | Jobs | Job interests.** Overview is the outreach pipeline (owner only).
* **Jobs** is a single feed: job boards *and* social hiring posts, merged.
* **Job interests** is where you choose the skills you want jobs for (e.g. just `Laravel`). Those skills drive the feed **and** the optional emails. Nothing else is shown or sent.
* **Resume** (the one already uploaded) and **Profile** (edit it LinkedIn-style) are kept; each job's tailored resume and cover letter are built from them.

## How jobs reach you

1. `jobs:fetch` (hourly) pulls Arbeitnow, RemoteOK, WeWorkRemotely, Adzuna, Himalayas and Greenhouse/Lever/Ashby boards, dedupes, translates.
2. `social:fetch` (every 30 min) finds hiring posts, only for skills somebody chose.
3. `jobs:match` runs at the end of both: each new listing is scored against every account's chosen skills and the verdict is stored on `job_listing_user_states` (score, tier, matched skills). A watermark (`career_profiles.last_matched_id`) means a listing is scored once.
4. The feed reads those stored matches with plain SQL. Saving new skills clears your matches and re-scores the last 14 days at once (`jobs:match --user=ID --backfill` does the same from the CLI).
5. `jobs:alerts` (hourly, :10) emails opted-in accounts. **Off by default.** Only jobs matching your skills at or above your chosen tier, each emailed once, max 25 per email; "daily" accounts get one email after 07:00 app time. Use "Send me a test email" on Job interests to check mail works.

New accounts start with no skills, so the feed is empty until they choose some.

## Cron (cPanel)

```
* * * * * cd /path/to/app && php artisan schedule:run >> /dev/null 2>&1
* * * * * cd /path/to/app && php artisan queue:work --stop-when-empty --max-time=55 >> /dev/null 2>&1
```

## Upgrading from the previous version

`php artisan migrate` (adds `2026_10_18_000001_add_skill_feed_columns`; it also switches every account's email alerts **off**). Then each account picks skills on **Job interests**, or run `php artisan jobs:match --backfill` after setting `required_skills`.

## Removed in this version

Employer accounts and posting jobs, public job pages, applications, application drafts and auto-apply, the apply-service webhook, tasks/projects board, leads, the hourly job-digest email, and the old `/social` page (social posts are now in the Jobs feed). Their old tables are left in place by the old migrations; nothing reads them. Your original zip still has all that code.

## Owner gating

`/agent/*` is for the owner only (`AGENT_OWNER_EMAILS`, or the first registered account). Everyone else sees Jobs, Job interests, Resume and Profile.

## Tests

`tests/Unit` (matcher, skills, dedupe, translation, agent helpers) and `tests/Feature/JobFeedTest.php` (matching, backfill, feed, alerts) run with `php artisan test` in a real install.


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


## Opportunity Agent (merged in) — `/agent`

A personal business-development workspace inside this app: opportunities with an evidence trail, a research agent,
services/projects/CVs/signatures, and AI + search provider failover. Laravel 11, Tailwind + Alpine from CDN, no build step.

- **Who sees it:** the account(s) in `AGENT_OWNER_EMAILS`, or — if empty — the first account ever registered. Others get a 404.
- **Setup:** `php artisan migrate` (adds the agent tables), optionally `php artisan db:seed --class=ServiceSeeder`.
- **Keys (.env):** `GEMINI_API_KEY`, `GROQ_API_KEY`, `DEEPSEEK_API_KEY` (at least one) and optionally `TAVILY_API_KEY`, `SERPER_API_KEY`, `BRAVE_SEARCH_API_KEY`.
  Order, enable/disable, models, daily limits and cooldown resets live at **Agent → Providers**.
- **Research runs** use the database queue: keep the cron `* * * * * php artisan queue:work --stop-when-empty --max-time=55`
  (or set `RESEARCH_RUN_MODE=sync`).
- **Existing job features on the same failover:** set `AI_PROVIDER=failover` (and/or `RESUME_AI_PROVIDER=failover`).

## Outreach Studio (Phase 5) — `/agent/opportunities/{id}/outreach`

Open it from an opportunity ("Outreach Studio" button, or "Draft a message →" under a saved contact).

- **Three different drafts per channel** — Email, WhatsApp, Contact form (also professional-network messages), Application cover note,
  Call script — taking a *direct*, *problem-solving* or *experience-led* angle. Pick the contact, the service to lead with, up to 3 projects it may cite,
  the CV to attach/link, and optional style notes, then **Write 3 drafts**. The AI runs through your provider failover.
- **Integrity checks before anything is saved** (a failing reply moves on to the next provider): no links, emails or phone numbers except ones you supplied;
  claims about the company must quote the draft and cite a saved evidence row; "problem-solving" must cite evidence; "experience-led" must name one of your projects;
  length per channel; the three drafts must differ; no placeholders, guarantees or generic openers.
  Each card has "Check the facts used" showing every claim next to its evidence and source link.
- **You stay in control:** edit in place (autosaves; "Reset to original"), swap alternate subjects, pick a signature, **Copy** (message + signature) / **Copy subject**,
  **Select** one, then send it yourself and press **I've sent this**: that logs the send, moves the opportunity to *Contacted* and sets a follow-up reminder (3 days to 2 weeks, or none).
  Sent drafts are frozen. Nothing is ever sent from the app.
- **Safety:** companies marked *do not contact*, and contacts that are bounced/opted out, block drafting; a contact note like "no unsolicited approaches" blocks it unless you tick the override.
- **Setup:** `php artisan migrate` (adds `2026_10_17_000001_add_studio_columns_to_outreach_drafts`). Generation uses the same queue/`RESEARCH_RUN_MODE` as research.
- **Tests:** `tests/Unit/Agent/DraftValidatorTest.php` (run here, passing) and `tests/Feature/Agent/OutreachStudioTest.php` (not run yet — needs `php artisan test` in a real install).

