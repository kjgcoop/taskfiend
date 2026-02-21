# Todoist Import Specification

## Overview
Laravel Artisan command to import data from Todoist into Task Fiend. This is a one-time migration tool (repeatable but not for ongoing sync).

## Command Format
```bash
php artisan todoist:import --taskfiend-api-key=tfk_xxxxx --todoist-api-key=YOUR_TODOIST_KEY
```

## What to Import

### Include
- ✅ Active tasks (incomplete)
- ✅ Projects
- ✅ Labels/Tags
- ✅ Comments on tasks
- ✅ Task attachments
- ✅ Comment attachments

### Exclude
- ❌ Completed tasks
- ❌ Todoist priority levels (ignore)
- ❌ Todoist sections (ignore)
- ❌ Collaborator information (assign all to importing user)

## Data Mapping

| Todoist Concept | Task Fiend Concept | Notes |
|----------------|-------------------|-------|
| Project | Project | Direct mapping |
| Label | Tag | Auto-create if doesn't exist |
| Task | Task | Assigned to importing user |
| Comment | Comment | Attributed to importing user |
| File/Resource | TaskAttachment or CommentAttachment | Download and re-upload |
| Recurring Task | Task with recurrence_pattern | Preserve pattern; log if unparseable |
| Priority (1-4) | *ignored* | Not mapped |
| Section | *ignored* | Not mapped |

## Detailed Requirements

### 1. Authentication
- **Todoist API Token**: Passed via `--todoist-api-key` CLI option (falls back to `TODOIST_KEY` environment variable if not provided)
- **Task Fiend User**: Identified by API key passed as `--taskfiend-api-key` option
- All imported items attributed to this user

### 2. Scope
- Import **all projects** from Todoist (not filtered)
- Import **all active tasks** from those projects
- Import **all labels** referenced by tasks

### 3. Duplicate Handling (Idempotency)

#### Projects
- **Uniqueness**: Project name (global)
- **Conflict**: Skip if project with same name already exists
- **Action**: Log skipped duplicate

#### Tasks
- **Uniqueness**: Task name within project (e.g., "Research" can exist in both "Work" and "Personal")
- **Conflict**: Skip if task with same name exists in same project
- **Action**: Log skipped duplicate

#### Tags
- **Uniqueness**: Tag name (global)
- **Conflict**: Use existing tag if already exists
- **Action**: No logging needed (expected behavior)

### 4. File Handling

#### Task Attachments
- Download files from Todoist
- Store in `public/` directory (temporary location)
- Upload to Task Fiend via API
- Handle download/upload failures gracefully (log and continue)

#### Comment Attachments
- Same process as task attachments
- Associate with imported comment

### 5. Recurring Tasks
- Import with next due date
- Preserve recurrence pattern in Task Fiend format
- If DateParser cannot understand pattern:
  - Import task without recurrence_pattern
  - Log warning with: Task Fiend ID, Todoist recurrence string

### 6. Date/Time Handling
- Preserve times if present in Todoist
- Assume all dates/times are Pacific timezone
- Strip time if Todoist task has date-only due date

### 7. Comments
- All comments attributed to importing user (identified by API key)
- Import comment text as-is
- Preserve comment order/timestamps if possible

### 8. Subtasks (BLOCKED - NEEDS TASK FIEND FEATURE)
**Current Status**: Task Fiend schema does not support parent/child task relationships

**Options for Implementation** (to be decided after subtask feature exists):
- Option A: Import as regular tasks (lose hierarchy)
- Option B: Skip subtasks entirely (log for manual review)
- Option C: Prefix subtask names with indicator like `"  - "` or `"[Subtask] "`
- Option D: Use new subtask feature once implemented

**Action Required**:
1. Implement subtask functionality in Task Fiend
2. Return to this spec and choose implementation approach
3. Update import command accordingly

### 9. Error Handling
- **API Failures**: Log error, continue with next item
- **Download Failures**: Log error, continue without attachment
- **Upload Failures**: Log error, continue without attachment
- **Rate Limits**: Log error, continue (or implement retry logic)
- **Principle**: Be resilient - don't abort entire import for single failures

### 10. Logging

#### Progress Output (STDOUT)
- Show real-time progress as import proceeds
- Examples:
  - "Fetching projects from Todoist..."
  - "Imported project 'Work' (5/12 projects)"
  - "Importing tasks from project 'Personal'..."
  - "Imported 45/120 tasks..."
  - "Downloading attachment 'screenshot.png'..."

#### Error/Warning Logging (Laravel Log)
Use Laravel's `Log` facade for all logging.

**Log Categories:**

1. **Skipped Duplicates** (Info level)
   - Project name
   - Task name + project name

2. **Unparseable Recurrence** (Warning level)
   - Task Fiend ID
   - Todoist recurrence string
   - Task name for context

3. **API Errors** (Error level)
   - Endpoint
   - Error message
   - Context (which item was being processed)

4. **File Errors** (Warning level)
   - File URL/name
   - Error message
   - Associated task/comment

5. **Summary** (Info level)
   - Total items processed
   - Total items skipped
   - Total errors encountered

## Implementation Details

### Technology Stack
- **Framework**: Laravel Artisan command
- **HTTP Client**: Guzzle (use Laravel conventions)
- **API Integration**: Task Fiend REST API (not direct database access)
- **Logging**: Laravel `Log` facade (not custom logger)
- **Todoist API**: API v1 (`https://api.todoist.com/api/v1/`)

