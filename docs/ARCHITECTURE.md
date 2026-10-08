# HireFlow Architecture

## System architecture

```
┌──────────────────────┐   JSON over HTTPS    ┌─────────────────────────────────────────────┐
│  React SPA           │  Authorization:      │  Laravel API                                │
│  (hireflow-web)      │  Bearer <token>      │                                             │
│  React Router, Axios ├─────────────────────▶│  Middleware: JSON · security headers · CORS │
│  Tailwind UI         │                      │  throttle · auth:sanctum · role             │
└──────────────────────┘                      │        │                                    │
                                              │  Controllers ─▶ Form Requests (validation)  │
                                              │        │      ─▶ Policies (ownership)       │
                                              │        ▼                                    │
                                              │  Services: CareerAIService · CvUploader ·   │
                                              │            PdfTextExtractor · ActivityLogger│
                                              │        │                                    │
                                              │  Eloquent models ─▶ API Resources (output)  │
                                              └───┬───────────────┬───────────────┬─────────┘
                                                  ▼               ▼               ▼
                                               MySQL       Private storage   AI provider
                                                           (CV PDFs)         (via AiProvider)
```

- The API is stateless: every request carries a Sanctum personal access token.
- Every response uses one envelope: `{ success, message, data }`, or `{ success: false, message, errors }` for errors.
- Errors never include stack traces or internal messages, whatever `APP_DEBUG` is set to.

## Authentication flow

1. `POST /api/register` or `POST /api/login` returns `{ user, token }`. Public sign-up always creates a **candidate**; admins are provisioned separately.
2. The SPA keeps the token in `localStorage` behind one module (`services/api.js`), which attaches `Authorization: Bearer …` to every request.
3. Tokens expire after 7 days (`SANCTUM_EXPIRATION`). `POST /api/logout` revokes the current token.
4. Any `401` clears the token and sends the user to the login page. Login and register are limited to 10 requests per minute.

## API flow

```
Request ─▶ ForceJson ─▶ SecurityHeaders ─▶ throttle ─▶ auth:sanctum ─▶ role:<candidate|admin>
        ─▶ route-model binding ─▶ Form Request ─▶ Policy (Gate::authorize) ─▶ Controller/Service
        ─▶ API Resource ─▶ { success, message, data }
```

The role check runs **before** route-model binding. A user with the wrong role gets `403` and never learns whether a record id exists.

## AI flow

```
Controller ─▶ CareerAIService ─▶ AiProviderFactory ─▶ AiProvider::generateJson(system, prompt, schema)
                 │  prompts, JSON Schemas,                 │  AnthropicProvider (official SDK):
                 │  output normalisation                   │  structured outputs, timeouts, retries,
                 ▼                                         ▼  refusal/error mapping
           validated arrays  ◀───────────── JSON ──────────┘
```

- **Isolation**: controllers never call the AI directly. To change provider, implement `AiProvider` and register it in `AiProviderFactory`.
- **Structured output**: each request sends a strict JSON Schema. Responses are checked (scores clamped, lists trimmed and de-duplicated) before they are saved.
- **Safety**: CVs, job postings and answers are wrapped in tags and treated as untrusted data in prompts. Interview prompts forbid questions about protected characteristics.
- **Failure handling**: a missing key returns `503` ("not configured"); rate limits return `503`; provider errors return `502`. A failed AI call happens before any write, so nothing is half-saved and the user can retry.
- **Cost control**: per-user limits on AI endpoints (`ai`: 5/min, 50/day; `ai-interview`: 20/min, 200/day).

## CV analysis flow

1. `POST /api/cvs` checks the file (PDF by extension **and** content, ≤ 5 MB, ≤ 10 CVs per user) and stores it at `storage/app/private/cvs/{user}`.
2. `PdfTextExtractor` extracts the text straight away. If the PDF is unreadable, scanned or too long, the API returns `422` and the file is deleted.
3. `POST /api/cvs/{cv}/analyze` sends the text to `CareerAIService::analyzeCv()`. The structured result is saved to `cvs.analysis_json` and `cvs.score`.
4. API responses include the analysis, but never the storage path or the raw extracted text.

## Job matching flow

1. `POST /api/jobs/{job}/match` with `{ cv_id }`. The CV must belong to the caller (checked in the Form Request) and the job must be active.
2. `CareerAIService::matchCvToJob()` builds the prompt from the job (title, description, required skills) and the CV, plus the skills already found by an earlier analysis.
3. It returns the match score, matched and missing skills, relevant experience and recommendations. The match itself isn't stored; an activity entry records that it happened.

## Interview flow

```
POST /interviews (pending) ─▶ POST /start ─▶ AI writes the questions ─▶ in_progress
   └─▶ POST /questions/{q}/answer (repeat; must be the current question) ─▶ AI scores it 0–10 with feedback
   └─▶ POST /complete ─▶ AI final report ─▶ completed, total_score /100
```

- Mixed interviews have 3 HR, 3 technical and 2 behavioral questions; the other types have 8 of their category.
- Only answered questions and the current one are returned. Later questions stay hidden until reached.
- `total_score` is computed by the server: the average answer score × 10, with unanswered questions counted as 0. The AI supplies the technical, communication and relevance ratings and the written feedback.
- Completing an interview sends a notification and adds an activity entry.

## Authorization model

| Area | Rule |
|---|---|
| Public | `GET /jobs`, `GET /jobs/{job}` (active jobs only) |
| Candidate | Own applications, CVs, interviews, activity. Policies return **404** for other users' records, so ids can't be probed |
| Admin | `/api/admin/*`: companies, jobs, skills (create/edit/delete), stats, a read-only user list |
| Any signed-in user | Own notifications only (queried through `$user->notifications()`) |

Further safeguards:
- **Role and ownership columns:** `role` and `user_id` are never mass-assignable; ownership always comes from the authenticated user.
- **Scoped validation:** `cv_id`, `job_id` and interview questions are checked against the current user and interview (`exists` rules and scoped bindings).
- **Admin user list:** shows names, emails, roles and counts only, never CV text, notes or answers.
- **Uploaded files:** CVs are stored on a private disk with no public or signed URL.

## Database relationship overview

```
users ─┬─< applications >── jobs >── companies
       │                     │
       │                     └─< job_skill >── skills
       ├─< cvs
       ├─< interviews ──< interview_questions ──1 interview_answers
       │      ├── job (nullable)
       │      └── cv (nullable)
       ├─< activities (subject: application | cv | job | interview)
       └─< notifications (Laravel database notifications)
```

- `applications`: unique `(user_id, job_id)`. A job with applications can't be deleted (close it instead).
- `interview_answers`: one per question (unique `interview_question_id`).
- Deleting a user cascades to their applications, CVs, interviews and activity. Deleting a job or CV keeps past interviews (`nullOnDelete`).
