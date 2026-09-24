<?php

namespace Tests\Unit;

use App\Models\Task;
use App\Services\DayPdfExporter;
use Carbon\Carbon;
use Tests\TestCase;

/**
 * Unit tests for App\Services\DayPdfExporter.
 *
 * No RefreshDatabase: DayPdfExporter only reads plain attributes
 * (status/time/name) off the Task objects it's handed, so unsaved in-memory
 * Task instances are enough here.
 */
class DayPdfExporterTest extends TestCase
{
    public function test_build_returns_a_structurally_valid_pdf(): void
    {
        $tasks = collect([
            new Task(['name' => 'Buy milk', 'status' => 'incomplete']),
            new Task(['name' => 'Call dentist', 'status' => 'incomplete']),
        ]);

        $pdf = DayPdfExporter::build(Carbon::parse('2026-09-23'), $tasks, null, 'date', false);

        $this->assertStringStartsWith('%PDF-1.4', $pdf);
        $this->assertStringContainsString('%%EOF', $pdf);
        $this->assertMatchesRegularExpression('/\/Type\s*\/Page\b/', $pdf);
    }

    /**
     * The export must lay tasks out in a single column: every task line's
     * x-position in the content stream is the same, and enough tasks to
     * overflow one column's height must start a new page rather than a
     * second column at a different x.
     */
    public function test_many_tasks_all_share_the_same_x_position_across_pages(): void
    {
        $tasks = collect(
            array_map(
                fn (int $i) => new Task(['name' => "Task {$i}", 'status' => 'incomplete']),
                range(1, 60)
            )
        );

        $pdf = DayPdfExporter::build(Carbon::parse('2026-09-23'), $tasks, null, 'date', false);

        preg_match_all('/([\d.]+) ([\d.]+) Td \(Task \d+\)/', $pdf, $matches);

        $this->assertCount(60, $matches[1], 'expected one Td text draw per task name');

        $distinctXPositions = array_unique($matches[1]);
        $this->assertCount(
            1,
            $distinctXPositions,
            'all task name lines should share the same x-position (single column)'
        );

        // With enough tasks to overflow one page's worth of a single column,
        // the export must paginate instead of starting a second column.
        $this->assertGreaterThan(1, substr_count($pdf, '/Type /Page '));
    }
}
