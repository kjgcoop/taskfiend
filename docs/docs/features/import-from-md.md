# Import Updates from .md

If you have a large, unwieldy project, you can export it as a markdown file, reorganize it in any text editor, and re-upload it to bring the app up to speed — without clicking through each task individually.

## How to use it

1. Open a project you own.
2. Click the **⋮** (three-dot) menu in the top right of the project header.
3. Select **Export .md** to download the current state of the project.
4. Edit the file in any text editor: reorder tasks, move them between sections, or add new ones.
5. Back in the ⋮ menu, select **Import Updates from .md**.
6. Upload your edited file. You'll see a preview of what will change before anything is applied.
7. Click **Confirm import** to apply.

Only the project's creator can import updates.

## File format

The importer accepts the same format the exporter produces:

```
# Project Name
## Incomplete
* Task one
* Task two

## Done
* Task three

## Archived
* Task four
```

It also accepts the simpler single-`#` form:

```
# Incomplete
- Task one
- Task two

# Done
- Task three
```

Both `-` and `*` are accepted as list markers. Section headings are case-insensitive (`incomplete`, `Incomplete`, and `INCOMPLETE` are all fine). Any number of leading `#` characters works for the section headings. A leading project-name heading (the first `#` line, if it doesn't match a section name) is silently ignored.

If the file contains any unrecognized headings, the importer will report all of them at once so you can fix everything in one pass before re-uploading.

## What changes (and what doesn't)

| Scenario | What happens |
|---|---|
| Task name in file, not in project | Created as a new task |
| Task name in file, already in project | Status updated if it moved to a different section; otherwise left alone |
| Task in project, not in file | Left completely unchanged |
| Incomplete tasks in file | Sort order reset to match file order; incomplete tasks not in the file are pushed to the bottom |
| Done / Archived tasks | Sort order is not changed |

## Limitations

**Exact name matching.** Tasks are matched by their exact name. If you rename a task in the file before uploading, the original task is left unchanged and a new task is created with the new name. There is no way for the importer to tell the difference between a rename and a brand-new task.

**No subtasks.** Indented items (subtasks) are ignored during import.

**No metadata.** Dates, tags, assignees, and other fields are not read from the file. The importer only handles task names and their section (status).

**Creators only.** Only the project's creator can run an import. Assignees who aren't the creator will not see the option.
