---
title: "Security"
---

## Overview

Task Fiend is designed for use by a single household or small trusted group. All data is private by default — tasks and projects are only visible to their creator and explicitly assigned members.

---

## Security audit (June 2026)

A full security audit was completed covering IDOR/authorization, file upload safety, XSS, input validation/DoS, mass assignment, API security, CSRF, security headers, and SQL injection.

| Category | Finding | Status |
|---|---|---|
| IDOR | `ProjectController::showBackground()` served background images without checking project access | Fixed |
| XSS | Tag names inserted into `innerHTML` via JS template literal in task-list | Fixed |
| File upload | `ProfileController` called `imagecreatefromstring()` without checking return value (PHP 8 fatal) | Fixed |
| File upload | `getClientOriginalName()` stored without `basename()` — path traversal sequences in filenames | Fixed |
| Mass assignment | `task_id` in `$fillable` for `Comment` and `TaskAttachment` — switched to relationship `create()` | Fixed |
| Input validation | No server-side limits on bulk task input, descriptions, or comments | Fixed |
| APP_DEBUG | `.env.example` defaulted to `APP_DEBUG=true` | Fixed |
| Security headers | No `X-Frame-Options`, `X-Content-Type-Options`, `Referrer-Policy`, or `Permissions-Policy` | Fixed |
| API rate limiting | None | Already present (`throttle:30,1`) |
| CSRF | All state-changing web routes protected | No issue found |
| SQL injection | No raw query interpolation | No issue found |

### Deferred: Content Security Policy

A strict CSP would require `nonce` attributes on inline `<script>` blocks across 16+ view files. This is a meaningful refactor and was deferred. When implemented, every `<script>` tag in Blade templates will need a server-generated nonce, and the CSP header should use `script-src 'self' 'nonce-{value}'`.

---

## Authentication and authorization

- All web routes require an authenticated session (`auth` middleware)
- All API routes require a bearer token matched against hashed keys in `api_keys` (`auth.api` middleware)
- Tasks are private to their creator and assignees; no other user can view, edit, or comment
- Projects are private to their creator and members (direct assignees or users with an assigned task)
- Tags are global — all authenticated users can create and manage tags

---

## File uploads

- Uploaded files are stored on the `private` disk and are never directly URL-accessible
- MIME types are validated by content inspection (`mimetypes:` rule), not by extension
- Accepted types: images (JPEG, PNG, WebP, GIF, HEIC), PDF, Word, Excel, PowerPoint, LibreOffice, CSV, TXT, JSON
- File size limit is configurable via `MAX_FILE_SIZE` in `.env` (default: 22 MB)
- Filenames are sanitized with `basename()` before storage
- Images are scaled down on upload (configurable via `SCALE_LARGEST_TO`)

---

## Input limits

All configurable via `.env`:

| Variable | Default | Controls |
|---|---|---|
| `BULK_INPUT_MAX_CHARS` | 10,000 | Total characters in task bulk-add field |
| `BULK_INPUT_MAX_LINES` | 100 | Maximum lines (tasks) per bulk submission |
| `LONG_TEXT_MAX_CHARS` | 10,000 | Task/project descriptions and comments |

---

## API keys

- Keys are generated with a `tfk_` prefix followed by random bytes
- Only the hash (`bcrypt`) is stored; the plaintext key is shown once at generation
- Keys can be invalidated via `php artisan apikey:invalidate {key}`
- API endpoints are rate-limited to 30 requests per minute per key

---

## HTTP security headers

Applied to every response via `SecurityHeaders` middleware:

| Header | Value |
|---|---|
| `X-Frame-Options` | `SAMEORIGIN` |
| `X-Content-Type-Options` | `nosniff` |
| `Referrer-Policy` | `strict-origin-when-cross-origin` |
| `Permissions-Policy` | `camera=(), microphone=(), geolocation=()` |

---

## Deployment checklist

Before running in production:

- [ ] Set `APP_DEBUG=false` in `.env`
- [ ] Set `APP_ENV=production` in `.env`
- [ ] Confirm `APP_KEY` is set (run `php artisan key:generate` if blank)
- [ ] Confirm the `private` storage disk is not web-accessible
- [ ] Confirm `MAX_FILE_SIZE` and PHP's `upload_max_filesize` / `post_max_size` are aligned
- [ ] Review `BULK_INPUT_MAX_CHARS`, `BULK_INPUT_MAX_LINES`, and `LONG_TEXT_MAX_CHARS` for your use case
