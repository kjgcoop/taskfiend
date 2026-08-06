<?php

namespace App\Services;

use Carbon\Carbon;
use Illuminate\Support\Collection;

/**
 * Builds the printable "today's task list" PDF: a date header, an optional
 * meta line describing any active filter/sort, and the incomplete tasks in
 * a two-column bulleted list sized to fold down to pocket size.
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
    private const MARGIN = 40.0;
    private const COLUMN_GAP = 28.0;
    private const FONT_SIZE = 10.5;
    private const LINE_HEIGHT = 15.0;
    private const HEADER_TITLE_SIZE = 13.0;
    private const HEADER_META_SIZE = 8.5;

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
        $bulletWidth = self::textWidth('-  ', self::FONT_SIZE);

        $lines = [];
        foreach ($tasks as $task) {
            $label = $task->time
                ? Carbon::parse($task->time)->format('g:i A') . '  ' . $task->name
                : $task->name;

            $wrapped = self::wrap($label, $columnWidth - $bulletWidth, self::FONT_SIZE);
            foreach ($wrapped as $i => $text) {
                $lines[] = ['text' => $text, 'first' => $i === 0];
            }
        }

        $metaLine  = self::metaLine($filterQuery, $sort, $reversed);
        $headerRows = $metaLine ? 2 : 1;
        $topFirstPage = self::PAGE_H - self::MARGIN - ($headerRows * self::LINE_HEIGHT) - 6;
        $topOtherPages = self::PAGE_H - self::MARGIN;
        $bottom = self::MARGIN;

        $linesPerColumnFirst = max(1, (int) floor(($topFirstPage - $bottom) / self::LINE_HEIGHT) + 1);
        $linesPerColumnOther = max(1, (int) floor(($topOtherPages - $bottom) / self::LINE_HEIGHT) + 1);

        // Header (page 1 only).
        $pdf->text(self::MARGIN, self::PAGE_H - self::MARGIN, $date->format('l, F j, Y'), 'F2', self::HEADER_TITLE_SIZE);
        if ($metaLine) {
            $pdf->text(self::MARGIN, self::PAGE_H - self::MARGIN - self::LINE_HEIGHT, $metaLine, 'F1', self::HEADER_META_SIZE);
        }

        if ($lines === []) {
            $pdf->text(self::MARGIN, $topFirstPage, 'No tasks.', 'F1', self::FONT_SIZE);
            return $pdf->output();
        }

        $linesPerColumn = $linesPerColumnFirst;
        $top = $topFirstPage;
        $col = 0;
        $row = 0;
        $x = self::MARGIN;
        $y = $top;

        foreach ($lines as $line) {
            if ($row >= $linesPerColumn) {
                $col++;
                $row = 0;
                if ($col > 1) {
                    $pdf->newPage();
                    $linesPerColumn = $linesPerColumnOther;
                    $top = $topOtherPages;
                    $col = 0;
                }
                $x = $col === 0 ? self::MARGIN : self::MARGIN + $columnWidth + self::COLUMN_GAP;
                $y = $top;
            }

            $prefix = $line['first'] ? '-  ' : '   ';
            $pdf->text($x, $y, $prefix . $line['text'], 'F1', self::FONT_SIZE);
            $y -= self::LINE_HEIGHT;
            $row++;
        }

        return $pdf->output();
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
