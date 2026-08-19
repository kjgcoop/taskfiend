<?php

namespace App\Services;

/**
 * A minimal, dependency-free PDF writer.
 *
 * Task Fiend's daily export is a single page of text and thin ruled lines (a
 * date header plus a two-column task list with divider lines) — well within
 * what the 14 standard PDF fonts and a handful of stroked lines can render
 * without embedding anything. Pulling in a full PDF library (mpdf, dompdf,
 * browsershot, ...) for that felt like the wrong trade: extra dependency
 * weight and, for the headless-Chrome options, an external binary — to lay
 * out text and lines we can position ourselves.
 *
 * Supports exactly what the day export needs: multiple pages, absolutely
 * positioned text lines in Helvetica or Helvetica-Bold (optionally gray and/or
 * letter-spaced), straight stroked lines (for the column/row dividers), and
 * filled rectangles (for the shaded backdrop behind an in-list section label).
 * Text is transcoded to WinAnsiEncoding (Windows-1252); characters outside
 * that range (emoji, CJK, etc.) are dropped rather than embedding a unicode
 * font.
 *
 * Every operator each public method emits is fully self-contained (color,
 * spacing, etc. are always stated explicitly, never left to whatever the
 * previous call happened to set) — PDF graphics state persists across BT/ET
 * text blocks and isn't reset automatically, so relying on "whatever's
 * already set" is how you get a task title silently inheriting the previous
 * line's gray fill color.
 */
class SimplePdfWriter
{
    private float $width;
    private float $height;

    /** @var array<int, array<int, string>> Finished pages; each is a list of content-stream operator lines. */
    private array $pages = [];

    /** @var array<int, string> The page currently being written to. */
    private array $currentPageOps = [];

    public function __construct(float $width = 612.0, float $height = 792.0)
    {
        $this->width  = $width;
        $this->height = $height;
    }

    /** Start a new page. Safe to call before writing anything to the first page. */
    public function newPage(): void
    {
        if (!empty($this->currentPageOps)) {
            $this->pages[] = $this->currentPageOps;
        }
        $this->currentPageOps = [];
    }

    /**
     * Draw one line of text with its baseline at ($x, $y), measured in points
     * from the bottom-left corner of the page (standard PDF coordinates).
     *
     * @param  float  $gray  Fill color, 0 (black) to 1 (white).
     * @param  float  $charSpacing  Extra space between characters, in points — for letter-spaced small caps.
     */
    public function text(float $x, float $y, string $text, string $font = 'F1', float $size = 10.0, float $gray = 0.0, float $charSpacing = 0.0): void
    {
        $this->currentPageOps[] = sprintf(
            'BT /%s %s Tf %s g %s Tc %s %s Td (%s) Tj ET',
            $font,
            $this->num($size),
            $this->num($gray),
            $this->num($charSpacing),
            $this->num($x),
            $this->num($y),
            $this->escape($text)
        );
    }

    /**
     * Draw a straight stroked line from ($x1, $y1) to ($x2, $y2) — used for
     * the header rule and the row/column dividers.
     */
    public function line(float $x1, float $y1, float $x2, float $y2, float $width = 1.0, float $gray = 0.0): void
    {
        $this->currentPageOps[] = sprintf(
            "q\n%s w\n%s G\n%s %s m\n%s %s l\nS\nQ",
            $this->num($width),
            $this->num($gray),
            $this->num($x1),
            $this->num($y1),
            $this->num($x2),
            $this->num($y2)
        );
    }

    /**
     * Draw a filled, unstroked rectangle with its bottom-left corner at ($x, $y) —
     * used for the shaded backdrop behind an in-list section label. Sharp corners
     * only (no rounding): a rounded rect needs Bezier curve operators, which this
     * writer doesn't otherwise need anywhere, for a difference that's barely
     * visible at this size.
     */
    public function filledRect(float $x, float $y, float $width, float $height, float $gray = 0.9): void
    {
        $this->currentPageOps[] = sprintf(
            "q\n%s g\n%s %s %s %s re\nf\nQ",
            $this->num($gray),
            $this->num($x),
            $this->num($y),
            $this->num($width),
            $this->num($height)
        );
    }

    /** Render the accumulated pages to raw PDF bytes. */
    public function output(): string
    {
        // Flush whatever's pending, even an empty page (e.g. a completely empty export).
        $this->pages[] = $this->currentPageOps;
        $this->currentPageOps = [];

        $objects = [];      // 1-indexed object number => object body (without "N 0 obj"/"endobj")
        $nextId  = 1;
        $alloc   = function () use (&$nextId) { return $nextId++; };

        $catalogId = $alloc();
        $pagesId   = $alloc();
        $fontRegularId = $alloc();
        $fontBoldId    = $alloc();

        $objects[$fontRegularId] = "<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>";
        $objects[$fontBoldId]    = "<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica-Bold /Encoding /WinAnsiEncoding >>";

        $pageIds = [];
        foreach ($this->pages as $ops) {
            $contentId = $alloc();
            $pageId    = $alloc();
            $stream    = implode("\n", $ops);
            $objects[$contentId] = "<< /Length " . strlen($stream) . " >>\nstream\n" . $stream . "\nendstream";
            $objects[$pageId] = sprintf(
                "<< /Type /Page /Parent %d 0 R /MediaBox [0 0 %s %s] /Resources << /Font << /F1 %d 0 R /F2 %d 0 R >> >> /Contents %d 0 R >>",
                $pagesId,
                $this->num($this->width),
                $this->num($this->height),
                $fontRegularId,
                $fontBoldId,
                $contentId
            );
            $pageIds[] = $pageId;
        }

        $kids = implode(' ', array_map(fn ($id) => "$id 0 R", $pageIds));
        $objects[$pagesId]   = "<< /Type /Pages /Kids [$kids] /Count " . count($pageIds) . " >>";
        $objects[$catalogId] = "<< /Type /Catalog /Pages $pagesId 0 R >>";

        ksort($objects);

        $out     = "%PDF-1.4\n";
        $offsets = [0 => 0];
        foreach ($objects as $id => $body) {
            $offsets[$id] = strlen($out);
            $out .= "$id 0 obj\n$body\nendobj\n";
        }

        $xrefStart = strlen($out);
        $count     = count($objects) + 1;
        $out .= "xref\n0 $count\n";
        $out .= "0000000000 65535 f \n";
        for ($id = 1; $id < $count; $id++) {
            $out .= sprintf("%010d 00000 n \n", $offsets[$id] ?? 0);
        }
        $out .= "trailer\n<< /Size $count /Root $catalogId 0 R >>\nstartxref\n$xrefStart\n%%EOF";

        return $out;
    }

    /** Format a number without scientific notation or unnecessary trailing zeros. */
    private function num(float $n): string
    {
        return rtrim(rtrim(sprintf('%.3F', $n), '0'), '.') ?: '0';
    }

    /** Transcode to WinAnsi and escape PDF literal-string special characters. */
    private function escape(string $text): string
    {
        $ansi = @iconv('UTF-8', 'Windows-1252//TRANSLIT//IGNORE', $text);
        if ($ansi === false) {
            $ansi = preg_replace('/[^\x20-\x7E]/', '?', $text) ?? '';
        }

        return str_replace(['\\', '(', ')'], ['\\\\', '\\(', '\\)'], $ansi);
    }
}
