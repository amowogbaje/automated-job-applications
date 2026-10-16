# Opportunity Agent (Laravel + Tailwind CDN + Alpine CDN)

Drop these files into a fresh Laravel install (same folder structure, merge/overwrite), then:

1. `.env`: set DB_*, plus
   ADMIN_EMAIL=you@example.com
   ADMIN_PASSWORD=choose-a-strong-one
   ADMIN_NAME=Florence
   QUEUE_CONNECTION=database
   SESSION_DRIVER=database
2. `php artisan migrate`   (Laravel's default users/cache/jobs migrations + the 4 here)
3. `php artisan db:seed`   (creates your login, 6 service categories, score weights)
4. Remove any default `/` route in routes/web.php (this file replaces it). There is no registration route by design.

cPanel: point the domain's document root at `public/`; cron `* * * * * php /home/USER/path/artisan schedule:run`.
CV uploads are stored on the private `local` disk and served only through the authenticated download route (no storage:link needed).

## Phase 3 additions (opportunity tracker)
- New file `config/opportunity.php` (types, statuses, channels, scoring criteria). Copy it in; no extra setup.
- Pages: /opportunities (filters + search), create/edit, detail (evidence trail, contacts, assessment/scoring, follow-ups, outreach log, activity).
- Priority thresholds (A >= 75, B >= 55) and score weights are read from the `settings` table, seeded by ServiceSeeder.
- Nothing is sent from the app. The outreach log is a manual record of what you did.

## Phase 4 – Research agent, AI & search failover

**.env keys** (add only the ones you have; at least one AI key is required, search is optional):

```
GEMINI_API_KEY=      GROQ_API_KEY=      DEEPSEEK_API_KEY=
TAVILY_API_KEY=      SERPER_API_KEY=    BRAVE_SEARCH_API_KEY=
# optional model overrides: GEMINI_MODEL, GROQ_MODEL, DEEPSEEK_MODEL
RESEARCH_RUN_MODE=queue        # or "sync" if you can't run a cron worker
RESEARCH_CONTACT_EMAIL=you@example.com   # put in the crawler's User-Agent
```

**Setup:** `php artisan migrate` (includes `000005_create_provider_calls_and_provenance`), then cron
`* * * * * php /path/to/artisan queue:work --stop-when-empty --max-time=55`.

**Providers page** (`/settings/providers`): enable/disable, reorder priority, edit models, set daily limits, test a key, clear a cooldown.
When a provider is rate-limited or errors, the next one is used automatically and the failing one rests for a cooldown.
Models are editable because names change; confirm them against each provider's docs.

**Tests:** `tests/Unit` (pure classes) and `tests/Feature` (use `Http::fake`) – run `php artisan test`. Feature tests have not been run yet.
