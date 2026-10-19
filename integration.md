# Integration & go-live checklist

Work top to bottom. Tick each box; nothing here is optional unless marked.

## 1. Deploy the code
- [ ] Upload/extract the project over your existing `jobs` app (keep your real `.env` and `storage/`).
- [ ] `composer install --no-dev --optimize-autoloader`
- [ ] `php artisan migrate` (adds, among others: `2026_10_18_000001_add_skill_feed_columns`, `2026_10_18_000002_add_options_to_discovery_runs`). This also turns every account's email alerts **off** until they opt in.
- [ ] `php artisan storage:link` (first install only)
- [ ] Optional: `php artisan db:seed --class=ServiceSeeder` (starter services for the agent).

## 2. `.env` (copy lines from `.env.example`; the key goes right after `=`, nothing after it)
**Must have**
- [ ] `APP_URL`, `APP_KEY` (`php artisan key:generate`), database credentials
- [ ] `QUEUE_CONNECTION=database`, `SESSION_DRIVER=database`, `CACHE_STORE=database`
- [ ] Mail settings (`MAIL_*`) so job alerts and the test email work
- [ ] `AGENT_OWNER_EMAILS=you@example.com` (only these accounts see `/agent`; if empty, the first account ever registered does)

**AI: at least one** (all free tiers, no card needed): `GEMINI_API_KEY`, `GROQ_API_KEY`, `DEEPSEEK_API_KEY`. Keep `AI_PROVIDER=failover` and `RESUME_AI_PROVIDER=failover`.

**Search: at least one.** Start with the ones that need no card:

| Key | Card? | Free allowance |
|---|---|---|
| `SERPER_API_KEY` | No | 2,500 searches once |
| `EXA_API_KEY` | No (per Exa) | $10 bonus + $10/month (~2,500 searches/month) |
| `FIRECRAWL_API_KEY` | No (per Firecrawl) | 1,000 credits/month (~500 searches) |
| `SERPAPI_API_KEY` | Not confirmed | 250 searches/month |
| `JINA_API_KEY` | Not confirmed | Free key, limits not confirmed |
| `SEARXNG_URL` | No | Free, you host it; value is your instance URL, not a key |
| `TAVILY_API_KEY` | Yes | Skip unless you have a card |
| `BRAVE_SEARCH_API_KEY` | Yes | Brave removed its free tier; skip |

Optional extras: `ADZUNA_*` (more job sources), `DEEPL_API_KEY` or `LIBRETRANSLATE_URL` (translation), `RESEARCH_CONTACT_EMAIL` (shown to sites the research crawler visits).

- [ ] After editing `.env` run `php artisan config:clear` (or press **Reload .env keys** on Agent → Providers). cPanel often serves a cached config, which is why a new key can still show "no key".

## 3. Cron (cPanel → Cron Jobs, every minute)
```
* * * * * cd /path/to/app && php artisan schedule:run >> /dev/null 2>&1
* * * * * cd /path/to/app && php artisan queue:work --stop-when-empty --max-time=55 >> /dev/null 2>&1
```
The first drives: `jobs:fetch` hourly, `social:fetch` every 30 min, `jobs:alerts` hourly at :10, `jobs:translate` every 5 min, `jobs:prune` daily, `discovery:run` daily 05:30 only if `DISCOVERY_SCHEDULE=daily`. The second runs research, outreach drafting and discovery. No cron? Set `RESEARCH_RUN_MODE=sync`.

## 4. First-run verification (about 10 minutes)
- [ ] Log in; the sidebar shows Dashboard, Discovery, Opportunities, Services, Projects, CVs, Signatures, Providers + the Job feed group. Try the hamburger on your phone.
- [ ] **Agent → Providers:** each key you added says "key found". Press **Test** on each.
- [ ] **Job interests:** add skills (e.g. `Laravel`), save, then press "Send me a test email".
- [ ] **Jobs:** after `php artisan jobs:fetch` and `php artisan jobs:match --backfill`, matching jobs appear.
- [ ] **Discovery:** pick a service and strategy, run it, open the run and check each candidate cites a quote.
- [ ] `php artisan test` (the feature tests have never been run on a real install; report any failure).

## 5. Troubleshooting
| Symptom | Fix |
|---|---|
| Provider says "no key" though it is in `.env` | `php artisan config:clear` or **Reload .env keys**; check the name spelling and no text after `=` |
| `Route [...] not defined` | `php artisan route:clear`; make sure all files from the latest zip were uploaded |
| Page shows old design/JS | Hard refresh; assets are versioned `?v=2` |
| Research/discovery stays "queued" | The queue cron isn't running, or set `RESEARCH_RUN_MODE=sync` |
| No job emails | Alerts are opt-in on Job interests; check `MAIL_*` and the test email |
| Empty feed | New accounts have no skills; choose some, then run `jobs:match --backfill` |

