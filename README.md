# HireFlow

**AI-Powered Career Management Platform** — find the right job, prepare smarter, get hired.

HireFlow gives job seekers one workspace for the whole search: a job board, an application tracker, an AI CV analyzer, CV-to-job matching and an AI interview coach. Admins manage companies, jobs and skills and see platform analytics.

The project is split into two repositories:

| Repository | Purpose |
|---|---|
| `hireflow-api` (this repo) | Laravel REST API, database, AI integration, tests |
| `hireflow-web` | React single-page app (see its own README) |

## Features

- **Authentication**: register, log in and log out with Sanctum bearer tokens; candidate and admin roles.
- **Job management**: a public job board with search and filters, plus admin create/edit/delete for companies, jobs and skills.
- **Application tracking**: candidates track roles through 7 stages (saved → applied → interview → technical interview → offer → rejected / hired), with dates and private notes.
- **CV analyzer**: upload a PDF; the API extracts the text and returns an AI review with a score out of 100, skills, experience, strengths, weaknesses, missing keywords and recommendations.
- **AI job matching**: compares one of your CVs with a job's description and required skills to give a match score, matched and missing skills, and next steps.
- **AI interview coach**: HR, technical, behavioral or mixed mock interviews. You get 8 role-specific questions and a score out of 10 with feedback on every answer. A final report scores you out of 100 and breaks it down into technical, communication and relevance.
- **Admin dashboard**: platform analytics (users, jobs, the application pipeline, interviews, a 6-month trend) and a read-only user directory.
- **Notifications and activity**: in-app notifications (application created, status changed, interview feedback ready, job closed) and a personal activity timeline.

## Architecture

```
React SPA (hireflow-web)
        │  HTTPS + JSON, Bearer token
        ▼
Laravel REST API (hireflow-api) ── Sanctum auth · policies · rate limits
        │
        ├──▶ MySQL (users, jobs, applications, CVs, interviews, notifications, activity)
        ├──▶ Private file storage (uploaded CV PDFs, never served over HTTP)
        └──▶ AI Provider (via AiProvider interface → structured JSON)
```

Details: [docs/ARCHITECTURE.md](docs/ARCHITECTURE.md) · Endpoints: [docs/API.md](docs/API.md)

## Tech Stack

**Frontend**: React 19, Vite 8, Tailwind CSS 4, Axios, React Router 7, Lucide icons

**Backend**: Laravel 13, PHP 8.5, MySQL 8+, Laravel Sanctum, `smalot/pdfparser` (PDF text extraction)

**AI**
- A provider abstraction (`App\Services\AI\Contracts\AiProvider`) with an Anthropic implementation built on the official PHP SDK.
- All prompts and output clean-up live in one service (`CareerAIService`).
- Responses are requested as **structured JSON** (JSON Schema), then validated and normalised before anything is saved.

## Project Structure

```
hireflow-api/
├── app/
│   ├── Enums/                 Roles and statuses (UserRole, ApplicationStatus, InterviewType, …)
│   ├── Http/
│   │   ├── Controllers/Api/   REST controllers (Admin/ for admin-only endpoints)
│   │   ├── Middleware/        JSON forcing, role checks, security headers
│   │   ├── Requests/          Form Request validation
│   │   └── Resources/         API response shapes (no internal fields)
│   ├── Models/                Eloquent models and relationships
│   ├── Notifications/         In-app (database) notifications
│   ├── Policies/              Ownership rules (Application, Cv, Interview)
│   └── Services/
│       ├── AI/                CareerAIService, provider interface, Anthropic provider
│       ├── Cv/                PDF text extraction and upload handling
│       └── ActivityLogger.php
├── config/ai.php, cv.php      AI and CV upload settings
├── database/                  Migrations, factories, demo seeders
├── docs/                      ARCHITECTURE.md, API.md
├── routes/api.php             All API routes
└── tests/                     Feature and unit tests (AI is always faked)
```

## Local Setup

Requirements: PHP 8.5+, Composer, MySQL 8+, Node 20+.

### Backend

```bash
cd hireflow-api
composer install
cp .env.example .env
php artisan key:generate
mysql -u root -e "CREATE DATABASE hireflow CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci"
php artisan migrate --seed      # schema + demo data
php artisan serve               # http://127.0.0.1:8000
```

### Frontend

```bash
cd hireflow-web
npm install
cp .env.example .env            # set VITE_API_URL=http://127.0.0.1:8000/api
npm run dev                     # http://localhost:5173
```

## Environment Variables

Backend (`hireflow-api/.env`). Secrets stay out of version control; `.env` is git-ignored.

| Variable | Purpose |
|---|---|
| `APP_ENV`, `APP_DEBUG`, `APP_URL`, `APP_KEY` | Standard Laravel settings. Production: `APP_ENV=production`, `APP_DEBUG=false` |
| `DB_*` | MySQL connection |
| `CORS_ALLOWED_ORIGINS` | Comma-separated frontend origins allowed to call the API |
| `SANCTUM_EXPIRATION` | Token lifetime in minutes (default 7 days) |
| `AI_PROVIDER` | `anthropic` (the provider layer is swappable) |
| `AI_API_KEY` | Provider API key. Empty = AI features disabled (endpoints return 503 with a clear message) |
| `AI_MODEL`, `AI_EFFORT` | Model ID (default `claude-opus-5-5`) and reasoning effort (`low` … `max`) |
| `AI_TIMEOUT`, `AI_MAX_RETRIES`, `AI_FALLBACKS` | Optional request tuning |
| `CV_MAX_FILE_KB`, `CV_MAX_PER_USER` | Optional CV upload limits (defaults: 5 MB, 10 CVs) |
| `SEED_DEMO_DATA` | Only relevant in production: demo data is never seeded unless this is `true` |

Frontend (`hireflow-web/.env`):

| Variable | Purpose |
|---|---|
| `VITE_API_URL` | API base URL including `/api`. If empty, the app calls `/api` on its own origin |
| `VITE_DEMO_MODE` | `true` shows one-click demo sign-in on the login page in a production build (always shown in `npm run dev`) |

## Testing

```bash
cd hireflow-api
php artisan test        # 89 feature + unit tests; AI providers are faked, no network calls
vendor/bin/pint --test  # code style

cd ../hireflow-web
npm run build           # production build
npm run lint            # oxlint
```

## Demo Accounts (local / demo data only)

`php artisan db:seed` creates fictional demo data: 5 companies, 15 jobs, skills and sample applications, plus these accounts.

| Role | Email | Password |
|---|---|---|
| Candidate | `candidate1@hireflow.test` | `Password123` |
| Candidate | `candidate2@hireflow.test` | `Password123` |
| Admin | `admin@hireflow.test` | `Password123` |

These accounts exist only in seeded local/demo databases. The seeder refuses to run in production unless `SEED_DEMO_DATA=true` is set for an intentional public demo.

## Production Notes

- Set `APP_ENV=production`, `APP_DEBUG=false`, a real `APP_URL` and `CORS_ALLOWED_ORIGINS`. Then run `php artisan config:cache route:cache`.
- Uploaded CVs are stored on the private `local` disk (`storage/app/private`) and never served publicly. Back that directory up, or switch the disk to private object storage.
- Notifications are sent synchronously to the database, so no queue worker is required. Cache and sessions use the database driver by default.
- Rate limits: 300 requests/min per user (or IP), 10/min on login and register, and separate per-user daily caps for AI endpoints.
