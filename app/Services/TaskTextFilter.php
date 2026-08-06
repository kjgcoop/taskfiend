<?php

namespace App\Services;

use Illuminate\Support\Collection;

/**
 * Server-side port of the on-page task list filter (task-list.blade.php's
 * Alpine `filterTasks()`). The on-page filter is client-side only — it hides
 * DOM rows and never touches the URL — so anything that needs to export
 * "whatever's currently filtered" (see DashboardController::exportDayPdf())
 * has to reapply the same query text against the server-side task list
 * itself. Keep the tokenizing and matching rules here in sync with the JS.
 */
class TaskTextFilter
{
    /**
     * @param  Collection<int, \App\Models\Task>  $tasks  Must have project, tags, assignees loaded.
     */
    public static function apply(Collection $tasks, ?string $query): Collection
    {
        $raw = strtolower(trim((string) $query));
        if ($raw === '') {
            return $tasks->values();
        }

        $tokens = self::tokenize($raw);

        return $tasks->filter(fn ($task) => self::matches($task, $tokens))->values();
    }

    private static function matches(\App\Models\Task $task, array $t): bool
    {
        $taskName = strtolower($task->name);
        $project  = self::clean($task->project?->name ?? '');
        $tags     = $task->tags->pluck('tag_name')->map(fn ($tag) => self::clean($tag))->all();
        $location = strtolower($task->location ?? '');
        $assignees = $task->assignees->map(fn ($a) => preg_replace('/[^a-z0-9]/', '', strtolower($a->name)))->all();

        foreach ($t['name'] as $f)     if (!str_contains($taskName, $f)) return false;
        foreach ($t['project'] as $f)  if (!str_contains($project, str_replace('-', ' ', $f))) return false;
        foreach ($t['tag'] as $f)      if (!self::anyContains($tags, str_replace('-', ' ', $f))) return false;
        foreach ($t['location'] as $f) if (!str_contains($location, str_replace('-', ' ', $f))) return false;
        foreach ($t['user'] as $f)     if (!self::anyContains($assignees, $f)) return false;

        foreach ($t['notName'] as $f)     if (str_contains($taskName, $f)) return false;
        foreach ($t['notProject'] as $f)  if (str_contains($project, str_replace('-', ' ', $f))) return false;
        foreach ($t['notTag'] as $f)      if (self::anyContains($tags, str_replace('-', ' ', $f))) return false;
        foreach ($t['notLocation'] as $f) if (str_contains($location, str_replace('-', ' ', $f))) return false;
        foreach ($t['notUser'] as $f)     if (self::anyContains($assignees, $f)) return false;

        return true;
    }

    private static function anyContains(array $haystacks, string $needle): bool
    {
        foreach ($haystacks as $h) {
            if (str_contains($h, $needle)) return true;
        }
        return false;
    }

    private static function clean(string $value): string
    {
        return preg_replace('/[^a-z0-9 ]/', '', strtolower($value));
    }

    /**
     * Mirrors the JS tokenizer: not:"quoted", "quoted", and bare tokens,
     * with #project / @tag / +location / &user prefixes (each optionally
     * preceded by "not:").
     */
    private static function tokenize(string $raw): array
    {
        $t = [
            'name' => [], 'project' => [], 'tag' => [], 'location' => [], 'user' => [],
            'notName' => [], 'notProject' => [], 'notTag' => [], 'notLocation' => [], 'notUser' => [],
        ];

        preg_match_all('/not:"([^"]+)"|"([^"]+)"|(\S+)/', $raw, $matches, PREG_SET_ORDER);

        foreach ($matches as $m) {
            if (($m[1] ?? '') !== '') {
                $t['notName'][] = $m[1];
                continue;
            }
            if (($m[2] ?? '') !== '') {
                $t['name'][] = $m[2];
                continue;
            }

            $token = $m[3] ?? '';
            $isNot = str_starts_with($token, 'not:');
            $val   = $isNot ? substr($token, 4) : $token;
            if ($val === '') continue;

            if (str_starts_with($val, '#') && strlen($val) > 1) {
                $t[$isNot ? 'notProject' : 'project'][] = substr($val, 1);
            } elseif (str_starts_with($val, '@') && strlen($val) > 1) {
                $t[$isNot ? 'notTag' : 'tag'][] = substr($val, 1);
            } elseif (str_starts_with($val, '+') && strlen($val) > 1) {
                $t[$isNot ? 'notLocation' : 'location'][] = substr($val, 1);
            } elseif (str_starts_with($val, '&') && strlen($val) > 1) {
                $t[$isNot ? 'notUser' : 'user'][] = substr($val, 1);
            } else {
                $t[$isNot ? 'notName' : 'name'][] = $val;
            }
        }

        return $t;
    }
}
