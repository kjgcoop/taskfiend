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

`QuickAddParser` returns a `QuickAddTokens` DTO. Matching is hyphen-insensitive, limited to **active** projects/tags, and unmatched tokens stay in the title. Location fuzzy-match is scoped to the user's *visible* tasks (`Task::visibleTo`). Matched `&user` tokens are stripped by both preview and store using the typed token, not a re-derived slug.

**Typed `#project` beats the posted `project_id`.** The quick-add bar posts a hidden `project_id` for the page it's on (a project page), and autocomplete adds its own. That value is only the default: a `#token` that matches an active project overrides it (typing `#project-b` on Project A's page files the task in B), and no token means the posted project. There is no "pre-resolved" shortcut: the lookup always runs, in single store, bulk store, preview and search alike. Besides the SQL name match, `QuickAddParser::findProject()` accepts the autocomplete's JS slug rule (`slugify()` in `layouts/app.blade.php`), so a name like `R&D` (autocompleted as `#rd`) is still recognized; keep the two in sync. If a line has several `#words`, the first one that matches a project is the project token and the rest stay in the title.

**Unmatched tokens are literal text, everywhere.** `"Here is a thing @notatag #project"` saves to Project as `"Here is a thing @notatag"`: only tokens that matched something are removed. A `#word` that matches no project (a hashtag, `"Tweet #laravel"`) stays in the title too, with or without a posted `project_id`. This has regressed more than once, so keep it true on every path:

- Quick-add (single and bulk) goes through `QuickAddParser`; don't hand-roll token stripping.
- The task side panel's inline name edit (`_saveFieldRequest()` in `layouts/app.blade.php`) strips only the tokens the user inserted via autocomplete (tracked in `nameTokens`), never a blanket `/[@#][\w-]+/` regex.
- Tests: `TaskStoreTest::test_unrecognized_at_token_*`, `TaskStoreTest::test_quick_add_*hash_token*` (override, no-token default, unmatched hashtag), `QuickAddParserTest::test_unmatched_tag_token_stays_in_name` and `test_unmatched_hash_token_*`, and the Playwright spec in `tests/e2e/task-panel.spec.js`.

## Search

- The search page's `#project`/`@tag` tokens go through the same server-side parser (`SearchController::parseTokens`, `/search/parse-tokens`). The autocomplete dropdown stays client-side, and a clicked suggestion passes its known id straight through. `#inbox` is a search-only sentinel stripped before the parser runs.
- "Search in" checkboxes: Title and Description (default on), Comments (`search_comments`, default off). Matching is an OR across the checked fields, built **inside** the closure already scoped by `Task::visibleTo(Auth::id())`, so a comment match can never reveal an invisible task.
- "Templates" (`search_templates`, default off) is a separate checkbox in the same row, but it is not a text field: when unchecked, `buildSearchQuery()` applies `outsideTemplates()`; when checked, template tasks are included alongside normal ones (still scoped by `visibleTo`). It satisfies the "at least one box" check on its own (with no field box checked, only template tasks are returned), and the header quick search never sends it.
- With search text and no box checked, `SearchController::searchScopeErrorMessage()` returns a validation error (no silent fallback). It is shared by `index()` (200 with a banner and flashed `ViewErrorBag`) and `more()` (422 JSON). The header quick search sends `search_title=1&search_description=1` explicitly.
- Markdown export is handled inside `index()` and honors the same options.

## Project Pickers

Every place a task can be pointed at a project (`store()`, `update()`, `updateField()` project branch, `bulkUpdate()` move, API `create()`, tag show page) goes through `Project::forMember()` / `activeForUser()` and rejects `done`/`archived` targets. Use `activeForUser()`, not a raw `status != archived` filter.
