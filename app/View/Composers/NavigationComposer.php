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
        $sources = [
            'bundled' => config('filesystems.disks.bundled-links.root'),
            'site'    => config('filesystems.disks.site.root'),
        ];

        $otherLinksFiles = collect();

        foreach ($sources as $sourceKey => $root) {
            if (!is_dir($root)) {
                continue;
            }

            foreach (glob("$root/*") ?: [] as $path) {
                if (!is_file($path)) {
                    continue;
                }
                $filename = basename($path);
                $name = str_replace(['-', '_'], ' ', pathinfo($filename, PATHINFO_FILENAME));
                $otherLinksFiles->put("$sourceKey/$filename", $name);
            }
        }

        $view->with('otherLinksFiles', $otherLinksFiles);
    }
}
