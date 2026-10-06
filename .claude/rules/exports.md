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

## Filtering by Visible IDs, Not Re-Parsed Text

The on-page filter box is client-side only. When it's active, the client sends the exact visible task ids as `ids[]` (collected by `window.collectVisibleFilterableTaskIds()`; with no active filter, no `ids[]` is sent and everything is exported). The server applies `whereIn('id', $ids)` **on top of** the already-authorized, scoped query: an id can only narrow the result, never widen it. The raw `filter` text is only a display string in the header meta line. Don't reintroduce a server-side port of the JS filter tokenizer (`TaskTextFilter` was deleted for drift risk).

The Overdue page's Markdown export (`DashboardController::exportOverdueMarkdown()`) uses the same narrowing-only `ids[]` pattern via an `overdueExport` Alpine component.

## Notes

- Day Markdown and PDF exports share the on-page sort/reversed params.
- Export buttons (MD/PDF/PNG) always live under the three-dot menu.
- Morning emails (`EmailSubscription`: `daily_digest`, `daily_png`) are sent by `email:task-digest --all` and `email:task-png --all`, scheduled at 06:00 in `routes/console.php`, via Mailgun's HTTP API (`MAILGUN_API_KEY`/`MAILGUN_BASE`). They need the `schedule:run` cron entry. Details: `docs/content/docs/developers/_index.md`.
