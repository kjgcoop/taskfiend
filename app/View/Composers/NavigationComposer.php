<?php

namespace App\View\Composers;

use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;
use App\Models\Project;

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

        $navProjects = collect();
        if (Auth::check()) {
            $navProjects = Project::activeForUser(Auth::id())
                ->where('is_inbox', false)
                ->orderBy('name')
                ->get(['id', 'name']);
        }

        $view->with('otherLinksFiles', $otherLinksFiles);
        $view->with('navProjects', $navProjects);
    }
}
