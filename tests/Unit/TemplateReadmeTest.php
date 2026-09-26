<?php

namespace Tests\Unit;

use App\Services\TemplateReadme;
use Tests\TestCase;

class TemplateReadmeTest extends TestCase
{
    private function data(array $overrides = []): array
    {
        return array_merge([
            'exported_at'      => '2026-09-26T12:00:00-07:00',
            'template_type'    => 'project',
            'project'          => ['name' => 'Kitchen Remodel', 'description' => 'Everything for the kitchen.'],
            'tasks'            => [
                ['name' => 'Buy tile', 'parent_index' => null],
                ['name' => 'Pick grout color', 'parent_index' => 0],
                ['name' => 'Call plumber', 'parent_index' => null],
            ],
            'tags'             => [['id' => 1, 'name' => 'urgent', 'color' => '#f00']],
            'task_attachments' => [['task_index' => 0, 'filename' => 'tile.jpg', 'path' => 'x']],
        ], $overrides);
    }

    public function test_includes_name_description_and_summary(): void
    {
        $md = TemplateReadme::build($this->data());

        $this->assertStringStartsWith('# Kitchen Remodel', $md);
        $this->assertStringContainsString('Everything for the kitchen.', $md);
        $this->assertStringContainsString('- Exported: Saturday, September 26, 2026', $md);
        $this->assertStringContainsString('- Tasks: 2 (plus 1 subtask)', $md);
        $this->assertStringContainsString('- Tags: urgent', $md);
        $this->assertStringContainsString('- Attachments: 1', $md);
    }

    public function test_task_list_nests_subtasks_under_parents(): void
    {
        $md = TemplateReadme::build($this->data());

        $this->assertStringContainsString("- Buy tile\n  - Pick grout color\n- Call plumber", $md);
    }

    public function test_handles_empty_project_without_description(): void
    {
        $md = TemplateReadme::build($this->data([
            'project' => ['name' => 'Empty', 'description' => null],
            'tasks' => [], 'tags' => [], 'task_attachments' => [],
        ]));

        $this->assertStringNotContainsString('## Description', $md);
        $this->assertStringNotContainsString('## Tasks', $md);
        $this->assertStringContainsString('- Tasks: 0', $md);
        $this->assertStringContainsString('- Tags: none', $md);
    }

    public function test_malformed_parent_cycle_does_not_loop(): void
    {
        $md = TemplateReadme::build($this->data([
            'tasks' => [
                ['name' => 'A', 'parent_index' => 1],
                ['name' => 'B', 'parent_index' => 0],
            ],
        ]));

        $this->assertStringContainsString("- A\n  - B", $md);
    }
}
