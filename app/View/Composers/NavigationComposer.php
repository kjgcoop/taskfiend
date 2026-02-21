<?php

namespace App\View\Composers;

use Illuminate\View\View;

class NavigationComposer
{
    /**
     * Bind data to the view.
     */
    public function compose(View $view): void
    {
        $root = storage_path('app/other-links');
        $paths = is_dir($root) ? (glob("$root/*") ?: []) : [];

        $otherLinksFiles = collect($paths)
            ->filter(fn($p) => is_file($p))
            ->mapWithKeys(function ($path) {
                $filename = basename($path);
                $name = str_replace(['-', '_'], ' ', pathinfo($filename, PATHINFO_FILENAME));
                return [$filename => $name];
            });

        $view->with('otherLinksFiles', $otherLinksFiles);
    }
}
