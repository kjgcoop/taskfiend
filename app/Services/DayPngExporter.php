<?php

namespace App\Services;

use Carbon\Carbon;
use Illuminate\Support\Collection;

/**
 * Builds a single tall PNG of a day's task list — for printing on a receipt/
 * thermal printer, where DayPdfExporter's multi-column US-Letter layout means
 * cutting the page into strips and taping them end to end to avoid a blank gap
 * between columns. This is one continuous column instead: no columns, no page
 * breaks, height computed from the content so the image is exactly as tall as
 * it needs to be.
 *
 * Unlike the PDF export, which references the standard Helvetica font by name
 * (something every PDF viewer already has), a PNG's glyphs have to be drawn
 * by us — GD needs an actual TrueType font file on disk. resolveFont() looks
 * for one at a configured path, then a short list of common system paths,
 * and falls back to GD's built-in bitmap font (ugly, monospace, no real bold
 * or letter-spacing — but always available, so the button never just errors)
 * if none is found. See config/taskfiend.php for the env vars.
 */
class DayPngExporter
{
    private const MARGIN = 24;
    private const GUTTER_WIDTH = 72;
    private const GUTTER_TEXT_GAP = 16;

    private const BODY_SIZE = 15;
    private const LINE_HEIGHT = 22;
    private const TIME_SIZE = 12;

    private const ROW_GAP_BELOW_TEXT = 10;
    private const ROW_GAP_ABOVE_NEXT = 16;

    private const TITLE_SIZE = 26;
    private const META_SIZE = 12;
    private const HEADER_RULE_GAP = 22; // rule position -> first row's top

    private const SECTION_LABEL_SIZE = 12;
    private const SECTION_LABEL_TRACKING = 2.0; // px added after each letter, TTF mode only
    private const SECTION_LABEL_TEXT_INSET = 14;
    private const SECTION_LABEL_BOX_PAD_BELOW = 12; // box bottom -> label baseline
    private const SECTION_LABEL_HEIGHT = 36; // box top -> next row's top

    private const STATUS_LABELS = [
        'incomplete' => 'Incomplete',
        'done'       => 'Done',
        'archived'   => 'Archived',
    ];

    /** Tried in order; the first that actually exists on disk wins. */
    private const COMMON_FONT_PATHS = [
        'regular' => [
            '/usr/share/fonts/truetype/dejavu/DejaVuSans.ttf',
            '/usr/share/fonts/truetype/liberation/LiberationSans-Regular.ttf',
            '/System/Library/Fonts/Supplemental/Arial.ttf',
            'C:\\Windows\\Fonts\\arial.ttf',
        ],
        'bold' => [
            '/usr/share/fonts/truetype/dejavu/DejaVuSans-Bold.ttf',
            '/usr/share/fonts/truetype/liberation/LiberationSans-Bold.ttf',
            '/System/Library/Fonts/Supplemental/Arial Bold.ttf',
            'C:\\Windows\\Fonts\\arialbd.ttf',
        ],
    ];

