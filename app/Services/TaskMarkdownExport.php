<?php

namespace App\Services;

use Illuminate\Http\Request;
use Illuminate\Support\Collection;

/**
 * The single implementation of the "Export MD" downloads (day, overdue, project, tag, search).
 *
 * Two halves, both shared so the exports can't drift apart:
 *
 *  - visibleIds(): the page's live snapshot of which task rows are on screen, sent as 'ids[]' (see
 *    `window.collectVisibleFilterableTaskIds()` and the `visibleIdsExport` Alpine component).
 *    Controllers apply it as a whereIn() on top of their own authorized query, so an id can only
 *    narrow the result, never widen it.
 *  - response(): renders `# Title`, then one `## Heading` section per non-empty group of
 *    `* task name` lines (a task's already-loaded `children` are indented beneath it).
 */
class TaskMarkdownExport
{
    /**
     * The visible task ids the client sent, or null when no 'ids' param was sent at all (a
     * bookmarked URL, or a page with no active filter), meaning "don't narrow". An empty 'ids'
     * means the page showed nothing, so the export is empty too.
     *
     * @return int[]|null
     */
    public static function visibleIds(Request $request): ?array
    {
        if (!$request->has('ids')) {
            return null;
        }

        return array_values(array_map(
            'intval',
            array_filter((array) $request->input('ids'), fn ($id) => $id !== '' && $id !== null)
        ));
    }

    /**
     * @param  array<string, Collection>  $groups  section heading => tasks; a null heading renders
     *                                             the tasks directly under the title
     */
    public static function response(string $title, array $groups, string $filename, ?string $description = null)
    {
        $lines = ['# ' . $title];

        if ($description) {
            $lines[] = '';
            $lines[] = $description;
        }

        foreach ($groups as $heading => $tasks) {
            if ($tasks->isEmpty()) {
                continue;
            }

            if (!is_int($heading)) {
                $lines[] = '';
                $lines[] = '## ' . $heading;
            }

            foreach ($tasks as $task) {
                $lines[] = '* ' . $task->name;
                if ($task->relationLoaded('children')) {
                    foreach ($task->children as $child) {
                        $lines[] = '    * ' . $child->name;
                    }
                }
            }
        }

        return response(implode("\n", $lines) . "\n", 200, [
            'Content-Type'        => 'text/markdown; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ]);
    }
}