---

# Reference (carried over from the previous integration guide, updated to the merged app)

Everything below still exists in the merged app unless marked otherwise. Where the old guide described auto-apply, employers, the Tasks page or the webhook, see "Retired" at the end.

## A. Job sources and company boards (no keys)
| Vendor | Endpoint | Auth | Notes |
|---|---|---|---|
| Greenhouse | `GET https://boards-api.greenhouse.io/v1/boards/{board}/jobs?content=true` | none | `{board}` = slug in `boards.greenhouse.io/{board}`. `content` is HTML-entity-encoded; the source decodes it. |
| Lever | `GET https://api.lever.co/v0/postings/{site}?mode=json` | none | Returns a **bare array**, not `{jobs:[]}`. Wrong/unused slug → 404. |
| Ashby | `GET https://api.ashbyhq.com/posting-api/job-board/{board}` | none | Public posting feed. |

Setup: put boards in `.env` (`ATS_BOARDS=greenhouse:stripe,lever:spotify,ashby:linear`) and/or per account in /profile. Find a company's slug from its careers-page URL. Treat these feeds as stable-but-unversioned: a source that starts returning nothing is logged as a warning, not an exception.

Boards also come from each account's **Job interests** page. Aggregators from the earlier overview are not wired in; to add one, implement `JobSourceInterface` and register it in `FetchJobs`.

## B. Skills: standard, atomic, canonical
**Problem:** CV phrasing like `Node.js (NestJS, Express.js)` or `NestJS (GraphQL, microservices)` was stored as one skill string, so matching and tags were wrong.

**Fix (`config/skills.php` + `App\Services\Skills\SkillNormalizer`):**

| Input | Stored as |
|---|---|
| `NestJS (GraphQL, microservices)` | `NestJS`, `GraphQL`, `Microservices` |
| `Node.js (NestJS, Express.js)` | `Node.js`, `NestJS`, `Express.js` |
| `PHP 8 (OOP, MVC)` | `PHP`, `OOP`, `MVC` |
| `Go (production)` | `Go` + qualifier `production` |
| `Python/FastAPI (personal-project level)` | `Python`, `FastAPI` + qualifier |
| `nest js` / `NestJs` / `Nest.js` | `NestJS` |

