<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use FastVolt\Helper\Markdown;

class OtherLinksController extends Controller
{
    public function index(Request $request)
    {
        $root = storage_path('app/other-links');
        $paths = glob("$root/*") ?: [];

        $files = collect($paths)
            ->filter(fn($p) => is_file($p))
            ->mapWithKeys(function ($path) {
                $filename = basename($path);
                $name = str_replace(['-', '_'], ' ', pathinfo($filename, PATHINFO_FILENAME));
                return [$filename => $name];
            });

        return view('other.links.list', [
            'files' => $files
        ]);
    }

    public function show(Request $request, string $filename)
    {
        $fullPath = storage_path('app/other-links') . '/' . $filename;
        if (!is_file($fullPath)) {
            abort(404);
        }

        $fileContents = Storage::disk('other-links')->get($filename);

        $markdown = new Markdown();
        $markdown->setContent($fileContents);

        $title = str_replace(['-', '_'], ' ', pathinfo($filename, PATHINFO_FILENAME));

        return view('other.links.show', [
            'title' => $title,
            'fileContents' => $markdown->getHtml()
        ]);

    }
}
