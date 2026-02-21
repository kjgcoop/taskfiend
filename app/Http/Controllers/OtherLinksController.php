<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use FastVolt\Helper\Markdown;

class OtherLinksController extends Controller
{
    private const SOURCES = [
        'bundled' => 'bundled-links',
        'site'    => 'site',
    ];

    private const GROUP_LABELS = [
        'bundled' => 'Documentation',
        'site'    => 'Site',
    ];

    private function scanSource(string $sourceKey): array
    {
        $diskName = self::SOURCES[$sourceKey];
        $root = config("filesystems.disks.$diskName.root");

        if (!is_dir($root)) {
            return [];
        }

        $files = [];
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($root, \RecursiveDirectoryIterator::SKIP_DOTS)
        );

        foreach ($iterator as $file) {
            if (!$file->isFile()) {
                continue;
            }

            $relativePath = ltrim(str_replace($root, '', $file->getPathname()), '/');
            $segments = explode('/', $relativePath);

            $group = count($segments) > 1
                ? ucwords(str_replace(['-', '_'], ' ', $segments[0]))
                : self::GROUP_LABELS[$sourceKey];

            $files[] = [
                'routePath' => $sourceKey . '/' . $relativePath,
                'group'     => $group,
                'name'      => str_replace(['-', '_'], ' ', pathinfo($relativePath, PATHINFO_FILENAME)),
            ];
        }

        usort($files, fn($a, $b) => strcmp($a['name'], $b['name']));

        return $files;
    }

    public function index(Request $request)
    {
        $groups = collect(array_merge(
            $this->scanSource('bundled'),
            $this->scanSource('site'),
        ))->groupBy('group');

        return view('other.links.list', ['groups' => $groups]);
    }

    public function show(Request $request, string $path)
    {
        $segments = explode('/', $path, 2);

        if (count($segments) < 2) {
            abort(404);
        }

        [$sourceKey, $relativePath] = $segments;

        $diskName = self::SOURCES[$sourceKey] ?? null;

        if (!$diskName) {
            abort(404);
        }

        $root = realpath(config("filesystems.disks.$diskName.root"));
        $fullPath = realpath("$root/$relativePath");

        if (!$fullPath || !str_starts_with($fullPath, $root) || !is_file($fullPath)) {
            abort(404);
        }

        $fileContents = Storage::disk($diskName)->get($relativePath);

        $markdown = new Markdown();
        $markdown->setContent($fileContents);

        $title = str_replace(['-', '_'], ' ', pathinfo($relativePath, PATHINFO_FILENAME));

        return view('other.links.show', [
            'title'        => $title,
            'fileContents' => $markdown->getHtml(),
        ]);
    }
}
