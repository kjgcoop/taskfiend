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
