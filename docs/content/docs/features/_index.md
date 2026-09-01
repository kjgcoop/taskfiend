---
title: "Features"
---

## Features Found in Many Places
- **Two completed statuses** - statuses done vs archived allow you to see what got completed vs what was intentionally discarded.
- **[Recurring tasks](/docs/features/recurring-tasks/)** - set a recurrence pattern on any task; completing it automatically creates the next occurrence.
- **Quick-add bar** on task list views - type a task name with natural language dates and inline shortcuts (`#project`, `@tag`, `+location`, `&user`, `nodate`) to autofill fields; autocomplete suggestions appear as you type. Paste multiple lines to create multiple tasks at once, or use Shift-Enter to add them in sequence.
- **Natural language dates & recurrence** - type natural language in the quick-add bar and dates/recurrence are parsed automatically (see below for full syntax)
- **Markdown** is supported in task descriptions and comments; `` `code` `` spans are also supported in task titles
- **Task count breakdown** - click the task count on any list view to see a breakdown by project
- **Links** - link to other tasks, projects, tags, or locations from any description or comment.
    - `[task:1]`, `[project:1]`, or `[tag:1]` will be replaced with the ID and title of the relevant task, project or tag. About the asterisk on the location: there isn't a locations table, it's just a column in the tasks table. Consequently, there's no ID. So `[location:Home]` will link to all the tasks with that location. It works with multi-word locations, no quotes necessary
    - `[location:value]`: there isn't a locations table, it's just a column in the tasks table. Consequently, there's no ID. So `[location:Home]` will link to all the tasks with that location. It works with multi-word locations, no quotes necessary
    - You can also paste a task, project or tag URL and it'll know to replace it with the ID followed by the thing's name.
- **Duration in task lists** - tasks that have a duration set show it directly in list view rows so you can gauge workload at a glance
- **Sorting** - sort task lists by date, duration, or other fields; a reverse-sort button flips the current order. Tasks without a date or time are sorted by creation date (newest last), so the bottom of an undated list stays stable.
- **Manual task reordering** - drag tasks to reorder them, with auto-scroll when dragging to the edge of the page. Scoot arrows (↑ ↓ ⤒ ⤓) let you nudge a task up, down, to the top, or to the bottom one tap at a time; the page follows the arrows so you don't have to hunt for them after each move.
- **Import from a markdown file** - create a brand-new project by uploading a `.md` file, or export an existing project, rearrange tasks in any text editor, and re-upload to sync changes back; new tasks are created, statuses updated, and incomplete task order reset to match the file (see [Import from .md](/docs/features/import-from-md))
- **Multi-select** - activate with the clipboard icon next to the quick-add bar to select multiple tasks and perform bulk operations including status, project (combo box), location, and duration. Only tasks on the current page are affected; if the list is paginated, navigate to each page to act on those tasks.
- **Search** - find tasks by title, description, tags, projects, assignees, duration, and date presence; title and description can be targeted independently
- **Comment-specific attachments** - attachments on comments are displayed next to the comment they relate to, as opposed to up associated with the whole task.
- **Changelogs** - every create/edit is logged and browsable by task, project, tag, or user
- **API** - create and query tasks via bearer token (see [Admin-Like Functions](/docs/features/admin-functionality/) for key management)


## Day page
- Tasks can be marked done inline without a page reload, but to mark a task archived, you need to open it up and update the dropdown.
- Click the date header to jump to any date.
  - Toggle between layouts with the buttons on the right extreme of the page, toward the top. 
      - **List view** - pretty much what it sounds like.
      - **Agenda view** - use the time-unit dropdown to control how finely the agenda is divided. 
  - **Exports** 
      - **Export <D** downloads a plain markdown checklist of the day being viewed; 
      - **Export PDF** downloads a printable, foldable checklist of *today's* incomplete tasks, mirroring the current sort and filter, meant for keeping a paper to-do list instead of checking your phone (see [Day Exports](/docs/features/day-export/))
      - **Export PNG** is in many respects the same as the PDF export, except it's one long image so it can be sent to a receipt printer.

## Projects
- **Project/tag navigation** - the header nav includes dropdowns listing all your projects and tags
- **Favorite projects** - star any project to mark it as a favorite from the project list or the project's own page; the project list can be filtered to show only favorites, making it easy to separate active work from back-burner projects

