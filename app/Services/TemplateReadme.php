<?php

namespace App\Services;

use Carbon\Carbon;
use Illuminate\Support\Str;

/**
 * Builds the README.md dropped into every project template zip, so a zip
 * found in a Downloads folder months later explains itself. Built purely
 * from the same $data array that becomes template.json, so it can't
 * disagree with it. Import ignores the file entirely.
 */
class TemplateReadme
{
    public static function build(array $data): string
    {
        $name        = $data['project']['name'] ?? 'Untitled project';
        $description = trim((string) ($data['project']['description'] ?? ''));
        $tasks       = $data['tasks'] ?? [];
        $exportedAt  = isset($data['exported_at'])
            ? Carbon::parse($data['exported_at'])->timezone(config('app.timezone'))->format('l, F j, Y')
            : null;

        $subtaskCount = count(array_filter($tasks, fn ($t) => ($t['parent_index'] ?? null) !== null));
        $topCount     = count($tasks) - $subtaskCount;
        $attachments  = count($data['task_attachments'] ?? []);
        $tagNames     = array_column($data['tags'] ?? [], 'name');

        $lines   = [];
        $lines[] = '# ' . $name;
        $lines[] = '';
        $lines[] = 'This is a **Task Fiend project template**: a reusable project outline'
            . ' (tasks, subtasks, tags and attachments, without dates or assignees).';
        $lines[] = '';

        if ($description !== '') {
            $lines[] = '## Description';
            $lines[] = '';
            $lines[] = $description;
            $lines[] = '';
        }

        $lines[] = '## At a Glance';
        $lines[] = '';
        if ($exportedAt) {
            $lines[] = '- Exported: ' . $exportedAt;
        }
        $lines[] = '- Tasks: ' . $topCount . ($subtaskCount ? ' (plus ' . $subtaskCount . ' ' . Str::plural('subtask', $subtaskCount) . ')' : '');
        $lines[] = '- Tags: ' . ($tagNames ? implode(', ', $tagNames) : 'none');
        $lines[] = '- Attachments: ' . $attachments;
        $lines[] = '';

        if ($tasks) {
            $lines[] = '## Tasks';
            $lines[] = '';
            array_push($lines, ...self::taskTree($tasks));
            $lines[] = '';
        }

        $lines[] = '## How to Use It';
        $lines[] = '';
        $lines[] = 'Upload this zip as-is (don\'t unzip it) in Task Fiend:';
        $lines[] = '';
        $lines[] = '- **Templates → Import Template from Zip** adds it to your template list, so you can create projects from it any time.';
        $lines[] = '- **Projects → Import Template File** creates a new project from it straight away.';
        $lines[] = '';
        $lines[] = 'The app reads `template.json` and the `attachments/` folder. This README is for people and is ignored on import.';
        $lines[] = '';

        return implode("\n", $lines);
    }

    /** Nested bullet list of task names, subtasks indented under their parent. */
    private static function taskTree(array $tasks): array
    {
        $children = [];
        $roots    = [];
        foreach ($tasks as $i => $task) {
            $parent = $task['parent_index'] ?? null;
            if ($parent !== null && isset($tasks[$parent]) && $parent !== $i) {
                $children[$parent][] = $i;
            } else {
                $roots[] = $i;
            }
        }

        $out  = [];
        $seen = [];
        $walk = function (int $i, int $depth) use (&$walk, &$out, &$seen, $tasks, $children) {
            if (isset($seen[$i])) {
                return; // guard against malformed parent cycles
            }
            $seen[$i] = true;
            $name  = trim(preg_replace('/\s+/', ' ', (string) ($tasks[$i]['name'] ?? '')));
            $out[] = str_repeat('  ', $depth) . '- ' . ($name !== '' ? $name : '(untitled)');
            foreach ($children[$i] ?? [] as $child) {
                $walk($child, $depth + 1);
            }
        };
        foreach ($roots as $i) {
            $walk($i, 0);
        }
        // Anything unreached sits in a parent cycle (malformed data); list it flat.
        foreach (array_keys($tasks) as $i) {
            $walk($i, 0);
        }

        return $out;
    }
}
