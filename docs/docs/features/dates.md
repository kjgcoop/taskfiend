## Assigned date

Assigned dates are always in the future.

You can assign a date in any of the following formats:

- `today`, `tomorrow`
- Day of week such as `Sunday` or `Sun` — **also sets a weekly recurrence**; use `next Sunday` if you want a one-time date with no recurrence
- `next Sunday` (or any weekday) — one-time date for the next occurrence of that day, no recurrence set
- `yyyy-mm-dd` such as `2026-01-24`
- Month and day such as `January 24` or `1/24` — uses the next future occurrence of that date

If you'd like to refer to a specific day without using it as the due date, add a second date or use `nodate`. The last date wins. For example, "Reply to the letter that came on Sunday Tuesday" becomes a task due Tuesday with the title "Reply to the letter that came on Sunday".

## Recurrences

To make a task recurring, set a recurrence pattern using `every [interval]` or `every! [interval]`. The exclamation mark after `every` indicates [floating recurrence](#floating-recurrence).

### Patterns supported

| Pattern | Description |
|---|---|
| `daily` or `every day` | Every day |
| `every other day` | Every two days |
| `weekdays` | Monday–Friday |
| `weekends` | Saturday and Sunday |
| `weekly` or `every week` | Every week |
| `every other week` | Every two weeks |
| `every N weeks` | Every N weeks (e.g. `every 3 weeks`) |
| `Monday` / `every Monday` | Every Monday (or any weekday name) |
| `every other Wednesday` | Every two weeks on Wednesday (works with any weekday) |
| `mon,tue,fri` | Multiple days per week — comma-separated 3-letter abbreviations, no spaces |
| `every first Monday` | First Monday of each month (supports 1st–4th and last, word or numeric) |
| `every last Friday` | Last Friday of each month |
| `every 15th` or `every 15` | On the 15th of each month (any day 1–31) |
| `monthly` or `every month` | Every month on the same day |
| `every N months` | Every N months |
| `yearly` or `every year` | Every year |

Day abbreviations are flexible: `Thu`, `Thurs`, `Tue`, `Tues`, `Wed`, `Weds`, `Sun`, `Suns` are all accepted.

### Floating recurrence

Prefix your pattern with `every!` to make the next occurrence relative to when you *complete* the task rather than the original due date.

- `every week` on a task due Tuesday → the next instance always lands on Tuesday, even if you complete it on Wednesday.
- `every! week` → the next instance is scheduled one week from whenever you actually complete it.

Useful for habits where slipping a day shouldn't cause permanent catch-up. See [Recurring Tasks](recurring-tasks.md#floating-recurrence) for more detail.
