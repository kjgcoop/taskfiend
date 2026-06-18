### Features
- **Quick-add bar** on task list views — type a task name with natural language dates and inline shortcuts (`#project`, `@tag`, `+location`, `&user`, `nodate`) to autofill fields; autocomplete suggestions appear as you type. Paste multiple lines to create multiple tasks at once, or use Shift-Enter to add them in sequence.
- **Agenda view** on the day page — toggle between list and agenda (time-block) layouts; tasks can be quick-completed inline without a page reload. Click the date header to jump to any date. Use the time-unit dropdown to control how finely the agenda is divided. The task detail sidebar is available in agenda view.
- **Recurring tasks** — set a recurrence pattern on any task; completing it automatically creates the next occurrence (see `RECURRING_TASKS.md`)
- **Natural language dates & recurrence** — type natural language in the quick-add bar and dates/recurrence are parsed automatically (see below for full syntax)
- **Markdown** in task descriptions; `` `code` `` spans are also supported in task titles
- **Task links** — link to other tasks, projects, tags, or locations from any description or comment using `[task:1]`, `[project:1]`, `[tag:1]`, or `[location:1]`; IDs are shown on the relevant pages
- **Project/tag navigation** — the header nav includes dropdowns listing all your projects and tags
- **Favorite projects** — star any project to mark it as a favorite from the project list or the project's own page; the project list can be filtered to show only favorites, making it easy to separate active work from back-burner projects
- **Task count breakdown** — click the task count on any list view to see a breakdown by project
- **Duration in task lists** — tasks that have a duration set show it directly in list view rows so you can gauge workload at a glance
- **Sorting** — sort task lists by date, duration, or other fields; a reverse-sort button flips the current order. Tasks without a date or time are sorted by creation date (newest last), so the bottom of an undated list stays stable.
- **Manual task reordering** — drag tasks to reorder them, with auto-scroll when dragging to the edge of the page. Scoot arrows (↑ ↓ ⤒ ⤓) let you nudge a task up, down, to the top, or to the bottom one tap at a time; the page follows the arrows so you don't have to hunt for them after each move.
- **Import from a markdown file** — create a brand-new project by uploading a `.md` file, or export an existing project, rearrange tasks in any text editor, and re-upload to sync changes back; new tasks are created, statuses updated, and incomplete task order reset to match the file (see [Import from .md](import-from-md.md))
- **Multi-select** — activate with the clipboard icon next to the quick-add bar to select multiple tasks and perform bulk operations including status, project (combo box), location, and duration. Only tasks on the current page are affected; if the list is paginated, navigate to each page to act on those tasks.
- **Search** — find tasks by title, description, tags, projects, assignees, duration, and date presence; title and description can be targeted independently
- **Inline comment images** — image attachments on comments are displayed inline alongside the download link
- **Changelogs** — every create/edit is logged and browsable by task, project, tag, or user
- **API** — create and query tasks via bearer token (see Admin-Like Functions below for key management)

