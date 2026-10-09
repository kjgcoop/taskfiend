---
title: "Features"
---

## Features Found on Many Pages
- **Changelogs** - every create/edit is logged and browsable by task, project, tag, or user. This is displayed on the task page and in the Activity page. Although from there you can filter results down to a given project, there is no view on the project page to see activity related to just it. Maybe there should be.
- **API** - create and query tasks via bearer token (see [Admin-Like Functions](/docs/features/admin-functionality/) for key management)
- **Project/tag navigation** - the header nav includes dropdowns listing all your projects, tags and templates. The Templates dropdown is a convenience: there's no page per template, so choosing one scrolls you to it on the templates index.
- **Live change alerts** - alerts about changes other users made to your shared projects and tasks appear in more or less real time while a tab is open and visible; no page load required.
- **[Email notifications](/docs/developers/)** - opt in under Email Preferences on your profile to get that day's tasks emailed each morning, either inline as a digest or as an attached PNG. Requires Mailgun to be configured by whoever hosts your instance.
- **Other Links** - whoever runs your instance can add their own pages (a privacy policy, house rules, extra documentation) as Markdown files; they show up at the bottom of the **More** menu. See [Adding pages to Other Links](/docs/developers/#adding-pages-to-other-links).

### Task Lists
- **Quick-add bar** on task list views - type a task name with natural language dates and inline shortcuts (`#project`, `@tag`, `+location`, `&user`, `nodate`) to autofill fields; autocomplete suggestions appear as you type. Paste multiple lines to create multiple tasks at once, or use Shift-Enter to add them in sequence.
- **Multi-select** - activate with the clipboard icon next to the quick-add bar to select multiple tasks and perform bulk operations including status, project (combo box), location, and duration. Only tasks on the current page are affected; if the list is paginated, navigate to each page to act on those tasks.
- **Duration in task lists** - tasks that have a duration set show it directly in list view rows so you can gauge workload at a glance
- **Sorting** - sort task lists by date, duration, or other fields; a reverse-sort button flips the current order. Tasks without a date or time are sorted by creation date (newest last) - the bottom of an undated list stays stable. Created date is always the secondary sort.
- **Assignee icons show only other people** - a task's assignee icons leave out you, the person viewing the list. You're obviously an assignee or you wouldn't see it, so only your collaborators' icons appear.
- **Recurrence tooltip** - hover or long-press a task's recurrence pattern to see when the series ends.
- **Task count tooltip** - hover or long-press the count in a list header for the project breakdown; it now stays on screen on phones.
- **Manual task reordering** - drag tasks to reorder them, with auto-scroll when dragging to the edge of the page. Scoot arrows (↑ ↓ ⤒ ⤓) let you nudge a task up, down, to the top, or to the bottom one tap at a time; the page follows the arrows so you don't have to hunt for them after each move.

### Tasks
- **Two completed statuses** - statuses done vs archived allow you to see what got completed vs what was intentionally discarded.
- **Natural language dates & recurrence** - type natural language in the quick-add bar and dates/recurrence are parsed automatically (see below for full syntax)
- **Markdown** is supported in task descriptions and comments; `` `code` `` spans are also supported in task titles
- **Parent task in the sidebar** - the parent is shown and editable in the task sidebar as well as on the full task page. Click the parent's name to change it; the little link icon next to it takes you to the parent.
- **Parents and children can live in different projects** - suppose the parent is in Project A and its children are in Project B. Project A lists the children like any other subtasks. Project B has no line item for the parent, so each child says it's a subtask of the parent, with the parent's project.
- **Archived tags stay hidden** - archived tags no longer appear in task lists, the sidebar panel, or pickers. Archiving doesn't detach the tag from tasks that already have it.
- **Attachments** - images may be JPEG, PNG, AVIF and so on; long filenames are truncated so they can't push the Post button out of reach.
- **Comment-specific attachments** - attachments on comments are displayed next to the comment they relate to, as opposed to up associated with the whole task.

#### [Recurring Tasks](/docs/features/recurring-tasks/) 
Set a recurrence pattern on any task; completing it automatically creates the next occurrence.

#### Links
Link to other tasks, projects, tags, or locations from any description or comment.
- `[task:1]`, `[project:1]`, or `[tag:1]` will be replaced with the ID and title of the relevant task, project or tag. About the asterisk on the location: there isn't a locations table, it's just a column in the tasks table. Consequently, there's no ID. So `[location:Home]` will link to all the tasks with that location. It works with multi-word locations, no quotes necessary
- `[location:value]`: there isn't a locations table, it's just a column in the tasks table. Consequently, there's no ID. So `[location:Home]` will link to all the tasks with that location. It works with multi-word locations, no quotes necessary
- You can also paste a task, project or tag URL and it'll know to replace it with the ID followed by the thing's name.

## Page-Specific Features
### Project Index Page
- **Task count breakdown** - click the task count on any list view to see a breakdown by project
- **Import from a markdown file** - create a brand-new project by uploading a `.md` file, or export an existing project, rearrange tasks in any text editor, and re-upload to sync changes back; new tasks are created, statuses updated, and incomplete task order reset to match the file (see [Import from .md](/docs/features/import-from-md))
- **Member avatars on project cards** - project boxes show the icons of the other people on the project (not you).
- **Favorite projects** - star any project to mark it as a favorite from the project list or the project's own page; the project list can be filtered to show only favorites, making it easy to separate active work from back-burner projects

### Project Page
- **Project Details modal** has a single Save button for all its fields, and the modal stays open while it saves instead of the page reloading out from under it.
- **Reminder notes** - a project reminder can carry an optional note, to remind you why you're reminding yourself. It shows on the day view and the reminders list, and carries forward when a recurring reminder is dismissed.
- **Background on creation** - the New Project form takes an optional background image; you no longer have to create the project and upload one as a second step.

### Templates Page
- **Public or private** - a saved template's visibility can be toggled after creation.
- **README in exports** - every template zip includes a `README.md` describing what it is and how to import it, for when you find a random zip on your hard drive in six months.

### Scheduled Projects
- **Live template contents** - a project scheduled from a template matches the template's contents on the day it's actually created, not the day it was scheduled. Edits you make to the template in the meantime are picked up.

### Search Page
- There's a magnifying glass in the header that, when clicked, will provide a search input. Submitting a search takes you to the dedicated search page.
- There's also a dedicated search page. It can, as you might expect, find tasks by title, description, comments, tags, projects, assignees, duration, and date presence; title, description, and comments can each be targeted independently via checkboxes under "Search in" (Title and Description are checked by default; Comments is not). A comment match never surfaces a task you can't otherwise see. If you enter search text but uncheck all three, the page shows a validation error and runs no search rather than silently searching everything. The quick search (magnifying glass in the header) always searches Title and Description.
- The search box itself understands `#project` and `@tag` the same way the quick-add bar does, hyphenated multi-word names included (e.g. `#home-renovation` matches a project named "Home Renovation") — it's the same matching logic under the hood, not a lookalike.

### Overdue Page
- Everything here is incomplete by definition, so there's no done/archived folding to think about.
- **Export MD** works the same as it does on the Day page: it reflects whatever's currently visible under the on-page text filter, or everything overdue if no filter is active.

### Day Page
- Tasks can be marked done inline without a page reload, but to mark a task archived, you need to open it up and update the dropdown.
- Click the date header to jump to any date.
    - Toggle between layouts with the buttons on the right extreme of the page, toward the top. 
        - **List view** - pretty much what it sounds like.
        - **Agenda view** - use the time-unit dropdown to control how finely the agenda is divided. 
    - [Exports](/docs/features/day-export/) 
        - **Export MD** downloads a plain markdown checklist of the day being viewed; 
        - **Export PDF** downloads a printable, foldable single-column checklist of the viewed day's incomplete tasks, mirroring the current sort and filter, meant for keeping a paper to-do list instead of checking your phone (see [Day Exports](/docs/features/day-export/))
        - **Export PNG** (all three exports live under the three-dot menu at every screen width) is in many respects the same as the PDF export, except it's one long image so it can be sent to a receipt printer.
