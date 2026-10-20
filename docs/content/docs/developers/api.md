---
title: "API Reference"
---

All endpoints require a bearer token from `php artisan apikey:create`.

```
Authorization: Bearer tfk_xxxxxxxxxxxxxxxxxxxxxxxx
```

Rate limit: 30 requests per minute per key.

---

## POST /api/tasks — Create a task

Natural language is parsed from the `name` field if `date` or `recurrence_pattern` are not explicitly provided (same parser as the web quick-add bar).

**Request body (JSON):**

```json
{
  "name": "Team sync every Tuesday",
  "description": "Optional markdown description",
  "date": "2026-02-24",
  "time": "14:30",
  "project_id": 1,
  "recurrence_pattern": "Tuesday",
  "recurrence_floating": false,
  "tag_ids": [1, 2],
  "assignee_ids": [1]
}
```

All fields except `name` are optional. The key owner is always assigned: if `assignee_ids` is omitted the task is assigned to the key owner, and if it lists other people the key owner is added to them (a task's creator is always one of its assignees, same as in the web UI). If `project_id` is omitted, the task goes into the key owner's inbox. `project_id` is rejected with a 422 if it's a project the key owner isn't a member of, or a project that's `done`/`archived` — same rule the web UI's project pickers enforce.

**Response (201):**

```json
{
  "success": true,
  "task": {
    "id": 42,
    "name": "Team sync",
    "date": "2026-02-24",
    "time": "14:30",
    "status": "incomplete",
    "recurrence_pattern": "Tuesday",
    "recurrence_floating": false,
    "creator": { "id": 1, "name": "..." },
    "project": { "id": 1, "name": "..." },
    "tags": [...],
    "assignees": [...]
  }
}
```

**Error response (422)** — returned when an unrecognized recurrence keyword is detected in the name:

```json
{
  "success": false,
  "message": "The recurrence pattern in '...' was not recognized. ..."
}
```

---

## GET /api/tasks/on/{date} — Tasks due on a date

Returns all non-archived tasks dated `YYYY-MM-DD` that belong on the key owner's day: tasks created by or assigned to them, plus dated tasks from projects they belong to if they've turned on "Also show dated tasks from projects I'm assigned to" under Day Preferences on their profile. Also returns any not-dismissed project reminders due on or before that date for projects the key owner owns.

```
GET /api/tasks/on/2026-02-24
```

**Response (200):**

```json
{
  "success": true,
  "date": "2026-02-24",
  "tasks": [...],
  "project_reminders": [...]
}
```

`project_reminders` includes reminders on incomplete projects owned by the key owner where `dismissed` is `false` and `date <= the requested date`.

---

## GET /api/tasks/completed/{date} — Tasks completed on a date

Returns tasks with `status = done` whose `updated_at` falls on `YYYY-MM-DD`.

```
GET /api/tasks/completed/2026-02-24
```

Response shape is the same as `/api/tasks/on/{date}`, and the same Day Preferences setting decides whether project tasks that aren't assigned to the key owner are included.
