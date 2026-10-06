---
paths:
  - "resources/views/**"
  - "resources/js/**"
---

# Frontend: Alpine.js (CSP-Safe Build)

Full writeup: `docs/content/docs/developers/frontend-csp.md`.

## The Pitfall

Alpine is loaded in CSP-safe mode (no `unsafe-eval`; see `csp_nonce()` on every `<script>`). Directive expressions (`x-data`, `@click`, `@input`, `:class`, ...) accept a single JS *expression*: member access, comparisons, ternaries, function calls. They do **not** accept *statements*: no `const`/`let`/`if`, no semicolon-joined blocks.

A violation fails **silently** on the page. The only symptom is a console error like `Uncaught Error: CSP Parser Error: Unexpected token: ...`. Blade compilation (`view:cache`) will not catch it.

**Fix**: put the logic in a method on an `Alpine.data()` component (registered on `alpine:init`) and call it with a bare expression, e.g. `@click="go()"`. Established examples: `dayPdfExport` (`dashboard/day.blade.php`), `sortBy()` / `staleBanner`, `taskParentPicker().searchParent()`, `projectComboBox.focusSearch()`.

**Audit grep** after touching views: look for `@directive="...;..."` and for bare `if`/`const`/`let`/`var` inside directives.

## Shared Helpers and Where They Live

- Plain global functions in `layouts/app.blade.php`'s inline script (`slugify()`, `taskParentPicker()`, `window.initTaskSortable`, `saveTaskOrder`, `taskMoveInList`, `updateSortButtonStates`, `setNotificationBadge`) are visible to every later inline script on the page, including `@push('scripts')` blocks.
- Use the shared `slugify()` for `#project`/`@tag` text (spaces become dashes: `home-renovation`). Don't write inline regexes that delete spaces.
- `window.collectVisibleFilterableTaskIds()` (in `resources/js/app.js`) collects the visible `[data-filterable]` task ids; export buttons send them as `ids[]`.
- `Alpine.data('taskSortableList', ...)` is registered globally in `resources/js/app.js` as well as locally in `task-list.blade.php`. The registrations are identical.
- **Spreading a getter copies its value, not the accessor.** `taskParentPicker()` therefore exposes `parentFiltered()` as a method; call it with parentheses. A host component spread via `...taskParentPicker()` must supply `fields.parent_id` and `original.parentSearch`.
- `taskEditor` (task page) and `taskPanelEditor` (sidebar panel) both spread `taskParentPicker()`. The panel's initial state comes from the `data-task-json` payload (`_panelTaskJson`).

## Sortable Lists

Only the top level is sortable (`$depth === 0`) in both `task-list` and `subtask-list`. Nested sortable containers each register a `pointerdown` listener, and a single drag handle then bubbles into several listeners and corrupts the drag. `TaskController::reorder()` accepts any `ids[]` and stamps `sort_order` by position, so subtask reordering needs no backend change.

## Task Sidebar Panel

- `#task-panel-header` is a flex-pinned slot, **not** `position: sticky`. Sticky-inside-scroll-inside-fixed made mobile WebKit's native "Select All" callout drift.
- The partial `tasks/_panel.blade.php` marks the header with `[data-panel-header]`; `_loadContent()` moves it into the header slot. `Alpine.initTree()`/`destroyTree()` must cover both `#task-panel-header` and `#task-panel-content`.

## Navigation and Heartbeat

- The nav's hamburger layout covers up to 767px (`md`); between `md` and `lg` spacing is tighter and the "Search" text link is hidden (the More menu has an `lg:hidden` entry).
- The bell indicators are `[data-notif-badge="count"|"dot"]`, all updated by `setNotificationBadge(n)`. `#notif-badge` is always rendered (hidden at 0).
- Session heartbeat (end of `layouts/app.blade.php`) polls `GET /auth/check` every `SESSION_CHECK_INTERVAL` seconds (`0` disables) **only while the tab is visible**, and redirects to `/login` only on a 401. `/auth/check` also returns `unread`; it is read-only. The day page's "past midnight" `staleBanner` is separate and purely client-side.

## Misc

- Day view: the task input bar is always rendered outside the `showIncomplete` collapse gate.
- Dark theme: `bg-black` page, `bg-gray-800` containers/nav with `border-gray-700`, text `gray-100` (headers) / `300` (labels) / `400` (body) / `500` (muted), inputs `bg-gray-700` + `border-gray-600`, primary buttons `bg-blue-600`. Status badge and tag colors are preserved.
