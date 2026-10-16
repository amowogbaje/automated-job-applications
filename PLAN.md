# Opportunity Agent — Build Plan

A personal business-development platform: discover businesses with a plausible need for my services, research them with an evidence trail, match to the strongest service, find the best public contact channel, draft outreach, recommend CV/portfolio, and track follow-ups. **I review and send everything manually. Nothing is auto-sent.**

## 1. Stack and constraints

| Concern | Decision |
|---|---|
| Backend | Laravel (current stable), PHP 8.2+ |
| Frontend | Blade + Tailwind via CDN + Alpine.js via CDN. **No Mix, no Vite, no npm build.** |
| Hosting | cPanel shared hosting (Namecheap) |
| DB | MySQL/MariaDB (cPanel default) |
| Queue | `database` driver; worker run by cPanel **cron** (`queue:work --stop-when-empty` every minute) |
| Scheduler | One cron line: `* * * * * php artisan schedule:run` |
| Deploy | GitHub Actions: rsync/FTP of built repo incl. `vendor/` (install composer on the runner, not on cPanel) |
| AI | Provider-agnostic `AiClient` service (Anthropic/OpenAI via HTTP) |
| Search | Tavily via HTTP (`Http::` facade), behind a `SearchProvider` interface |
| Auth | Single-user (me). Laravel session auth, registration disabled |

cPanel implications: no long-running daemons, so agent work is chunked into short queue jobs (each job does one step and dispatches the next); respect PHP `max_execution_time`; document root points to `public/`.

## 2. The eight components → modules

1. **Service Intelligence** — `services` (editable profiles), keywords, signals, linked projects/CVs.
2. **Discovery Engine** — strategies A–E implemented as `DiscoveryStrategy` classes; each produces `discovery_runs` and candidate `opportunities`.
3. **Business Research Agent** — tool-using loop (SearchWeb, InspectWebsite, FindContacts, etc.) logging every step in `agent_runs`/`agent_steps`; findings saved to `evidence`.
4. **Matching & Scoring** — six-criterion weighted score; component scores stored in `opportunity_scores`; priority A/B/C/rejected.
5. **Contact Intelligence** — `contacts` with source URL, verified date, public-business flag, restrictions. Never guess emails, never treat a personal number as WhatsApp.
6. **Outreach Studio** — 3 distinct drafts per channel in `outreach_drafts`; email / WhatsApp / contact form / application / call script.
7. **Portfolio & CV Recommender** — `portfolio_projects`, `cv_versions`, pivot to services and opportunities with a reason.
8. **Review & Tracking Dashboard** — filters, pipeline stages, follow-up tasks, activity log.

## 3. Pipeline (state machine on `opportunities.status`)

`discovered → researching → qualified | rejected → drafted → approved → contacted → replied → won | lost | archived`

Each transition is a queue job; failures are recorded and retryable.

## 4. Integrity rules (enforced in schema and prompts)

- Every claim in a draft must trace to an `evidence` row. Evidence stores: what was found, source URL, retrieved_at, what it **establishes** (fact), what the AI **inferred**, confidence.
- Fact vs inference are separate columns.
- No invented relationships or invented problems.
- Contacts must have a source URL; email only if publicly published.
- Dedupe via normalized domain (`companies.domain` unique).
- Outreach is copy-ready only; sending is manual. Messaging-law notes stored per contact/country (`restrictions`).

## 5. Scoring (starting weights, editable in `settings`)

Need evidence 25 · Skills match 25 · Active signal 15 · Commercial value 15 · Contact channel 10 · Portfolio strength 10. Stored per component, total derived. Not a validated conversion predictor.

## 6. Schema overview

Core: `companies`, `opportunities`, `opportunity_services` (match + strength), `opportunity_scores`, `evidence`.
Contact/outreach: `contacts`, `outreach_drafts`, `outreach_events`.
Profile: `services`, `portfolio_projects`, `cv_versions`, `service_portfolio_project`, `service_cv_version`, `opportunity_portfolio_project`, `signatures`.
Operations: `discovery_runs`, `agent_runs`, `agent_steps`, `follow_ups`, `activity_logs`, `settings`, plus Laravel's `users`, `jobs`, `failed_jobs`, `cache`.

## 7. Build phases

1. **Foundation** — migrations, models, seeder, auth (single user), layout (Tailwind/Alpine CDN).
2. **Service profiles CRUD** — Alpine-driven editor for services, projects, CVs, signature.
3. **Manual opportunity entry + dashboard** — list/filter/detail, so the tracker is useful before any AI exists.
4. **Search + research agent** — Tavily provider, website inspector, evidence recording, queue jobs.
5. **Matching & scoring** — AI match, weights from settings, priority assignment.
6. **Contact intelligence** — extraction + routing rules.
7. **Outreach Studio** — three-draft generation per channel, compare/select/edit/copy.
8. **Follow-ups & reporting** — reminders, funnel stats, cron setup docs.
9. **Hardening** — rate limits per provider, cost caps per run, audit log review.

## 8. Decisions needed from me later (not blocking schema)

- AI provider and monthly cost cap.
- Preferred signature and which name/portfolio URL to use.
- Target countries and minimum budget per service.
