<?php

namespace App\Services;

use Carbon\Carbon;
use Illuminate\Support\Collection;

/**
 * Builds the printable "today's task list" PDF: an eyebrow label, a big bold
 * date, an optional meta line describing any active filter/sort, a rule, and
 * the incomplete tasks in a two-column list — a time gutter on the left of
 * each column, a divider line under every row instead of a bullet, and a
 * vertical divider between the columns running the full column height.
 *
 * Layout is hand-laid-out (via SimplePdfWriter) rather than left to a CSS
 * multi-column engine: it gives us the top-to-bottom-then-across fill order
 * a foldable checklist wants, without depending on a renderer's column
 * support being reliable.
 */
class DayPdfExporter
{
    private const PAGE_W = 612.0; // US Letter, points (72/inch)
    private const PAGE_H = 792.0;
    private const MARGIN = 48.0;
    private const COLUMN_GAP = 32.0;

    private const GUTTER_WIDTH = 46.0;      // reserved for the time label
    private const GUTTER_TEXT_GAP = 14.0;   // space between the gutter and the task text

    private const FONT_SIZE = 10.0;
    private const LINE_HEIGHT = 14.0;

    private const TIME_FONT_SIZE = 8.5;
    private const TEXT_GRAY = 0.0;
    private const META_GRAY = 0.42;
    private const TIME_GRAY = 0.42;

    private const ROW_GAP_BELOW_TEXT = 7.0;  // last text baseline -> row divider
    private const ROW_GAP_ABOVE_NEXT = 11.0; // row divider -> next row's first baseline

    private const ROW_DIVIDER_GRAY  = 0.82;
    private const ROW_DIVIDER_WIDTH = 0.75;

    private const HEADER_EYEBROW_SIZE = 8.0;
    private const HEADER_EYEBROW_TRACKING = 1.4;
    private const HEADER_TITLE_SIZE = 19.0;
    private const HEADER_META_SIZE = 8.5;
    private const HEADER_RULE_GRAY  = 0.12;
    private const HEADER_RULE_WIDTH = 1.3;

    /** Standard Helvetica glyph widths (per 1000 em units), ASCII 32–126. */
    private const HELVETICA_WIDTHS = [
        32 => 278, 33 => 278, 34 => 355, 35 => 556, 36 => 556, 37 => 889, 38 => 667, 39 => 191,
        40 => 333, 41 => 333, 42 => 389, 43 => 584, 44 => 278, 45 => 333, 46 => 278, 47 => 278,
        48 => 556, 49 => 556, 50 => 556, 51 => 556, 52 => 556, 53 => 556, 54 => 556, 55 => 556,
        56 => 556, 57 => 556, 58 => 278, 59 => 278, 60 => 584, 61 => 584, 62 => 584, 63 => 556,
        64 => 1015, 65 => 667, 66 => 667, 67 => 722, 68 => 722, 69 => 667, 70 => 611, 71 => 778,
        72 => 722, 73 => 278, 74 => 500, 75 => 667, 76 => 556, 77 => 833, 78 => 722, 79 => 778,
        80 => 667, 81 => 778, 82 => 722, 83 => 667, 84 => 611, 85 => 722, 86 => 667, 87 => 944,
        88 => 667, 89 => 667, 90 => 611, 91 => 278, 92 => 278, 93 => 278, 94 => 469, 95 => 556,
        96 => 333, 97 => 556, 98 => 556, 99 => 500, 100 => 556, 101 => 556, 102 => 278, 103 => 556,
        104 => 556, 105 => 222, 106 => 222, 107 => 500, 108 => 222, 109 => 833, 110 => 556,
        111 => 556, 112 => 556, 113 => 556, 114 => 333, 115 => 500, 116 => 278, 117 => 556,
        118 => 500, 119 => 722, 120 => 500, 121 => 500, 122 => 500, 123 => 334, 124 => 260,
        125 => 334, 126 => 584,
    ];

