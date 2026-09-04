---
title: "Day Exports"
---

The day view (`/day`) has a three dot menu in its header, next to the date. All the menu items are different types of exports. All reflect any filtering done on the page. Statuses (incomplete, done, archived) not unfolded are not represented in these exports:

## Export PDF
This page displays tasks in one to four columns (value set in `.env` with `DAY_EXPORT_COLUMNS`; max and min are hard-coded; defaults to 2). I did this so I could print it, but that was an extravagant waste of paper. This is kind of awkward and minimally useful as-is. To me, anyway. Your use case may be different. I didn't take it out because it's not hurting anything.

Downloads a printable checklist of a day's incomplete tasks — `taskfiend-day-YYYY-MM-DD.pdf` —
meant to be printed, folded down to pocket size, and marked up with a highlighter through the day
instead of checking the app on your phone.

## Export PNG
This is designed to be a narrow document that gets, if necessary, quite long. I have a receipt reader to print my day's tasks so I'm not in my browser during the work day. I found I was just taking screenshots of the PDF export. That was no fun.

## Export MD
This is a little different from the above. Sometimes I need a full text editor to think, as opposed to several lines. To that end, you can export to markdown, monkey with the tasks, then import it again - see [Import from Markdown](/docs/features/import-from-md/).

A sample export:
```markdown
# Monday, November 10, 2025

## Incomplete
* Buy milk
* Call dentist

## Done
* Morning workout
```


## Limitations
- It only exports tasks on the page. If the list is paginated (see `PAGINATION_PER_PAGE` in `.env`), you 
  won't see the complete list of tasks. Consider the possiblity of having 10 tasks per page and 12 tasks. 
  Tasks #1, #2 and #11 match your term. It will only show tasks #1 and #2. It won't go check/grab the two not 
  displayed on the page.

- Regarding recurring tasks on a future date you haven't reached yet - this export only includes tasks
  that already exist as real rows. A recurring task's *next* occurrence isn't created until you
  mark the *current* occurrence done (see [Recurring tasks](/docs/features/recurring-tasks/)). Exporting a future date won't 
  include a recurring task that hasn't yet been generated for that date, even if it's projected to 
  recur on that day. For floating tasks that happen say once a week, we don't necessarily know when 
  the next instance will be. We could assume you'll complete all the instances between now and the day 
  in question on the day they're slated to appear. For example, say you currently have a task due once 
  a week whose current instance is on a Friday. We could assume that you'll do this every Friday between 
  now and whatever date you're looking up. This gets computationally expensive quickly. It would be really
  nice though.

- For the PDF and PNG exports, task names are rendered in a standard PDF font (Helvetica) with no embedded fonts, so
  characters outside basic Latin — emoji, CJK, and similar — won't render correctly in the PDF or PNG. Plain-text 
  task titles are unaffected.

- Apparently if you have your server set up in a way that pleases Claude, you'll get pretty fonts or something in your PDF/PNG export. My server did not spark joy, so my text looks like [Courier](https://en.wikipedia.org/wiki/Courier_(typeface)).
