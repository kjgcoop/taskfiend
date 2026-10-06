---
paths:
  - "app/Services/DateParser.php"
  - "app/Services/QuickAddParser.php"
  - "tests/Unit/DateParserTest.php"
  - "docs/content/docs/features/dates.md"
---

# Date Parser

`App\Services\DateParser` parses natural-language dates and recurrence out of task names (quick-add bar) and validates recurrence patterns typed into the task edit form. It is used by `TaskController` and `Api\TaskApiController`, which auto-parse the name when `datetime`/`recurrence_pattern` aren't given explicitly.

## Key Methods

- `parseTaskInput(string)`: extracts name, date, `recurrence_pattern`, `recurrence_floating`
- `getNextOccurrence(string $pattern, Carbon $current)`: strictly typed; passing a formatted string throws `TypeError`
- `isValidRecurrencePattern(string)`: bool, used for validation
- `detectUnrecognizedPattern(string)`: error string if input looks like a recurrence attempt but doesn't parse
- `parseRelativeDuration()`: called from `resolveDate()`, which backs `POST /tasks/parse-date`

## Date Tokens (Quick-Add Bar)

`today`, `tomorrow`; day names (next occurrence); `next Monday` (skips this week); `January 15`, `3/15`, `2026-03-15`. With several day names the last one schedules and earlier ones stay in the title (`"Letter on Sunday Tuesday"` becomes title `"Letter on Sunday"`, date Tuesday). `"Team sync every Tuesday"` must parse as title `"Team sync"`.

## Recurrence Patterns (Quick-Add Bar and Recurrence Field)

- `daily` / `every day`; `weekdays`; `weekends`; `every other day`; `every N days`
- `Fridays` / `every Friday` (weekly; a plural or "every" prefix signals recurrence); `every other Friday` (bi-weekly)
- `Monday, Wednesday, Friday` / `mon,wed,fri` (multi-day weekly)
- `weekly` / `every week`, `every other week`, `every N weeks`
- `monthly` / `every month`, `every N months`
- Monthly ordinal: `every 3rd Sunday`, `every third Sunday`, `third Sunday of the month`, `every 3rd Sunday of the month` (1st-4th and last; word or numeric)
- `every 15` / `every 15th`: monthly on a day-of-month
- `yearly` / `every year`
- `every!` prefix: floating recurrence (next occurrence relative to *completion*, not scheduled date)
- Abbreviations accepted in the recurrence field: `Thu`, `Thurs`, `Tue`, `Tues`, `Wed`, `Weds`, `Sun`, `Suns`

## Relative Dates (Task Date Field Only)

Create/edit forms only, **not** the quick-add bar (a deliberately separate, narrower pattern so quick-add never picks it up). `parseRelativeDuration()` matches the *entire* trimmed input against `^(\d+)\s+(day|days|week|weeks|month|months|year|years)$`. Zero, negatives, fractions and compound input (`1 week 2 days`) simply fail to match and fall through to the existing invalid-date error. Always relative to `Carbon::today()`.
