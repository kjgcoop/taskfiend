---
paths:
  - "app/Models/Tag.php"
  - "app/Http/Controllers/TagController.php"
  - "app/Http/Controllers/SearchController.php"
  - "app/Http/Controllers/TaskController.php"
  - "app/Services/QuickAddParser.php"
  - "resources/views/search/**"
  - "resources/views/tags/**"
---

# Tags, Search, and Quick-Add Tokens

## Tag Archiving

`tags.archived_at` (nullable; null = active). `Tag::scopeActive()` / `scopeArchived()` partition on it. Routes `POST /tags/{tag}/archive` and `/unarchive` (change-logged); the actions live in the tag Details modal, away from Delete so they can't be misclicked.

- **Pickers and parsers use `Tag::active()`**: task create/edit/show/panel forms, search filters, the nav list, quick-add `@tag` autocomplete data, and `QuickAddParser::parseTagTokens()` (an archived tag's `@name` stays as literal title text). `ChangeLogController`'s tag filter is deliberately unfiltered (history view).
- **Display** uses `Task::visibleTags()` (a plain method filtering the loaded `tags` collection), never `$task->tags`, anywhere chips render.
- **Do not filter `$task->tags` where it builds hidden `tag_ids[]` inputs** (quick-complete, agenda complete, review rows, subtask quick-complete); that would silently detach archived tags.
- **Data-loss guard**: use `TaskController::syncTagsPreservingArchived()` at every *existing-task* tag sync, because pickers only render active tags and a plain `sync()` would strip archived ones. Plain `sync()` is fine at new-task sites (`store()`, API `create()`, bulk add, `Task::duplicate()`, which copies the full tag set).
- The tag's own show page still lists all its tasks, scoped to `visibleTo(Auth::id())`.
- `Tag::$fillable` must include `archived_at` (it's never in a request-validated array, so users can't smuggle it in).
- The tag count excludes archived/done tasks. Tag lists use `Str::plural('task', $count)`.

## Quick-Add Tokens

`QuickAddParser` returns a `QuickAddTokens` DTO. Matching is hyphen-insensitive, limited to **active** projects/tags, and unmatched tokens stay in the title. Location fuzzy-match is scoped to the user's *visible* tasks. Matched `&user` tokens are stripped by both preview and store using the typed token, not a re-derived slug.

## Search

- The search page's `#project`/`@tag` tokens go through the same server-side parser (`SearchController::parseTokens`, `/search/parse-tokens`). The autocomplete dropdown stays client-side, and a clicked suggestion passes its known id straight through. `#inbox` is a search-only sentinel stripped before the parser runs.
- "Search in" checkboxes: Title and Description (default on), Comments (`search_comments`, default off). Matching is an OR across the checked fields, built **inside** the closure already scoped by `Task::visibleTo(Auth::id())`, so a comment match can never reveal an invisible task.
- With search text and no box checked, `SearchController::searchScopeErrorMessage()` returns a validation error (no silent fallback). It is shared by `index()` (200 with a banner and flashed `ViewErrorBag`) and `more()` (422 JSON). The header quick search sends `search_title=1&search_description=1` explicitly.
- Markdown export is handled inside `index()` and honors the same options.

## Project Pickers

Every place a task can be pointed at a project (`store()`, `update()`, `updateField()` project branch, `bulkUpdate()` move, API `create()`, tag show page) goes through `Project::forMember()` / `activeForUser()` and rejects `done`/`archived` targets. Use `activeForUser()`, not a raw `status != archived` filter.
