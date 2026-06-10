## Documentation

Full documentation is available at [taskfiend.kjcoop.com](https://taskfiend.kjcoop.com).

## About Task Fiend
This is a vibe-coded to-do list software that is a lot like many of the other open source task lists out there. None of them were *perfect* and watching machines program is fun, so I asked Claude to code one for me.

Because I designed this for my own devious purposes, I assumed there would only be two or three users. It would probably fine for more than that, but it's not designed to scale. It assumes at least one user is technical enough to run this on a server and run scripts at the command line.

### Features
- **Quick-add bar** on task list views — type a task name with natural language dates and inline shortcuts (`#project`, `@tag`, `+location`, `&user`, `nodate`) to autofill fields; autocomplete suggestions appear as you type. Paste multiple lines to create multiple tasks at once, or use Shift-Enter to add them in sequence.
- **Agenda view** on the day page — toggle between list and agenda (time-block) layouts; tasks can be quick-completed inline without a page reload. Click the date header to jump to any date.
- **Recurring tasks** — set a recurrence pattern on any task; completing it automatically creates the next occurrence (see `RECURRING_TASKS.md`)
- **Natural language dates & recurrence** — type natural language in the quick-add bar and dates/recurrence are parsed automatically (see below for full syntax)
- **Markdown** in task descriptions; `` `code` `` spans are also supported in task titles
- **Task links** — link to other tasks, projects, or locations from any description or comment using `[task:1]`, `[project:1]`, or `[location:1]`; IDs are shown on task and project pages
- **Project/tag navigation** — the header nav includes dropdowns listing all your projects and tags
- **Favorite projects** — star any project to mark it as a favorite; the project list can be filtered to show only favorites, making it easy to separate active work from back-burner projects
- **Task count breakdown** — click the task count on any list view to see a breakdown by project
- **Sorting** — sort task lists by date, duration, or other fields; a reverse-sort button flips the current order. Tasks without a date or time are sorted by creation date (newest last), so the bottom of an undated list stays stable.
- **Manual task reordering** — drag tasks to reorder them, with auto-scroll when dragging to the edge of the page. Scoot arrows (↑ ↓ ⤒ ⤓) let you nudge a task up, down, to the top, or to the bottom one tap at a time; the page follows the arrows so you don't have to hunt for them after each move.
- **Search** — find tasks by title, description, tags, projects, assignees, duration, and date presence; title and description can be targeted independently
- **Changelogs** — every create/edit is logged and browsable by task, project, tag, or user
- **API** — create and query tasks via bearer token (see Admin-Like Functions below for key management)

### Local Development Setup:
1. Create environment file: `cp .env.example .env`
2. Run the all-in-one setup command: `composer setup`
   - This installs PHP and JS dependencies, generates an app key, runs migrations, and builds frontend assets.
3. Create your first user: `php artisan user:create admin@example.com "Admin User" password123`
4. Start the dev server: `php artisan serve`

### Get Docker Running:
1. Create environment file: `cp .env.example .env`
2. Edit .env and set APP_KEY, APP_ENV=production, APP_DEBUG=false
3. Build and start containers: `docker compose up -d --build` (or `docker-compose` for Docker V1)
4. Run migrations and create first user:
   - `docker compose exec app php artisan migrate --force`
   - `docker compose exec app php artisan user:create admin@example.com "Admin User" password123`


### Admin-Like Functions
There is no admin UI. Here are the commands to do admin-like things.

#### Create/Disable users
- Create a user: `php artisan user:create {email} {name} {password}`
- Toggle whether a user is enabled or disabled: `php artisan user:toggle {email}`

A user who is disabled is unable to log in, but their tasks remain present. If they're re-enabled, their tasks are still waiting for them. Tasks they've assigned to other user(s) are still available to those other user(s).

#### API Keys
- Create an API key: `php artisan apikey:create {email}` - this will only print the API key the one time, so note the value immediately.
- Disable an API key: `php artisan apikey:invalidate {key}`

An invalidated API key cannot be re-validated.

#### Importing from Todoist
See TODOIST_IMPORT_SPEC.md

#### Purging User Data
The idea is to keep data even after users go away, but I needed this for testing the Todoist import:
`php artisan user:purge [email address] --force`

#### Move Overdue Tasks to Today
I only want to do this in dev, so this is run at the command line:
`php artisan tasks:move-overdue`


### Adding Other Pages
I created this functionality so somebody can put up their own Privacy Policy, Terms of Service, documentation or whatever.

To add a new page:
1. Put a Markdown file in storage/app/other-links
2. A link pointing to it will appear as a link on the page /other-links. 
   - You can access it at /other-links/[filename]. 
   - The title will be the filename minus the extension with - and _ replaced with spaces. The example file in there, Read-Me.md, will show up with the title Read Me.
3. The code will turn the Markdown into HTML and spit out the contents. 

If you add documentation, please consider contributing it back to this project. If it's something you/your users need documented, there's probably somebody else out there who could also use it.

### Natural Language Date & Recurrence Syntax

The quick-add bar parses natural language from the task name. The recurrence pattern field on the task edit page accepts the same recurrence syntax directly.

#### Scheduling a date

| What you type | Result |
|---|---|
| `today` | Today |
| `tomorrow` | Tomorrow |
| `Friday` | Next Friday |
| `next Friday` | The Friday of next week (skips this week's) |
| `January 15` | Jan 15 (next occurrence) |
| `3/15` | March 15 (next occurrence) |
| `2026-03-15` | Exact date |
| `nodate` | Removes the date |

**Multiple day names:** if your task name contains more than one day name (e.g. "Letter that comes on Sunday Tuesday"), the *last* day name is used for scheduling and the earlier ones are left in the title.

#### Recurrence (detected automatically in quick-add, or typed directly in the recurrence field)

| What you type | Recurs… |
|---|---|
| `daily` / `every day` | Every day |
| `weekdays` | Mon–Fri |
| `weekends` | Sat–Sun |
| `every other day` | Every 2 days |
| `Fridays` / `every Friday` | Every Friday |
| `every other Friday` | Every 2 weeks on Friday |
| `Mon, Wed, Fri` / `mon,wed,fri` | Every Monday, Wednesday, and Friday |
| `weekly` / `every week` | Every 7 days |
| `every other week` | Every 2 weeks |
| `every 3 weeks` | Every 3 weeks |
| `every 10 days` | Every 10 days |
| `monthly` / `every month` | Every month |
| `every 3rd Sunday` / `third Sunday of the month` | Monthly on that ordinal weekday (supports 1st–4th and last; word or numeric) |
| `every 15` / `every 15th` | Monthly on the 15th |
| `every 3 months` | Every 3 months |
| `yearly` / `every year` | Yearly |

**Floating recurrence:** prefix `every!` (with exclamation mark) to make the next occurrence relative to *when you complete the task* rather than the scheduled date. Useful for "floss every! 3 days" where slipping a day shouldn't cause perpetual catch-up.

**Recurrence field abbreviations:** day abbreviations like `Thu`, `Thurs`, `Tue`, `Tues` are accepted and treated the same as the full name.

#### Quick-add extras

Add a project, tags, location, or assignees inline by name — autocomplete suggestions appear as you type. Unknown `#things` and `@things` are ignored rather than silently stripped.

| Token | Effect |
|---|---|
| `#project-name` | Assigns task to that project |
| `@tag-name` | Applies that tag |
| `+location` | Sets the task location |
| `&username` | Assigns that user; for multiple: `&user1,user2` or `&user1 &user2` |

Multiple tags are supported: `Buy milk @errands @today`.

#### Filter bar

On list views (day, project, tag, search) a filter bar sits above the task list. In addition to plain text, it accepts:

| Token | Effect |
|---|---|
| `not:project-name` | Show only tasks *not* in that project |
| `not:tag-name` | Show only tasks *not* tagged with that tag |

Filtering applies to completed and archived tasks as well; expand the done/archived section after filtering to see the filtered results.

### Frontend Assets

The following third-party assets are vendored into this repository so the app works without external CDN requests at runtime.

| Asset | Source | License |
|-------|--------|---------|
| **Figtree** font (woff2 + CSS) | [Fontsource](https://fontsource.org/fonts/figtree) — originally by Erik Kennedy | SIL OFL 1.1 (see `public/fonts/OFL.txt`) |
| **Instrument Sans** font (woff2 + CSS) | [Fontsource](https://fontsource.org/fonts/instrument-sans) — originally by Rodrigo Fuenzalida / Instrument | SIL OFL 1.1 (see `public/fonts/OFL.txt`) |
| **marked.js** v15.0.12 (`public/js/vendor/marked.min.js`) | [github.com/markedjs/marked](https://github.com/markedjs/marked) | MIT (copyright notice in file header) |

### Find Tasks without Tags
I like most of my tasks to have tags. It seemed like overkill to program a solution for something I do so rarely. This query will get the required results. I'll forget it forever in approximately 17 seconds if I don't write it down:

```
SELECT tasks.id, name FROM tasks
LEFT JOIN tag_task ON tasks.id = tag_task.task_id
WHERE tag_task.task_id IS NULL
AND tasks.status = 'incomplete';
```
