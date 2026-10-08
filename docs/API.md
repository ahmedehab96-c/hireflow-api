# HireFlow API

Base URL: `{APP_URL}/api` (for example `http://127.0.0.1:8000/api`).

## Conventions

- Send `Accept: application/json`. Authenticated endpoints need `Authorization: Bearer <token>`.
- Success: `{ "success": true, "message": "…", "data": … }`
- Error: `{ "success": false, "message": "…", "errors": { "field": ["…"] } }`
- Paginated lists return `data: { items: [...], meta: { current_page, last_page, per_page, total } }` and accept `page` and `per_page`.
- Status codes:

  | Code | Meaning |
  |---|---|
  | 401 | Not signed in |
  | 403 | Wrong role |
  | 404 | Not found, or not yours |
  | 409 | Wrong state (e.g. an interview already started) |
  | 422 | Validation error |
  | 429 | Rate limited |
  | 502, 503, 504 | AI provider unavailable |

**Auth column:** — public · **User** any signed-in user · **Candidate** candidate role · **Admin** admin role.

---

## Authentication

| Method | URL | Auth | Purpose |
|---|---|---|---|
| POST | `/register` | — | Create a candidate account and return a token |
| POST | `/login` | — | Return a token for valid credentials |
| POST | `/logout` | User | Revoke the current token |
| GET | `/me` | User | The current user |

```json
POST /register
{ "name": "Jane Example", "email": "jane@example.com", "password": "Secret123", "password_confirmation": "Secret123" }

→ 201 { "data": { "user": { "id": 4, "name": "Jane Example", "role": "candidate", … }, "token": "1|…", "token_type": "Bearer" } }
```

```json
POST /login
{ "email": "jane@example.com", "password": "Secret123" }
```

## Jobs

| Method | URL | Auth | Purpose |
|---|---|---|---|
| GET | `/jobs` | — | Active jobs. Filters: `search`, `location`, `employment_type`, `company_id`, `skill` |
| GET | `/jobs/{job}` | — | Active job with its company and skills (404 if not active) |

```
GET /jobs?search=laravel&employment_type=full_time&per_page=10
```

## Applications

| Method | URL | Auth | Purpose |
|---|---|---|---|
| GET | `/applications` | Candidate | Your applications (filter: `status`) |
| POST | `/applications` | Candidate | Track an active job |
| GET | `/applications/{application}` | Candidate | One of your applications |
| PUT | `/applications/{application}` | Candidate | Update status, dates or notes |
| DELETE | `/applications/{application}` | Candidate | Remove it from your tracker |

Statuses: `saved`, `applied`, `interview`, `technical_interview`, `offer`, `rejected`, `hired`.

```json
POST /applications
{ "job_id": 3, "status": "applied", "notes": "Referred by a former colleague" }

PUT /applications/12
{ "status": "technical_interview", "interview_at": "2026-11-02T10:00:00Z" }
```

## CVs

| Method | URL | Auth | Purpose |
|---|---|---|---|
| GET | `/cvs` | Candidate | Your CVs (no analysis body) |
| POST | `/cvs` | Candidate | Upload a PDF (`multipart/form-data`, field `file`, ≤ 5 MB) |
| GET | `/cvs/{cv}` | Candidate | One CV, including its analysis |
| DELETE | `/cvs/{cv}` | Candidate | Delete the CV and its file |

Text is extracted on upload. An unreadable or image-only PDF returns `422` with a `file` error. Responses never include the stored file path or the raw extracted text.

## CV Analysis

| Method | URL | Auth | Purpose |
|---|---|---|---|
| POST | `/cvs/{cv}/analyze` | Candidate | Run the AI review and save the result (rate limited) |

```json
→ 200 { "data": { "id": 7, "score": 74, "is_analyzed": true, "analysis": {
  "score": 74, "summary": "…", "skills": ["PHP", "Laravel"], "experience": [{ "role": "…", "organization": "…", "period": "…", "highlights": "…" }],
  "education": [ … ], "strengths": [ … ], "weaknesses": [ … ], "missing_keywords": [ … ], "recommendations": [ … ] } } }
```