    /**
     * @param  Collection<int, \App\Models\Task>  $tasks  Already filtered/sorted, in display order.
     */
    public static function build(Carbon $date, Collection $tasks, ?string $filterQuery, string $sort, bool $reversed): string
    {
        $pdf = new SimplePdfWriter(self::PAGE_W, self::PAGE_H);

        $columnWidth = (self::PAGE_W - 2 * self::MARGIN - self::COLUMN_GAP) / 2;
        $textWidth   = $columnWidth - self::GUTTER_WIDTH - self::GUTTER_TEXT_GAP;
        $dividerX    = self::MARGIN + $columnWidth + self::COLUMN_GAP / 2;
        $col1X       = self::MARGIN + $columnWidth + self::COLUMN_GAP;
        $bottomY     = self::MARGIN;

        $contentTopFirstPage = self::drawHeader($pdf, $date, $filterQuery, $sort, $reversed);
        $contentTopOtherPages = self::PAGE_H - self::MARGIN - 10.0;

        if ($tasks->isEmpty()) {
            $pdf->text(self::MARGIN, $contentTopFirstPage, 'No tasks.', 'F1', self::FONT_SIZE, self::TEXT_GRAY);
            return $pdf->output();
        }

        $rows = $tasks->map(function ($task) use ($textWidth) {
            return [
                'time'  => $task->time ? Carbon::parse($task->time)->format('g:i A') : null,
                'lines' => self::wrap($task->name, $textWidth, self::FONT_SIZE),
            ];
        });

        $col = 0;
        $x = self::MARGIN;
        $columnTop = $contentTopFirstPage;
        $y = $columnTop;
        $pdf->line($dividerX, $columnTop, $dividerX, $bottomY, self::ROW_DIVIDER_WIDTH, self::ROW_DIVIDER_GRAY);

        foreach ($rows as $row) {
            $numLines   = count($row['lines']);
            $lastLineY  = $y - ($numLines - 1) * self::LINE_HEIGHT;
            $fits       = ($lastLineY - self::ROW_GAP_BELOW_TEXT) >= $bottomY;
            $atFreshTop = ($y === $columnTop);

            if (!$fits && !$atFreshTop) {
                $col++;
                if ($col > 1) {
                    $pdf->newPage();
                    $col = 0;
                    $columnTop = $contentTopOtherPages;
                    $pdf->line($dividerX, $columnTop, $dividerX, $bottomY, self::ROW_DIVIDER_WIDTH, self::ROW_DIVIDER_GRAY);
                }
                $x = $col === 0 ? self::MARGIN : $col1X;
                $y = $columnTop;
                $lastLineY = $y - ($numLines - 1) * self::LINE_HEIGHT;
            }

            if ($row['time']) {
                $pdf->text($x, $y, $row['time'], 'F1', self::TIME_FONT_SIZE, self::TIME_GRAY);
            }
            foreach ($row['lines'] as $i => $line) {
                $pdf->text($x + self::GUTTER_WIDTH + self::GUTTER_TEXT_GAP, $y - $i * self::LINE_HEIGHT, $line, 'F1', self::FONT_SIZE, self::TEXT_GRAY);
            }

            $dividerY = $lastLineY - self::ROW_GAP_BELOW_TEXT;
            $pdf->line($x, $dividerY, $x + $columnWidth, $dividerY, self::ROW_DIVIDER_WIDTH, self::ROW_DIVIDER_GRAY);

            $y = $dividerY - self::ROW_GAP_ABOVE_NEXT;
        }

        return $pdf->output();
    }

    /**
     * Draws the eyebrow/title/meta/rule block and returns the y where the task list should start.
     * The "TODAY" eyebrow only appears when $date actually is today, matching how the day view
     * itself only prefixes "Today - " onto its own header for the current date (day.blade.php) —
     * other dates just get the plain weekday/date title, no label above it.
     */
    private static function drawHeader(SimplePdfWriter $pdf, Carbon $date, ?string $filterQuery, string $sort, bool $reversed): float
    {
        $top = self::PAGE_H - self::MARGIN;

        if ($date->isToday()) {
            $eyebrowY = $top - self::HEADER_EYEBROW_SIZE;
            $pdf->text(self::MARGIN, $eyebrowY, 'TODAY', 'F1', self::HEADER_EYEBROW_SIZE, self::META_GRAY, self::HEADER_EYEBROW_TRACKING);
            $titleY = $eyebrowY - 24.0;
        } else {
            $titleY = $top - self::HEADER_TITLE_SIZE;
        }

        $pdf->text(self::MARGIN, $titleY, $date->format('l, F j, Y'), 'F2', self::HEADER_TITLE_SIZE, 0.0);

        $lastY = $titleY;
        $metaLine = self::metaLine($filterQuery, $sort, $reversed);
        if ($metaLine) {
            $metaY = $titleY - 20.0;
            $pdf->text(self::MARGIN, $metaY, $metaLine, 'F1', self::HEADER_META_SIZE, self::META_GRAY);
            $lastY = $metaY;
        }

        $ruleY = $lastY - 16.0;
        $pdf->line(self::MARGIN, $ruleY, self::PAGE_W - self::MARGIN, $ruleY, self::HEADER_RULE_WIDTH, self::HEADER_RULE_GRAY);

        return $ruleY - 24.0;
    }

    /** Raw filter/sort description — deliberately not humanized (same tokens the user typed). */
    private static function metaLine(?string $filterQuery, string $sort, bool $reversed): ?string
    {
        $parts = [];
        if ($filterQuery !== null && trim($filterQuery) !== '') {
            $parts[] = 'filter: ' . trim($filterQuery);
        }
        if ($sort !== 'date' || $reversed) {
            $parts[] = 'sort: ' . $sort . ($reversed ? ' reversed' : '');
        }

        return $parts === [] ? null : implode('  ·  ', $parts);
    }

    /** Greedy word-wrap using real Helvetica glyph widths so lines fit $maxWidth points. */
    private static function wrap(string $text, float $maxWidth, float $size): array
    {
        $words = preg_split('/\s+/', trim($text)) ?: [];
        if ($words === []) return [''];

        $lines = [];
        $current = '';
        foreach ($words as $word) {
            $candidate = $current === '' ? $word : $current . ' ' . $word;
            if (self::textWidth($candidate, $size) <= $maxWidth || $current === '') {
                $current = $candidate;
            } else {
                $lines[] = $current;
                $current = $word;
            }
        }
        if ($current !== '') $lines[] = $current;

        return $lines;
    }

    private static function textWidth(string $text, float $size): float
    {
        $units = 0;
        $len = strlen($text); // byte-wise is fine: width table only covers ASCII 32-126
        for ($i = 0; $i < $len; $i++) {
            $code = ord($text[$i]);
            $units += self::HELVETICA_WIDTHS[$code] ?? 556;
        }
        return $units / 1000 * $size;
    }
}