### Code Style
- Follow Laravel conventions throughout
- Use dependency injection
- Use Laravel collections where appropriate
- Prefer Laravel helpers over custom implementations

### API Reference
Reference package at `./todoist-api/` for:
- Todoist API endpoint patterns
- Request/response structure
- Authentication headers

Note: The reference package uses custom logger and UniformTask objects that should NOT be used in this implementation.

## Todoist API Endpoints Needed

Based on Todoist API v1 docs (unified API replacing REST v2):

```
GET /api/v1/projects            # Get all projects (paginated)
GET /api/v1/tasks               # Get all tasks (paginated, with filters)
GET /api/v1/labels              # Get all labels (paginated)
GET /api/v1/comments            # Get comments (paginated, by task or project)
```

Headers:
```
Authorization: Bearer {TODOIST_KEY}
```

### Pagination
All list endpoints use cursor-based pagination:
- **Request params**: `cursor` (string, optional), `limit` (integer 0-200, default 50)
- **Response format**: `{ "results": [...], "next_cursor": "string"|null }`
- Loop until `next_cursor` is null to fetch all results

## Task Fiend API Endpoints Needed

Refer to `routes/api.php` and existing API controllers. May need to extend API to support:
- Project creation
- Tag creation
- Task creation with full metadata (tags, attachments, recurrence)
- Comment creation with attachments

**Note**: Current API only supports basic task creation. This import command may require API extensions.

## Open Questions

1. Does Task Fiend API support full task creation (with tags, attachments, assignments)?
2. Does Task Fiend API support project creation?
3. Does Task Fiend API support comment creation with attachments?
4. If API is insufficient, should we extend it or use direct Eloquent models?

## Testing Plan

### Manual Testing
1. Test with empty Task Fiend database
2. Test with existing projects/tasks (verify duplicate skipping)
3. Test with tasks that have:
   - Attachments
   - Comments
   - Multiple labels
   - Recurring patterns
   - Subtasks (once feature exists)
4. Test error scenarios:
   - Invalid Task Fiend API key
   - Missing Todoist API key (neither CLI option nor env var)
   - Network failures
   - Invalid recurrence patterns

### Verification
After import, verify in Task Fiend UI:
- Projects created correctly
- Tasks in correct projects
- Tags applied correctly
- Attachments downloadable
- Comments visible
- Recurrence patterns working

## Future Enhancements

Once implemented, consider:
- Dry-run mode (`--dry-run` flag)
- Selective import (specific projects only)
- Progress persistence (resume interrupted imports)
- Mapping file for custom project/tag name translations
- Support for other users' Todoist collaborator mapping

---

## Implementation Status: COMPLETED ✅

### Usage Instructions

1. **Prerequisites**:
   - Obtain your Todoist API token from [Todoist Settings > Integrations > Developer](https://todoist.com/app/settings/integrations/developer)
   - Create a Task Fiend user and generate an API key:
     ```bash
     php artisan user:create your@email.com "Your Name" password123
     php artisan apikey:create your@email.com
     ```

2. **Run the import**:
   ```bash
   php artisan todoist:import --taskfiend-api-key=tfk_xxxxx --todoist-api-key=YOUR_TODOIST_KEY
   ```
   Alternatively, the Todoist key can be set via the `TODOIST_KEY` environment variable (the CLI option takes precedence):
   ```bash
   # Using env var for the Todoist key
   export TODOIST_KEY=YOUR_TODOIST_KEY
   php artisan todoist:import --taskfiend-api-key=tfk_xxxxx
   ```

3. **Monitor progress**:
   - Real-time progress is shown in the console
   - Detailed logs are written to Laravel log files
   - A summary is displayed at the end

### Implementation Notes

**What's Implemented**:
- ✅ Full project import with duplicate detection
- ✅ Tag/label import (reuses existing tags by name)
- ✅ Task import with support for:
  - Subtasks using `parent_id` field
  - Due dates (date and time)
  - Recurrence patterns (auto-converted using DateParser)
  - Tag associations
  - Automatic assignment to importing user
- ✅ Comment import with inline attachments
- ✅ Two-pass task import (parents first, then subtasks)
- ✅ Comprehensive error handling and logging
- ✅ Import statistics and summary

**Data Mapping Details**:
- Projects: Name-based duplicate detection (per user)
- Tasks: Name + project-based duplicate detection
- Tags: Global, reused if exists
- Subtasks: Imported in second pass after parents
- Recurrence: Converted via DateParser, logged if unparseable
- Attachments: Comment attachments only (Todoist API doesn't expose task attachments separately)

**Important Behaviors**:
- All imported items are assigned to the user identified by the API key
- Duplicate items are skipped (not overwritten)
- Errors are logged but don't abort the import
- Subtasks with missing parents are skipped
- Tasks with unknown projects are skipped
- Recurrence patterns that can't be parsed are imported without recurrence

### Known Limitations

1. **Task Attachments**: Todoist API doesn't expose task attachments via a separate endpoint. Attachments are imported via comments only.

2. **Subprojects**: Task Fiend doesn't support nested projects. All Todoist projects are imported as top-level projects.

3. **Sections**: Todoist sections are not mapped (as specified).

4. **Collaborators**: All tasks are assigned to the importing user only.

5. **Recurrence Patterns**: Some complex Todoist recurrence patterns may not convert perfectly to Task Fiend format. These are logged as warnings.