    /**
     * @param  Collection<int, \App\Models\Task>  $tasks  Already filtered/sorted, in display
     *                        order. May mix statuses — same status-label treatment as
     *                        DayPdfExporter, just simpler here: no column/page breaks means a
     *                        label never needs to repeat, only appear once per status change.
     * @param  int  $width  Image width in px; see config/taskfiend.php (DAY_EXPORT_PNG_WIDTH).
     */
    public static function build(Carbon $date, Collection $tasks, ?string $filterQuery, string $sort, bool $reversed, int $width): string
    {
        $width = max(200, min(4000, $width));

        $fontRegular = self::resolveFont('regular');
        $fontBold    = self::resolveFont('bold') ?? $fontRegular;

        $textWidth = $width - 2 * self::MARGIN - self::GUTTER_WIDTH - self::GUTTER_TEXT_GAP;

        $rows = $tasks->map(fn ($task) => [
            'status' => $task->status,
            'time'   => $task->time ? Carbon::parse($task->time)->format('g:i A') : null,
            'lines'  => self::wrap($task->name, $textWidth, self::BODY_SIZE, $fontRegular),
        ]);
        $multiStatus = $rows->pluck('status')->unique()->count() > 1;
        $metaLine    = self::metaLine($filterQuery, $sort, $reversed);

        // ---- Pass 1: measure total height (GD needs the canvas size up front) ----
        $y = self::MARGIN + self::TITLE_SIZE + 6;
        if ($metaLine) $y += self::META_SIZE + 10;
        $y += self::HEADER_RULE_GAP;

        if ($tasks->isEmpty()) {
            $y += self::BODY_SIZE + self::MARGIN;
        } else {
            $currentStatus = null;
            foreach ($rows as $row) {
                if ($multiStatus && $row['status'] !== $currentStatus) {
                    $y += self::SECTION_LABEL_HEIGHT;
                    $currentStatus = $row['status'];
                }
                $y += count($row['lines']) * self::LINE_HEIGHT + self::ROW_GAP_BELOW_TEXT + self::ROW_GAP_ABOVE_NEXT;
            }
            $y += self::MARGIN - self::ROW_GAP_ABOVE_NEXT;
        }

        $height = (int) ceil(max($y, self::MARGIN * 2));

        // ---- Pass 2: draw ----
        $im = imagecreatetruecolor($width, $height);
        imageantialias($im, true);
        $white      = imagecolorallocate($im, 255, 255, 255);
        $ink        = imagecolorallocate($im, 17, 17, 17);
        $muted      = imagecolorallocate($im, 110, 110, 110);
        $rule       = imagecolorallocate($im, 31, 31, 31);
        $divider    = imagecolorallocate($im, 214, 214, 214);
        $labelBoxBg = imagecolorallocate($im, 231, 231, 231);
        imagefilledrectangle($im, 0, 0, $width, $height, $white);

        $cursorY = self::MARGIN;
        self::drawText($im, $fontBold, self::TITLE_SIZE, self::MARGIN, $cursorY + self::TITLE_SIZE, $date->format('l, F j, Y'), $ink);
        $cursorY += self::TITLE_SIZE + 6;

        if ($metaLine) {
            self::drawText($im, $fontRegular, self::META_SIZE, self::MARGIN, $cursorY + self::META_SIZE, $metaLine, $muted);
            $cursorY += self::META_SIZE + 10;
        }

        $ruleY = $cursorY + 4;
        imagefilledrectangle($im, self::MARGIN, $ruleY, $width - self::MARGIN, $ruleY + 2, $rule);
        $cursorY = $ruleY + self::HEADER_RULE_GAP;

        if ($tasks->isEmpty()) {
            self::drawText($im, $fontRegular, self::BODY_SIZE, self::MARGIN, $cursorY + self::BODY_SIZE, 'No tasks.', $ink);
        } else {
            $currentStatus = null;
            foreach ($rows as $row) {
                if ($multiStatus && $row['status'] !== $currentStatus) {
                    $label = strtoupper(self::STATUS_LABELS[$row['status']] ?? $row['status']);
                    imagefilledrectangle($im, self::MARGIN, $cursorY, $width - self::MARGIN, $cursorY + self::SECTION_LABEL_HEIGHT, $labelBoxBg);
                    $labelBaselineY = $cursorY + self::SECTION_LABEL_HEIGHT - self::SECTION_LABEL_BOX_PAD_BELOW;
                    self::drawTrackedText($im, $fontBold, self::SECTION_LABEL_SIZE, self::MARGIN + self::SECTION_LABEL_TEXT_INSET, $labelBaselineY, $label, $muted, self::SECTION_LABEL_TRACKING);
                    $cursorY += self::SECTION_LABEL_HEIGHT;
                    $currentStatus = $row['status'];
                }

                $rowTopY = $cursorY;
                if ($row['time']) {
                    self::drawText($im, $fontRegular, self::TIME_SIZE, self::MARGIN, $rowTopY + self::TIME_SIZE, $row['time'], $muted);
                }
                foreach ($row['lines'] as $i => $line) {
                    self::drawText($im, $fontRegular, self::BODY_SIZE, self::MARGIN + self::GUTTER_WIDTH + self::GUTTER_TEXT_GAP, $rowTopY + self::BODY_SIZE + $i * self::LINE_HEIGHT, $line, $ink);
                }

                $textBottomY = $rowTopY + (count($row['lines']) - 1) * self::LINE_HEIGHT + self::BODY_SIZE;
                $dividerY    = $textBottomY + self::ROW_GAP_BELOW_TEXT;
                imagefilledrectangle($im, self::MARGIN, $dividerY, $width - self::MARGIN, $dividerY + 1, $divider);
                $cursorY = $dividerY + self::ROW_GAP_ABOVE_NEXT;
            }
        }

        ob_start();
        imagepng($im);
        $bytes = ob_get_clean();
        imagedestroy($im);

        return $bytes;
    }

