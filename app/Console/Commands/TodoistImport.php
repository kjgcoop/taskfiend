<?php

namespace App\Console\Commands;

use App\Models\ApiKey;
use App\Models\Assignment;
use App\Models\Comment;
use App\Models\Project;
use App\Models\Tag;
use App\Models\Task;
use App\Models\TaskAttachment;
use App\Models\User;
use App\Services\DateParser;
use Carbon\Carbon;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class TodoistImport extends Command
{
    protected $signature = 'todoist:import
        {--taskfiend-api-key= : Task Fiend API key (tfk_...)}
        {--todoist-api-key= : Todoist API key (overrides TODOIST_KEY env var)}';

    protected $description = 'Import data from Todoist into Task Fiend';

    private Client $todoistClient;
    private User $user;
    private DateParser $dateParser;

    // Statistics tracking
    private int $projectsImported = 0;
    private int $projectsSkipped = 0;
    private int $tasksImported = 0;
    private int $tasksSkipped = 0;
    private int $tagsImported = 0;
    private int $commentsImported = 0;
    private int $attachmentsImported = 0;
    private int $errors = 0;

    // Mapping Todoist IDs to Task Fiend IDs
    private array $projectIdMap = [];
    private array $taskIdMap = [];
    private array $tagIdMap = [];
    private array $tagNameMap = []; // Map label names to Task Fiend tag IDs

    public function handle()
    {
        // Validate Task Fiend API key
        $apiKey = $this->option('taskfiend-api-key');
        if (!$apiKey) {
            $this->error('Missing required --taskfiend-api-key option');
            $this->info('Usage: php artisan todoist:import --taskfiend-api-key=tfk_xxxxx --todoist-api-key=YOUR_TODOIST_KEY');
            return 1;
        }

        // Authenticate user
        $user = $this->authenticateUser($apiKey);
        if (!$user) {
            return 1;
        }
        $this->user = $user;

        // Check for Todoist API key (CLI argument takes precedence over env var)
        $todoistKey = $this->option('todoist-api-key') ?: env('TODOIST_KEY');
        if (!$todoistKey) {
            $this->error('Todoist API key not provided. Use --todoist-api-key or set TODOIST_KEY in .env');
            return 1;
        }

        // Initialize HTTP clients
        $this->todoistClient = new Client([
            'base_uri' => 'https://api.todoist.com/api/v1/',
            'headers' => [
                'Authorization' => 'Bearer ' . $todoistKey,
                'Content-Type' => 'application/json',
            ],
        ]);

        // Initialize DateParser
        $this->dateParser = new DateParser();

        $this->info('Starting Todoist import for user: ' . $this->user->email);
        $this->info('');

        try {
            // Import in order: labels (tags), projects, tasks, comments
            $this->importLabels();
            $this->importProjects();
            $this->importTasks();
            $this->importComments();

            // Print summary
            $this->printSummary();

            return 0;
        } catch (\Exception $e) {
            $this->error('Import failed with exception: ' . $e->getMessage());
            Log::error('Todoist import failed', [
                'user_id' => $this->user->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            return 1;
        }
    }

    private function authenticateUser(string $apiKey): ?User
    {
        // Find all active API keys and check each one with Hash::check
        // (since we can't hash and compare directly with bcrypt)
        $apiKeys = ApiKey::whereNull('invalidated_at')->get();

        $matchedApiKey = null;
        foreach ($apiKeys as $apiKeyModel) {
            if (Hash::check($apiKey, $apiKeyModel->key_hash)) {
                $matchedApiKey = $apiKeyModel;
                break;
            }
        }

        if (!$matchedApiKey) {
            $this->error('Invalid or inactive API key');
            return null;
        }

        $user = User::find($matchedApiKey->user_id);
        if (!$user || $user->email_enabled_at !== null) {
            $this->error('User not found or disabled');
            return null;
        }

        return $user;
    }

    /**
     * Fetch all results from a paginated Todoist API v1 endpoint.
     *
     * API v1 returns { "results": [...], "next_cursor": "string"|null }
     * and supports cursor/limit query parameters.
     */
    private function fetchAllPaginated(string $endpoint, array $query = []): array
    {
        $allResults = [];
        $cursor = null;

        do {
            $params = array_merge($query, ['limit' => 200]);
            if ($cursor) {
                $params['cursor'] = $cursor;
            }

            $response = $this->todoistClient->get($endpoint, [
                'query' => $params,
            ]);

            $body = json_decode($response->getBody()->getContents(), true);

            $results = $body['results'] ?? [];
            $allResults = array_merge($allResults, $results);

            $cursor = $body['next_cursor'] ?? null;
        } while ($cursor);

        return $allResults;
    }

    private function importLabels(): void
    {
        $this->info('Fetching labels from Todoist...');

        try {
            $labels = $this->fetchAllPaginated('labels');

            $this->info('Found ' . count($labels) . ' labels');

            foreach ($labels as $index => $todoistLabel) {
                $this->importLabel($todoistLabel, $index + 1, count($labels));
            }

            $this->info('');
        } catch (GuzzleException $e) {
            $this->error('Failed to fetch labels: ' . $e->getMessage());
            Log::error('Todoist API error fetching labels', ['error' => $e->getMessage()]);
            $this->errors++;
        }
    }

    private function importLabel(array $todoistLabel, int $current, int $total): void
    {
        $name = $todoistLabel['name'];

        // Todoist uses color names like 'berry_red', 'red', 'orange', etc.
        // Convert to hex color if possible, otherwise use default gray
        $color = $this->convertTodoistColor($todoistLabel['color'] ?? null);

        // Check if tag already exists (tags are global)
        $existingTag = Tag::where('tag_name', $name)->first();

        if ($existingTag) {
            // Tag already exists, reuse it
            $this->tagIdMap[$todoistLabel['id']] = $existingTag->id;
            $this->tagNameMap[strtolower($name)] = $existingTag->id;
            return;
        }

        // Create new tag
        try {
            $tag = Tag::create([
                'tag_name' => $name,
                'color' => $color,
            ]);

            $this->tagIdMap[$todoistLabel['id']] = $tag->id;
            $this->tagNameMap[strtolower($name)] = $tag->id;
            $this->tagsImported++;

            $this->info("Imported label '{$name}' ({$current}/{$total} labels)");
        } catch (\Exception $e) {
            $this->error("Failed to import label '{$name}': " . $e->getMessage());
            Log::error('Failed to import Todoist label', [
                'label_name' => $name,
                'error' => $e->getMessage(),
            ]);
            $this->errors++;
        }
    }

    private function importProjects(): void
    {
        $this->info('Fetching projects from Todoist...');

        try {
            $projects = $this->fetchAllPaginated('projects');

            $this->info('Found ' . count($projects) . ' projects');

            foreach ($projects as $index => $todoistProject) {
                $this->importProject($todoistProject, $index + 1, count($projects));
            }

            $this->info('');
        } catch (GuzzleException $e) {
            $this->error('Failed to fetch projects: ' . $e->getMessage());
            Log::error('Todoist API error fetching projects', ['error' => $e->getMessage()]);
            $this->errors++;
        }
    }

    private function importProject(array $todoistProject, int $current, int $total): void
    {
        $name = $todoistProject['name'];
        $isTodoistInbox = !empty($todoistProject['inbox_project']);

        // If this is the Todoist Inbox, map it to the user's Task Fiend inbox project
        if ($isTodoistInbox) {
            $inboxProject = Project::where('user_id', $this->user->id)
                ->where('is_inbox', true)
                ->first();

            if ($inboxProject) {
                $this->projectIdMap[$todoistProject['id']] = $inboxProject->id;
                $this->info("Mapped Todoist Inbox to existing inbox project '{$inboxProject->name}' ({$current}/{$total} projects)");
                return;
            }

            // No inbox project exists yet — create one
            try {
                $inboxProject = Project::create([
                    'name' => $this->user->name . "'s Inbox",
                    'description' => 'Personal inbox for quick task capture',
                    'user_id' => $this->user->id,
                    'status' => 'incomplete',
                    'is_inbox' => true,
                ]);

                $this->projectIdMap[$todoistProject['id']] = $inboxProject->id;
                $this->projectsImported++;
                $this->info("Created inbox project '{$inboxProject->name}' for Todoist Inbox ({$current}/{$total} projects)");
                return;
            } catch (\Exception $e) {
                $this->error("Failed to create inbox project: " . $e->getMessage());
                $this->errors++;
                return;
            }
        }

        // Check for duplicate project (by name, global uniqueness)
        $existingProject = Project::where('name', $name)
            ->where('user_id', $this->user->id)
            ->first();

        if ($existingProject) {
            $this->projectsSkipped++;
            $this->projectIdMap[$todoistProject['id']] = $existingProject->id;
            Log::info('Skipped duplicate project', ['project_name' => $name]);
            return;
        }

        // Create new project
        try {
            $project = Project::create([
                'name' => $name,
                'description' => null,
                'user_id' => $this->user->id,
                'status' => 'incomplete',
            ]);

            $this->projectIdMap[$todoistProject['id']] = $project->id;
            $this->projectsImported++;

            $this->info("Imported project '{$name}' ({$current}/{$total} projects)");
        } catch (\Exception $e) {
            $this->error("Failed to import project '{$name}': " . $e->getMessage());
            Log::error('Failed to import Todoist project', [
                'project_name' => $name,
                'error' => $e->getMessage(),
            ]);
            $this->errors++;
        }
    }

    private function importTasks(): void
    {
        $this->info('Fetching tasks from Todoist...');

        try {
            $tasks = $this->fetchAllPaginated('tasks');

            $this->info('Found ' . count($tasks) . ' tasks');

            // First pass: import all parent tasks (those without parent_id)
            $parentTasks = array_filter($tasks, fn($t) => empty($t['parent_id']));
            $this->info('Importing ' . count($parentTasks) . ' parent tasks...');

            foreach ($parentTasks as $index => $todoistTask) {
                $this->importTask($todoistTask, $index + 1, count($parentTasks));
            }

            // Second pass: import all subtasks (those with parent_id)
            $subtasks = array_filter($tasks, fn($t) => !empty($t['parent_id']));
            if (count($subtasks) > 0) {
                $this->info('Importing ' . count($subtasks) . ' subtasks...');

                foreach ($subtasks as $index => $todoistTask) {
                    $this->importTask($todoistTask, $index + 1, count($subtasks));
                }
            }

            $this->info('');
        } catch (GuzzleException $e) {
            $this->error('Failed to fetch tasks: ' . $e->getMessage());
            Log::error('Todoist API error fetching tasks', ['error' => $e->getMessage()]);
            $this->errors++;
        }
    }

    private function importTask(array $todoistTask, int $current, int $total): void
    {
        $name = $todoistTask['content'];
        $projectId = $todoistTask['project_id'] ?? null;

        // Skip if project wasn't imported
        if ($projectId && !isset($this->projectIdMap[$projectId])) {
            $this->tasksSkipped++;
            Log::info('Skipped task - project not imported', [
                'task_name' => $name,
                'todoist_project_id' => $projectId,
            ]);
            return;
        }

        $taskFiendProjectId = $projectId ? $this->projectIdMap[$projectId] : null;

        // Check for duplicate task (by name within same project)
        $duplicateQuery = Task::where('name', $name)
            ->where('creator_id', $this->user->id);

        if ($taskFiendProjectId) {
            $duplicateQuery->where('project_id', $taskFiendProjectId);
        } else {
            $duplicateQuery->whereNull('project_id');
        }

        $existingTask = $duplicateQuery->first();

        if ($existingTask) {
            $this->tasksSkipped++;
            $this->taskIdMap[$todoistTask['id']] = $existingTask->id;
            Log::info('Skipped duplicate task', [
                'task_name' => $name,
                'project_id' => $taskFiendProjectId,
            ]);
            return;
        }

        // Parse due date and recurrence
        $date = null;
        $time = null;
        $recurrencePattern = null;
        $recurrenceFloating = false;

        if (!empty($todoistTask['due'])) {
            $due = $todoistTask['due'];

            // Parse date — prefer the datetime field (has time info), fall back to date field
            // API v1 due.date is YYYY-MM-DD, due.datetime is full ISO 8601
            if (!empty($due['datetime'])) {
                try {
                    $parsed = Carbon::parse($due['datetime']);
                    $date = $parsed->format('Y-m-d');
                    $time = $parsed->format('H:i:s');
                } catch (\Exception $e) {
                    Log::warning('Failed to parse due.datetime', [
                        'task_name' => $name,
                        'datetime' => $due['datetime'],
                    ]);
                }
            } elseif (!empty($due['date'])) {
                try {
                    $parsed = Carbon::parse($due['date']);
                    $date = $parsed->format('Y-m-d');
                } catch (\Exception $e) {
                    Log::warning('Failed to parse due.date', [
                        'task_name' => $name,
                        'date' => $due['date'],
                    ]);
                }
            }

            // Parse recurrence pattern
            if (!empty($due['is_recurring']) && !empty($due['string'])) {
                // Detect Todoist floating recurrence ("every!" = relative to completion date)
                $recurrenceFloating = (bool) preg_match('/\bevery!\s*/i', $due['string']);
                $recurrencePattern = $this->convertTodoistRecurrence($due['string'], $name);
            }
        }

        // Get parent_id if this is a subtask
        $parentId = null;
        if (!empty($todoistTask['parent_id'])) {
            $parentId = $this->taskIdMap[$todoistTask['parent_id']] ?? null;

            if (!$parentId) {
                // Parent not found (likely completed in Todoist)
                // Import as top-level task instead of skipping
                Log::info('Importing orphaned subtask as top-level task', [
                    'task_name' => $name,
                    'todoist_parent_id' => $todoistTask['parent_id'],
                ]);
                $parentId = null; // Import without parent
            }
        }

        // Create task
        try {
            $task = Task::create([
                'name' => $name,
                'description' => $todoistTask['description'] ?? null,
                'status' => 'incomplete',
                'creator_id' => $this->user->id,
                'project_id' => $taskFiendProjectId,
                'parent_id' => $parentId,
                'date' => $date,
                'time' => $time,
                'recurrence_pattern' => $recurrencePattern,
                'recurrence_floating' => $recurrenceFloating,
            ]);

            // Create assignment
            Assignment::create([
                'task_id' => $task->id,
                'assignee_id' => $this->user->id,
                'assigned_by_id' => $this->user->id,
            ]);

            // Attach labels/tags
            if (!empty($todoistTask['labels'])) {
                foreach ($todoistTask['labels'] as $labelName) {
                    // Look up tag ID from our name map (case-insensitive)
                    $tagId = $this->tagNameMap[strtolower($labelName)] ?? null;

                    if ($tagId) {
                        $task->tags()->attach($tagId);
                    } else {
                        Log::warning('Task label not found in imported tags', [
                            'task_name' => $name,
                            'label_name' => $labelName,
                            'available_labels' => array_keys($this->tagNameMap),
                        ]);
                    }
                }
            }

            // Log task creation
            $task->changeLogs()->create([
                'date' => now(),
                'user_id' => $this->user->id,
                'entity_type' => 'tasks',
                'entity_id' => $task->id,
                'description' => 'created task via Todoist import',
            ]);

            $this->taskIdMap[$todoistTask['id']] = $task->id;

            // Import task attachments (fetch separately from Todoist)
            $this->importTaskAttachments($todoistTask['id'], $task);

            $this->tasksImported++;

            $taskType = $parentId ? 'subtask' : 'task';
            $this->info("Imported {$taskType} '{$name}' ({$current}/{$total})");
        } catch (\Exception $e) {
            $this->error("Failed to import task '{$name}': " . $e->getMessage());
            Log::error('Failed to import Todoist task', [
                'task_name' => $name,
                'error' => $e->getMessage(),
            ]);
            $this->errors++;
        }
    }

    private function convertTodoistRecurrence(string $todoistRecurrence, string $taskName): ?string
    {
        // Try to parse Todoist recurrence string into Task Fiend format
        // Todoist uses natural language like "every day", "every Monday", "every 2 weeks"

        // Strip Todoist's strict recurrence marker "!" (e.g., "every! month" → "every month")
        $cleaned = preg_replace('/\bevery!\s*/i', 'every ', $todoistRecurrence);
        $cleaned = trim($cleaned);

        // Use DateParser to attempt conversion
        try {
            $parsed = $this->dateParser->parseTaskInput($cleaned);

            if (!empty($parsed['recurrence_pattern'])) {
                return $parsed['recurrence_pattern'];
            }
        } catch (\Exception $e) {
            // DateParser couldn't parse it
        }

        // If DateParser can't handle it, log warning and return null
        Log::warning('Unparseable Todoist recurrence pattern', [
            'task_name' => $taskName,
            'todoist_recurrence' => $todoistRecurrence,
        ]);

        return null;
    }

    private function importComments(): void
    {
        if (empty($this->taskIdMap)) {
            return;
        }

        $this->info('Fetching comments from Todoist...');

        $totalComments = 0;

        // Fetch comments for each task
        foreach ($this->taskIdMap as $todoistTaskId => $taskFiendTaskId) {
            try {
                $comments = $this->fetchAllPaginated('comments', ['task_id' => $todoistTaskId]);

                foreach ($comments as $todoistComment) {
                    $this->importComment($todoistComment, $taskFiendTaskId);
                    $totalComments++;
                }
            } catch (GuzzleException $e) {
                $this->error('Failed to fetch comments for task ' . $todoistTaskId . ': ' . $e->getMessage());
                Log::error('Todoist API error fetching comments', [
                    'task_id' => $todoistTaskId,
                    'error' => $e->getMessage(),
                ]);
                $this->errors++;
            }
        }

        if ($totalComments > 0) {
            $this->info("Imported {$totalComments} comments");
            $this->info('');
        }
    }

    private function importComment(array $todoistComment, int $taskFiendTaskId): void
    {
        try {
            $commentData = [
                'task_id' => $taskFiendTaskId,
                'user_id' => $this->user->id,
                'comment' => $todoistComment['content'],
                'created_at' => $todoistComment['posted_at'] ?? now(),
            ];

            // Handle attachment if present
            if (!empty($todoistComment['attachment'])) {
                $attachment = $todoistComment['attachment'];
                $url = $attachment['url'] ?? $attachment['file_url'] ?? null;
                $filename = $attachment['file_name'] ?? $attachment['title'] ?? 'attachment';

                if ($url) {
                    try {
                        $this->line("  Downloading comment attachment: {$filename}");

                        $fileContents = file_get_contents($url);
                        if ($fileContents !== false) {
                            // Store file
                            $path = 'comment_attachments/' . uniqid() . '_' . $filename;
                            Storage::disk('private')->put($path, $fileContents);

                            // Add attachment fields to comment
                            $commentData['file_path'] = $path;
                            $commentData['original_filename'] = $filename;
                            $commentData['mime_type'] = $attachment['file_type'] ?? mime_content_type($path);
                            $commentData['file_size'] = strlen($fileContents);

                            $this->attachmentsImported++;
                        }
                    } catch (\Exception $e) {
                        $this->error("  Failed to download comment attachment '{$filename}': " . $e->getMessage());
                        Log::warning('Failed to import comment attachment', [
                            'filename' => $filename,
                            'url' => $url,
                            'error' => $e->getMessage(),
                        ]);
                    }
                }
            }

            Comment::create($commentData);
            $this->commentsImported++;
        } catch (\Exception $e) {
            $this->error('Failed to import comment: ' . $e->getMessage());
            Log::error('Failed to import Todoist comment', [
                'task_id' => $taskFiendTaskId,
                'error' => $e->getMessage(),
            ]);
            $this->errors++;
        }
    }

    private function importTaskAttachments(string $todoistTaskId, Task $task): void
    {
        // Todoist API doesn't have a direct endpoint for task attachments
        // Attachments in Todoist are typically added via comments
        // So we'll skip this - attachments will be imported via comments
    }

    private function convertTodoistColor(?string $todoistColor): string
    {
        // Map Todoist color names to hex values
        // Todoist uses names like 'berry_red', 'red', 'orange', etc.
        $colorMap = [
            'berry_red' => '#b8256f',
            'red' => '#db4035',
            'orange' => '#ff9933',
            'yellow' => '#fad000',
            'olive_green' => '#afb83b',
            'lime_green' => '#7ecc49',
            'green' => '#299438',
            'mint_green' => '#6accbc',
            'teal' => '#158fad',
            'sky_blue' => '#14aaf5',
            'light_blue' => '#96c3eb',
            'blue' => '#4073ff',
            'grape' => '#884dff',
            'violet' => '#af38eb',
            'lavender' => '#eb96eb',
            'magenta' => '#e05194',
            'salmon' => '#ff8d85',
            'charcoal' => '#808080',
            'grey' => '#b8b8b8',
            'taupe' => '#ccac93',
        ];

        if (!$todoistColor) {
            return '#808080'; // Default gray
        }

        return $colorMap[$todoistColor] ?? '#808080';
    }

    private function printSummary(): void
    {
        $this->info('');
        $this->info('=== Import Summary ===');
        $this->info("Projects imported: {$this->projectsImported}");
        $this->info("Projects skipped (duplicates): {$this->projectsSkipped}");
        $this->info("Tags imported: {$this->tagsImported}");
        $this->info("Tasks imported: {$this->tasksImported}");
        $this->info("Tasks skipped (duplicates): {$this->tasksSkipped}");
        $this->info("Comments imported: {$this->commentsImported}");
        $this->info("Attachments imported: {$this->attachmentsImported}");
        $this->info("Errors encountered: {$this->errors}");
        $this->info('');

        Log::info('Todoist import completed', [
            'user_id' => $this->user->id,
            'projects_imported' => $this->projectsImported,
            'projects_skipped' => $this->projectsSkipped,
            'tags_imported' => $this->tagsImported,
            'tasks_imported' => $this->tasksImported,
            'tasks_skipped' => $this->tasksSkipped,
            'comments_imported' => $this->commentsImported,
            'attachments_imported' => $this->attachmentsImported,
            'errors' => $this->errors,
        ]);

        if ($this->errors > 0) {
            $this->warn('Import completed with errors. Check logs for details.');
        } else {
            $this->info('Import completed successfully!');
        }
    }
}
