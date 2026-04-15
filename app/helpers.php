<?php

if (! function_exists('render_title')) {
    /**
     * Render a task title with inline code spans (`backtick` → <code>).
     * HTML-escapes first so no raw HTML can slip through the markdown patterns.
     * Returns an HTML string safe for use with {!! !!}.
     */
    function render_title(string $name): string
    {
        $safe = e($name);
        $safe = preg_replace('/`([^`\n]+)`/', '<code>$1</code>', $safe);
        return $safe;
    }
}

if (! function_exists('render_body')) {
    /**
     * Render a body text field (description, comment) to safe HTML.
     *
     * Resolves inline reference syntax before passing through GitHub-flavoured
     * Markdown:
     *   [task:5]              → link to task 5 (name; ✓ if done; strikethrough if archived)
     *   [project:3]           → link to project 3 (name; strikethrough if archived)
     *   [tag:2]               → link to tag 2 (tag_name)
     *   [date:2026-04-15]     → link to the day view for that date
     *   [location:Home Office]→ link to search filtered by that location (trimmed)
     *
     * Unresolvable or access-denied references are left as raw text.
     * Returns an HTML string safe for use with {!! !!}.
     */
    function render_body(?string $text): string
    {
        if (empty($text)) return '';

        $userId = auth()->id();

        // ── Collect all reference IDs so we can batch-load ──────────────────
        preg_match_all('/\[task:(\d+)\]/',    $text, $tm);
        preg_match_all('/\[project:(\d+)\]/', $text, $pm);
        preg_match_all('/\[tag:(\d+)\]/',     $text, $gm);

        $taskIds    = array_unique($tm[1]);
        $projectIds = array_unique($pm[1]);
        $tagIds     = array_unique($gm[1]);

        // ── Batch-load entities with relationships needed for access checks ──
        $tasks    = $taskIds    ? \App\Models\Task::with('assignees')
                                    ->whereIn('id', $taskIds)->get()->keyBy('id')
                                : collect();
        $projects = $projectIds ? \App\Models\Project::with('assignees')
                                    ->whereIn('id', $projectIds)->get()->keyBy('id')
                                : collect();
        $tags     = $tagIds     ? \App\Models\Tag::whereIn('id', $tagIds)->get()->keyBy('id')
                                : collect();

        // ── Helper: escape chars that would break GFM link syntax ───────────
        $mdText = function (string $s): string {
            return preg_replace('/([\\\\\\[\\]()~])/', '\\\\$1', $s);
        };

        // ── [task:N] ─────────────────────────────────────────────────────────
        $text = preg_replace_callback('/\[task:(\d+)\]/', function ($m) use ($tasks, $userId, $mdText) {
            /** @var \App\Models\Task|null $task */
            $task = $tasks->get((int) $m[1]);
            if (! $task) return $m[0];

            if ($userId) {
                $isCreator  = $task->creator_id === $userId;
                $isAssignee = $task->assignees->contains('id', $userId);
                if (! $isCreator && ! $isAssignee) return $m[0];
            }

            $name = $mdText($task->name);
            $url  = route('tasks.show', $task);

            if ($task->status === 'archived') {
                return "~~[{$name}]({$url})~~";
            }
            $suffix = $task->status === 'done' ? ' ✓' : '';
            return "[{$name}{$suffix}]({$url})";
        }, $text);

        // ── [project:N] ──────────────────────────────────────────────────────
        $text = preg_replace_callback('/\[project:(\d+)\]/', function ($m) use ($projects, $userId, $mdText) {
            /** @var \App\Models\Project|null $project */
            $project = $projects->get((int) $m[1]);
            if (! $project) return $m[0];

            if ($userId) {
                $isCreator  = $project->user_id === $userId;
                $isAssignee = $project->assignees->contains('id', $userId);
                if (! $isCreator && ! $isAssignee) return $m[0];
            }

            $name = $mdText($project->name);
            $url  = route('projects.show', $project);

            if ($project->status === 'archived') {
                return "~~[{$name}]({$url})~~";
            }
            $suffix = $project->status === 'done' ? ' ✓' : '';
            return "[{$name}{$suffix}]({$url})";
        }, $text);

        // ── [tag:N] ──────────────────────────────────────────────────────────
        $text = preg_replace_callback('/\[tag:(\d+)\]/', function ($m) use ($tags, $mdText) {
            /** @var \App\Models\Tag|null $tag */
            $tag = $tags->get((int) $m[1]);
            if (! $tag) return $m[0];

            $name = $mdText($tag->tag_name);
            $url  = route('tags.show', $tag);
            return "[{$name}]({$url})";
        }, $text);

        // ── [date:YYYY-MM-DD] ────────────────────────────────────────────────
        $text = preg_replace_callback('/\[date:(\d{4}-\d{2}-\d{2})\]/', function ($m) {
            try {
                $carbon    = \Carbon\Carbon::parse($m[1]);
                $formatted = $carbon->format('l, F j, Y');
                $url       = route('day') . '?date=' . $m[1];
                return "[{$formatted}]({$url})";
            } catch (\Exception $e) {
                return $m[0];
            }
        }, $text);

        // ── [location:X] (value trimmed; spaces before ] are fine) ──────────
        $text = preg_replace_callback('/\[location:([^\]]+)\]/', function ($m) {
            $location = trim($m[1]);
            if ($location === '') return $m[0];
            $url = route('search') . '?' . http_build_query(['location' => $location]);
            return "[{$location}]({$url})";
        }, $text);

        // ── Render GFM markdown (escapes raw HTML, disallows unsafe links) ───
        $html = \Illuminate\Support\Str::markdown($text, [
            'html_input'         => 'escape',
            'allow_unsafe_links' => false,
        ]);

        // Open all links in a new tab
        return preg_replace('/<a\s/', '<a target="_blank" rel="noopener noreferrer" ', $html);
    }
}