- **Everything goes through it:** resume import (`ResumeParser` → `Resume::syncSkills`), the seeder, /profile keywords & required skills, and job matching. The AI parse prompt now also demands atomic items.
- **Qualifiers** ("production", "personal-project level") move to `resume_skills.qualifier` — kept, but out of the name.
- **Catalog category wins**, so the PDF's skill groups stay consistent (`backend`, `frontend`, `database`, `apis_integrations`, `applied_ai`, `testing_devops`, `architecture`). Unknown skills are kept as typed — the catalog never drops anything.
- **Matching got safer:** boundary-aware and alias-aware. `Nest.js` in a job ad matches NestJS; `go` doesn't match "google"; `Java` doesn't match "JavaScript"; bare words like "next"/"express" are input-only aliases so "next steps" / "express interest" don't count as Next.js / Express.js.
- **Add a skill:** one line in `config/skills.php`: `'Display Name' => ['category', ['alias', '~input-only-alias']]`.
- **Clean existing data:** `php artisan skills:normalize --dry-run`, then without `--dry-run` (rewrites stored resume skills and every career profile's keywords/required skills).

## C. How matching works now
Matching is decided **at ingest** and stored per account (`job_listing_user_states`: score, tier strong/good/weak, matched skills, title hits, warnings). The feed reads that with SQL, so no per-page scoring. The scoring rules from the earlier audit still apply:

- Best match first (recency breaks ties); `?sort=recent` for newest-first.
- Broad skills (`generic` flag in `config/skills.php`, e.g. Git, REST, SQL) never qualify a job alone.
- Whole-word, alias-aware matching, also for excluded words (`intern` does not drop "international").
- A skill in the **title** scores more than one in the body.
- Region phrases like "Remote, US only" are flagged; optional **Hide region-restricted roles** on Job interests.
- Descriptions are kept to 8,000 characters.
- **New:** only the skills you choose on Job interests count, and saving them re-scores the last 14 days. "Test your rules" on that page scores any pasted job and explains why.
- Limits: keyword-based, not semantic; seniority isn't scored (use excluded words such as `junior`).

## D. Translating non-English jobs
**How it works**
1. **At fetch time** each listing's language is detected offline (stopword scoring, no API call, no quota). Non-English ones are marked `pending`.
2. `jobs:translate` runs **right after every fetch** and **every 5 minutes**, and translates pending listings **once**. The result is stored on the listing (`title_translated`, `description_translated`).
3. In the feed, the **Show translation / Show original** button only swaps text that is already stored. It never calls an API and never waits. If the translation isn't ready yet the card shows "Translation queued…".
4. Matching reads the original **and** the translation. Digest emails show the translated title for translated jobs.

**How the swapping works (already built):** each driver has a locally tracked budget, and the service tries them in the order above. For every piece of text it uses the first driver that is configured, has budget left and succeeds. When a provider reports it's out of quota (DeepL 456, MyMemory 429 or its warning text, Azure quota error) or rate-limits us (429), it is **paused until the day/month resets** (or for a few minutes on a rate limit) and the next listings go straight to the next driver. If a driver fails halfway through a listing, that listing is redone with the next driver, so one description never mixes two engines. If every driver is spent, the listing just stays `pending` and is retried on the next 5-minute run (up to 3 attempts). Check the live state any time with `php artisan jobs:translate --status`.

**Quota protection:** only listings that match at least one account's profile are translated, only the title plus the first 3,500 characters of the description, and every driver has a locally tracked budget.

**Drivers, used in turn as each one runs out** (`TRANSLATE_DRIVERS=deepl,azure,libretranslate,mymemory,ai`):

| Driver | Needs | Free limit | Notes |
|---|---|---|---|
| DeepL | free API key (`DEEPL_API_KEY`, ends `:fx`) | 500K chars/month (budget set to 450K) | Best quality for German/French. First in line. |
| Azure AI Translator | Azure account + Translator resource on the **Free F0** tier (`AZURE_TRANSLATOR_KEY`, `AZURE_TRANSLATOR_REGION`) | 2M chars/month (budget set to 1.8M) | Biggest free quota. Azure sign-up needs a card. Use F0 so nothing is billed. |
| LibreTranslate | `LIBRETRANSLATE_URL` | unlimited when self-hosted | `docker run -p 5000:5000 libretranslate/libretranslate`. The public libretranslate.com instance needs a paid key. |
| MyMemory | nothing (add `MYMEMORY_EMAIL` for more) | 5,000 chars/day anonymous, 50,000/day with an email | 500 bytes per request, so text is chunked. Lower quality, good fallback. |
| ai | your existing Groq/Agnes key | tokens | Last resort, own monthly budget. Remove `ai` from the list to disable. |

**Not included:** Pollinations.ai and Langbly (Google Cloud Translation, which has a 500K free month but needs a billing account, would be one more small driver if you want it). Pollinations is a general generative endpoint with no stated limits or translation guarantees, and I couldn't verify Langbly's API. Both are one small class implementing `TranslationDriver` if you want them.

**Setup:** `php artisan migrate`, set at least `MYMEMORY_EMAIL` (works with zero signup), ideally also `DEEPL_API_KEY` and/or the Azure key, then `php artisan jobs:translate --backfill` to detect and translate the last 7 days of existing listings.

**Limits:** it's machine translation (the card says so). The cover letters/emails are still written in English even for German postings.

## E. Social hiring posts (Hacker News, Mastodon, Bluesky, Lemmy, Reddit, GitHub, X)
Social posts now appear **inside the Jobs feed** (there is no separate `/social` page), filtered by the skills people chose. Per-source setup and costs:

**Pipeline (`social:fetch`, every 30 minutes, followed by `jobs:match`)**
1. **Search terms come from your users:** the core (non-generic) skills in the profiles of accounts that have social jobs on. Nothing unrelated is ever searched for.
2. **Sources** (see the table below for what is free): Hacker News (monthly "Who is hiring?" thread plus the official YC jobs feed), Mastodon hashtag timelines, Bluesky search, and X.
3. **Free filters before any AI is spent:** hiring-intent check (drops "open to work" / "hire me" posts), then a match against at least one account's skills.
4. **AI extraction** (capped per run): is it a real opening, company, location, **apply email, apply link, the poster's instructions, a required subject line, what materials they ask for**.
5. **Everything the AI says is validated against the raw post** before use:
   - an apply email is kept **only if that exact address is written in the post** (so a hallucinated or prompt-injected address is never shown as the contact);
   - an apply link must appear in the post or the platform's link data;
   - the email subject is stripped of line breaks (no header injection) and length-capped;
   - if the post asks for anything beyond a CV, cover letter and links (answers to questions, a video, salary figures), it's flagged "needs your input" (nothing is ever auto-sent);
   - "DM me / reply here" posts are not treated as email applications;
   - the post text is wrapped as untrusted data in the AI prompt.
6. **Then it joins the feed:** each post shows with the verified apply email or link and the poster's instructions. You apply yourself.


### Which sources are free

| Source | Cost | You need | Notes |
|---|---|---|---|
| **Hacker News** ("Who is hiring?" thread + YC jobs feed) | **Free** | nothing | Best for email-apply posts: many posts say "email us". Monthly thread, so volume is bursty at the start of each month. |
| **Mastodon** (public hashtags: #hiring, #fedihire, #remotejobs + one per skill) | **Free** | nothing | Smaller but real; some instances require login to read tag timelines and are skipped. |
| **Bluesky** (search per skill: "hiring laravel") | **Free** | a free Bluesky account + an **app password** (`BLUESKY_HANDLE`, `BLUESKY_APP_PASSWORD`) | Search now requires login, but the account and app password cost nothing. Never use your main password. |
| **X (Twitter)** | **Paid per post read** | `X_BEARER_TOKEN` + credits | No free tier. See below. |

So with no money at all you get Hacker News + Mastodon + Bluesky (default `SOCIAL_SOURCES=hackernews,mastodon,bluesky`; Bluesky switches itself on once the two env vars are set).

**If you want X without paying the API:** there is no legitimate way. Third-party "X data" services and scrapers use unofficial access that breaks X's terms, break often, and can get accounts banned, so I haven't built any. The sensible options are (1) pay-per-use with a tiny budget, e.g. `X_DAILY_READ_BUDGET=50` is about a quarter of a dollar a day at the reported rate, or (2) skip X: most companies that tweet a role also post it on their own Greenhouse/Lever/Ashby board, which you already cover for free.


### Cost and platform reality for X (please read)
- **X is not free.** The API has no free tier now; new developers get pay-per-use (credits). Recent search covers the last 7 days only. Pricing was reported as about $0.005 per post read when I checked (sources differ slightly; your Developer Console shows the real rate). So `social:fetch` throttles X to one run per 4 hours (`X_MIN_INTERVAL_MINUTES`) and stops at `X_DAILY_READ_BUDGET` posts/day (default 150, roughly $0.75/day at that rate). Leave `X_BEARER_TOKEN` empty and X stays off; the feed works from Hacker News, Mastodon, Bluesky and Lemmy.
- **Getting an X token:** developer.x.com, create a project/app, add credits, copy the app's **Bearer Token** into `X_BEARER_TOKEN`, then set `SOCIAL_SOURCES=hackernews,mastodon,x`.
- **Not built, on purpose:** scraping X, LinkedIn or Facebook. Their terms prohibit it, it breaks constantly, and accounts get banned. Adding another official-API source = one class implementing `SocialSource`.
- **Mastodon:** some instances require login to read tag timelines; those are skipped quietly. Add instances in `MASTODON_INSTANCES`.


**Stack Overflow Jobs and GitHub's job board no longer exist**, so nothing reads them. GitHub issues work only for repos you list in `GITHUB_JOB_REPOS`.

| Source | Cost | You need | Notes |
|---|---|---|---|
| **Lemmy** | Free | nothing | Public API on every instance. Searches "hiring {skill}" on `LEMMY_INSTANCES` (default lemmy.world, programming.dev) and optionally reads communities you list in `LEMMY_COMMUNITIES=name@instance`. On by default. |
| **Reddit** | Free for **non-commercial** use | a registered app: `REDDIT_CLIENT_ID`, `REDDIT_CLIENT_SECRET` (+ a proper `REDDIT_USER_AGENT`) | Official API only; unauthenticated traffic is blocked. New apps may need Reddit's approval first (self-service registration was tightened in late 2025, reportedly 2-4 weeks). Reads `REDDIT_SUBREDDITS` (default `forhire`, and only posts tagged [Hiring]). Off until credentials are set. |
| **GitHub issues** | Free | repos you choose | See above. |

**A licensing flag that matters now that this is becoming a job platform:** Reddit's free tier (and the free tiers of several other APIs) is for non-commercial use. If you charge employers or run ads, get Reddit's commercial terms sorted before enabling it, or leave it off. Hacker News, Mastodon, Lemmy and Bluesky have no such restriction that I found, but check each instance's terms if you scale up.

## F. No more repeats (de-duplication)
The same role often appears on a board, then on Greenhouse, then as a tweet, then cross-posted to Mastodon, Bluesky and Reddit. Every path (job boards, ATS feeds, all social sources, employer-posted jobs) now goes through one `JobDeduper`:

| Signal | Catches | Example |
|---|---|---|
| **Apply key** | the very same posting | same Greenhouse/Lever/Ashby job id; same apply link once tracking junk (`utm_*`, `gh_src`...) is stripped |
| **Fingerprint** | same job under different wording | company + title, normalised: "Acme GmbH, Senior Laravel Developer (m/w/d)" equals "ACME, Laravel Developer Senior - Remote" |
| **SimHash of the text** | a post copied to another platform | the tweet and its Mastodon/Bluesky copy (links, hashtags and handles are ignored) |
| **Same apply email + similar title** | "email us" posts reposted | |

- Duplicates are **never deleted**: they're soft-merged (`canonical_id`) into the first copy, hidden everywhere, and the keeper records where else it appeared, shown as **"Also posted on: X, Mastodon, Reddit"** chips on job cards.
- A social post is checked against stored jobs **before any AI is spent**, so cross-posts cost nothing.
- If a board listing turns up for something first seen as a tweet, the board listing's data (real apply link/ATS ids) becomes the main record and the tweet becomes an "also posted on" link.
- Jobs employers post on this site always win: publishing one folds every existing copy under it (`adopt()`), and the employer is told how many were merged.
- `jobs:prune` skips native jobs and takes merged duplicates down with their keeper.
- **Existing data:** `php artisan jobs:dedupe` fingerprints old rows and reports duplicates; add `--merge` to hide them.

Limits: it's heuristic. Two genuinely different openings with the same title at the same company within 45 days (e.g. two "Backend Engineer" roles) would be merged. If that matters to an employer, give the roles distinct titles.

---

# Retired in the merged app
These sections of the old guide describe features that were removed when the interface was unified. The code is gone, so their keys do nothing and can be deleted from `.env`:

| Old feature | Status |
|---|---|
| Auto-apply (`applications:auto-apply`), `ApplyRouter`, Greenhouse/Lever API apply drivers, apply-service driver | Removed. You apply yourself from the job's link or email. |
| `POST /webhooks/apply-service` and `APPLY_SERVICE_*`, `GREENHOUSE_BOARD_KEYS`, `LEVER_SITE_KEYS`, `AUTO_APPLY_*` | Removed. |
| Employer accounts, posting jobs, `/job/{id}` public pages, applicants pipeline | Removed. |
| Applications, Drafts pages and email-apply of social posts ("Send application now", auto-email) | Removed. Outreach now lives in **Outreach Studio** inside the Opportunity Agent, and nothing is ever sent automatically. |
| Tasks/projects board and Leads | Removed. |
| Hourly job-digest email | Replaced by opt-in skill-based **job alerts** (`jobs:alerts`). |
| Old public homepage and profile redesign | Replaced by the Opportunity Agent sidebar look; **Profile** and **Resume** are kept. |
| `HUNTER_API_KEY` | Not used by the merged app. |

Your original zip still contains all of that code if you ever want to bring a piece back.

---

# Portfolio brief API (www.amowogbaje.com → this server)

`POST https://jobs.amowogbaje.com/api/amowogbajewebsite/send-brief` emails the project brief to `hello@amowogbaje.com` (BCC `amowogbajegideon@gmail.com`), Reply-To the visitor. Replies: `200 {"ok":true}` (also for the honeypot `website` field, nothing sent), `422 {"ok":false,"errors":{}}`, `429 {"ok":false}` (over 5 per hour per IP), `500 {"ok":false}` (mail failed; see `storage/logs/laravel.log`).

Files: `routes/api.php`, `app/Http/Controllers/AmowogbajeWebsite/BriefController.php`, `config/cors.php` (only `api/*`; origins www.amowogbaje.com, amowogbaje.com, localhost:5173), `config/brief.php`, rate limiter `brief` in `AppServiceProvider`, `tests/Feature/BriefApiTest.php`.

It sends with the app's existing `MAIL_*` settings, so there is nothing new to configure (optionally `BRIEF_TO` / `BRIEF_BCC` in `.env`). Because it uses the normal mailer, the email is sent from `MAIL_FROM_ADDRESS`; set that to an address your SMTP account is allowed to send as.

Setup:
1. Upload, then `php artisan config:clear` and `php artisan route:clear`.
2. Check `php artisan route:list | grep send-brief`, then run the preflight and submit `curl` tests from your brief.
3. Submit one real brief from the live site, and only then delete the old `public/api/brief.php` on the portfolio.

Not run here: the feature tests and a real send (no Laravel install in my sandbox). A browser CORS error usually means a cached config (`config:clear`).