## Job Match

| Method | URL | Auth | Purpose |
|---|---|---|---|
| POST | `/jobs/{job}/match` | Candidate | Compare one of your CVs with an active job (rate limited) |

```json
POST /jobs/1/match
{ "cv_id": 7 }

→ 200 { "data": { "match_score": 82, "summary": "…", "matched_skills": [ … ], "missing_skills": [ … ],
                  "matching_experience": [ … ], "recommendations": [ … ], "job": { "id": 1, … }, "cv": { "id": 7, … } } }
```

## Interviews

| Method | URL | Auth | Purpose |
|---|---|---|---|
| GET | `/interviews` | Candidate | Your interviews (filter: `status`) with progress and score |
| POST | `/interviews` | Candidate | Create a pending interview |
| GET | `/interviews/{interview}` | Candidate | Details: answered questions plus the current one, and the final report when completed |
| POST | `/interviews/{interview}/start` | Candidate | AI writes the questions; status becomes `in_progress` |
| POST | `/interviews/{interview}/questions/{question}/answer` | Candidate | Answer the current question and get an AI score and feedback |
| POST | `/interviews/{interview}/complete` | Candidate | Final AI report and `total_score` (needs at least one answer) |
| DELETE | `/interviews/{interview}` | Candidate | Delete the interview and its answers |

Types: `hr`, `technical`, `behavioral`, `mixed`. At least one of `job_id` or `cv_id` is required.

```json
POST /interviews
{ "job_id": 1, "cv_id": 7, "type": "mixed" }

POST /interviews/5/questions/41/answer
{ "answer": "I would start by checking the slow query log…" }

→ 200 { "data": { "question": { "id": 41, "answer": { "score": 8, "feedback": "…", "strengths": [ … ],
        "weaknesses": [ … ], "improved_answer": "…" } }, "interview": { … } } }
```

## Notifications & Activity

| Method | URL | Auth | Purpose |
|---|---|---|---|
| GET | `/notifications` | User | Your notifications plus `unread_count` (filter: `unread=1`) |
| POST | `/notifications/{id}/read` | User | Mark one as read |
| POST | `/notifications/read-all` | User | Mark all as read |
| GET | `/activity` | Candidate | Your activity timeline, newest first |

Each notification has `kind`, `title`, `message`, `subject` (`{ type, id }` of the related application or interview) and `read_at`.

## Admin

All endpoints require the admin role. Candidates receive `403`.

| Method | URL | Purpose |
|---|---|---|
| GET | `/admin/stats` | Platform counts, applications by status, interviews, a 6-month trend |
| GET | `/admin/users` | Read-only user directory (filters: `search`, `role`) |
| GET, POST | `/admin/companies` | List (filter: `search`) / create |
| GET, PUT, DELETE | `/admin/companies/{company}` | Show / update / delete (409 if it has jobs) |
| GET, POST | `/admin/jobs` | List (any status; filters: `search`, `status`, `company_id`) / create with `skill_ids` |
| GET, PUT, DELETE | `/admin/jobs/{job}` | Show / update / delete (409 if it has applications; close it instead) |
| GET, POST | `/admin/skills` | List (filter: `search`) / create (unique names) |
| GET, PUT, DELETE | `/admin/skills/{skill}` | Show / rename / delete |

```json
POST /admin/jobs
{ "company_id": 1, "title": "Platform Engineer", "description": "…", "employment_type": "full_time",
  "salary_min": 20000, "salary_max": 26000, "currency": "AED", "status": "active", "skill_ids": [3, 7] }
```

Closing a job (`status: "closed"`) notifies candidates whose applications are still in progress.

## Health

`GET /up` returns `200` when the application boots (for load balancers and uptime checks).
