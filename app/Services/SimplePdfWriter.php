<?php

namespace App\Services;

/**
 * A minimal, dependency-free PDF writer.
 *
 * Task Fiend's daily export is a single page of plain text (a date header
 * plus a two-column bulleted task list) — well within what the 14 standard
 * PDF fonts can render without embedding anything. Pulling in a full PDF
 * library (mpdf, dompdf, browsershot, ...) for that felt like the wrong
 * trade: extra dependency weight and, for the headless-Chrome options, an
 * external binary — to lay out text we can position ourselves.
 *
 * Supports exactly what the day export needs: multiple pages, absolutely
 * positioned text lines in Helvetica or Helvetica-Bold. Text is transcoded
 * to WinAnsiEncoding (Windows-1252); characters outside that range (emoji,
 * CJK, etc.) are dropped rather than embedding a unicode font.
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
     */
    public function text(float $x, float $y, string $text, string $font = 'F1', float $size = 10.0): void
    {
        $this->currentPageOps[] = sprintf(
            'BT /%s %s Tf %s %s Td (%s) Tj ET',
            $font,
            $this->num($size),
            $this->num($x),
            $this->num($y),
            $this->escape($text)
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
