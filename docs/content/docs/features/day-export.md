---
title: "Day Exports"
---

The day view (`/day`) has two export buttons in its header, next to the date. Both produce a
one-way, read-only snapshot of the day — neither is meant to be edited and re-imported (compare
[Import from Markdown](/docs/features/import-from-md/), which is a project-level, round-trip
feature).

## Export .md

Downloads a plain markdown checklist of the day currently being viewed (works on any date, not
just today), split into Incomplete / Done / Archived sections:

```
# Monday, November 10, 2025

## Incomplete
* Buy milk
* Call dentist

## Done
* Morning workout
```

This is a fixed snapshot: it doesn't reflect the on-page sort order or the text filter box, and it
always includes all three sections regardless of what's expanded on screen.

## Export PDF

Downloads a printable checklist of a day's incomplete tasks — `taskfiend-day-YYYY-MM-DD.pdf` —
meant to be printed, folded down to pocket size, and marked up with a highlighter through the day
instead of checking the app on your phone.

- **Works for today or any future date** — whatever date you're currently viewing on the day page.
  Past dates aren't supported, since past days use a different page.
- **Mirrors what's on screen.** Unlike Export .md, this respects the day view's current sort order
  and its on-page text filter box — filter the list down to `#errands` first, and the PDF only
  contains those tasks. It's a snapshot of exactly what was visible the moment you clicked the
  button (rather than the filter being freshly re-evaluated against the database at export time),
  so it stays faithful to what you were actually looking at even if something changes in the
  seconds between filtering and clicking. The filter/sort in effect (if not the defaults) is
  printed at the top of the page as a plain reminder of how the list was narrowed.
- **Always incomplete tasks only,** regardless of any status filter that happens to be active on
  screen — this export is a to-do list, not an archive.
- **Layout**: a two-column list on a US Letter page — task time (if it has one) in a narrow gutter
  on the left, a divider line under each row instead of a bullet or checkbox — sized so that folding
  the sheet in half twice gets it down to roughly pocket size. The idea is to cross off or highlight
  items by hand and throw the sheet away at the end of the day, not to bring it back into the app.
- **Fills column 1 completely before starting column 2.** If everything fits in one column, the
  second column is intentionally left blank rather than splitting the list evenly across both —
  this keeps the reading order predictable when you're working through the page top to bottom.
- The button is disabled whenever there's nothing to export (an empty list, or a filter that
  matches nothing).

### Limitations

- **Task names** are rendered in a standard PDF font (Helvetica) with no embedded fonts, so
  characters outside basic Latin — emoji, CJK, and similar — won't render correctly in the PDF.
  Plain-text task titles are unaffected.
- **Recurring tasks on a future date you haven't reached yet.** This export only includes tasks
  that already exist as real rows. A recurring task's *next* occurrence isn't created until you
  mark the *current* occurrence done (see [Recurring tasks](/docs/features/recurring-tasks/)), so
  exporting a future date won't include a recurring task that hasn't been generated for that date
  yet — even if it "should" logically be due by then. There's currently no way to preview what a
  recurring series would project onto a future date.