    /**
     * Finds a usable TrueType font file, in priority order: the matching
     * config/env override, then COMMON_FONT_PATHS, then null (caller falls
     * back to GD's built-in bitmap font). $weight is 'regular' or 'bold'.
     */
    private static function resolveFont(string $weight): ?string
    {
        $configured = $weight === 'bold'
            ? config('taskfiend.day_export_png_font_bold')
            : config('taskfiend.day_export_png_font_regular');

        foreach (array_filter([$configured, ...self::COMMON_FONT_PATHS[$weight]]) as $path) {
            if (is_string($path) && $path !== '' && is_file($path) && is_readable($path)) {
                return $path;
            }
        }

        return null;
    }

    /** Draws one line of text with its baseline at ($x, $y). Falls back to GD's built-in font when $font is null (no usable TTF was found) — no bold, no anti-aliasing, but never fatal. */
    private static function drawText($im, ?string $font, float $size, float $x, float $y, string $text, int $color): void
    {
        if ($font !== null) {
            imagettftext($im, $size, 0, (int) round($x), (int) round($y), $color, $font, $text);
            return;
        }

        $gdFont = 5; // GD's largest built-in bitmap font
        imagestring($im, $gdFont, (int) round($x), (int) round($y - imagefontheight($gdFont)), $text, $color);
    }

    /** Like drawText(), but adds $tracking px after every character — imagettftext has no native letter-spacing, so this draws char-by-char. Falls back to plain drawText() (no tracking) without a TTF font, since GD's built-in font has no per-glyph metrics worth spacing out. */
    private static function drawTrackedText($im, ?string $font, float $size, float $x, float $y, string $text, int $color, float $tracking): void
    {
        if ($font === null) {
            self::drawText($im, null, $size, $x, $y, $text, $color);
            return;
        }

        $cursorX = $x;
        foreach (mb_str_split($text) as $char) {
            imagettftext($im, $size, 0, (int) round($cursorX), (int) round($y), $color, $font, $char);
            $cursorX += self::ttfTextWidth($font, $size, $char) + $tracking;
        }
    }

    /** Greedy word-wrap using real font metrics (TTF) or a monospace estimate (GD built-in fallback) so lines fit $maxWidth px. */
    private static function wrap(string $text, float $maxWidth, float $size, ?string $font): array
    {
        $words = preg_split('/\s+/', trim($text)) ?: [];
        if ($words === []) return [''];

        $widthOf = $font !== null
            ? fn ($s) => self::ttfTextWidth($font, $size, $s)
            : fn ($s) => mb_strlen($s) * imagefontwidth(5);

        $lines = [];
        $current = '';
        foreach ($words as $word) {
            $candidate = $current === '' ? $word : $current . ' ' . $word;
            if ($widthOf($candidate) <= $maxWidth || $current === '') {
                $current = $candidate;
            } else {
                $lines[] = $current;
                $current = $word;
            }
        }
        if ($current !== '') $lines[] = $current;

        return $lines;
    }

    private static function ttfTextWidth(string $font, float $size, string $text): float
    {
        $box = imagettfbbox($size, 0, $font, $text);
        return abs($box[2] - $box[0]);
    }

    /** Raw filter/sort description — deliberately not humanized (same tokens the user typed). Identical to DayPdfExporter's, duplicated rather than shared since it's a two-line helper and the two exporters otherwise share no code. */
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
}
