---
paths:
  - "app/Services/DayPdfExporter.php"
  - "app/Services/DayPngExporter.php"
  - "app/Services/SimplePdfWriter.php"
  - "app/Http/Controllers/DashboardController.php"
  - "config/taskfiend.php"
  - "docs/content/docs/features/day-export.md"
---

# Exports (Day PDF / PNG / Markdown)

## Day PDF

- Button on the day view (any date the day view supports; past dates use the separate `dayReview()` template, which has no button). `DashboardController::exportDayPdf()` parses the `date` param like `day()` does, always restricts to **incomplete** tasks (intentional), and downloads `taskfiend-day-YYYY-MM-DD.pdf`.
- Always **single-column** (`DayPdfExporter::build()` has no `$columns` parameter; `DAY_EXPORT_COLUMNS` no longer exists). The "TODAY" eyebrow appears only when `$date->isToday()`. A new page starts when a row doesn't fit.
- Design: eyebrow label, bold date, dark header rule, a time gutter, a light divider under each row, meant to be printed, folded and highlighted (no checkboxes).
- `SimplePdfWriter` is a dependency-free PDF writer (no mpdf/dompdf). It supports multiple pages, positioned text in Helvetica / Helvetica-Bold (not embedded), `line()` strokes, gray fill and letter-spacing. Every call states its own color/spacing because PDF graphics state persists across `BT`/`ET` blocks. Text is transcoded to WinAnsi, so emoji and CJK are dropped (accepted). Layout uses real Helvetica glyph widths for word wrapping.
- `DayPngExporter` / `DAY_EXPORT_PNG_WIDTH` are a separate single-column PNG export. Other lists have no PNG export (decided not worth it).

## Markdown Exports Share One Implementation

All five Markdown exports (day, overdue, project, tag, search) render through `TaskMarkdownExport::response()` (`app/Services/TaskMarkdownExport.php`): `# Title`, then non-empty `## Incomplete` / `## Done` / `## Archived` sections of `* name` lines, with a task's eager-loaded `children` indented under it. Don't hand-build Markdown in a controller.

## Filtering by Visible IDs, Not Re-Parsed Text

The on-page filter box and section folds are client-side only. Export buttons send the exact visible task ids as `ids[]`, built by `window.visibleIdsParams()` (`resources/js/app.js`, on top of `window.collectVisibleFilterableTaskIds()`). The server reads them with `TaskMarkdownExport::visibleIds()` (null = no `ids` param, don't narrow; empty = the page showed nothing) and applies `whereIn` **on top of** the already-authorized, scoped query: an id can only narrow the result, never widen it. The raw `filter` text is only a display string in PDF/PNG headers. Don't reintroduce a server-side port of the JS filter tokenizer (`TaskTextFilter` was deleted for drift risk).

Two client modes, both via `Alpine.data('visibleIdsExport')` for link/button exports (`x-data="visibleIdsExport" @click.prevent="go()"`):

- **Mirror the screen** (project, tag, day): always send `ids[]`, so folded Done/Archived sections and collapsed subtasks are left out.
- **`data-only-when-filtered`** (Overdue, Search): send `ids[]` only while the on-page filter is active. Otherwise the export is the full result set, including "Load more" pages that aren't loaded yet. Overdue buttons have no `href`, so they use `data-export-url`.

The day page's PDF/PNG/MD menu (`dayExport`) calls `visibleIdsParams()` directly. Day's `dayExportTaskGroups()` is shared by its MD, PDF and PNG exports.

## Notes

- Day Markdown and PDF exports share the on-page sort/reversed params.
- The day exports, the digest and PNG emails and the day API all list `Task::onDayOf($user)`, the same query as the Day page, so they follow the user's "Also show dated tasks from projects I'm assigned to" profile setting. Don't give an export its own visibility query.
- Export buttons (MD/PDF/PNG) always live under the three-dot menu.
- Morning emails (`EmailSubscription`: `daily_digest`, `daily_png`) are sent by `email:task-digest --all` and `email:task-png --all`, scheduled at 06:00 in `routes/console.php`, via Mailgun's HTTP API (`MAILGUN_API_KEY`/`MAILGUN_BASE`). They need the `schedule:run` cron entry. Details: `docs/content/docs/developers/_index.md`.
