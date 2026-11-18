<?php

namespace App\Services;

use App\Models\Project;
use App\Models\Tag;
use App\Models\Task;
use App\Models\User;

/**
 * Parses inline quick-add tokens out of a task name:
 *
 *   #project                  → project (must be active and accessible)
 *   @tag                      → tags (additive with any form-level tags)
 *   +place / +"Some Place"    → location
 *   ++place / ++"Some Place"  → location shown as a map link
 *   &user                     → assignees (enabled users only)
 *
 * Matched tokens are stripped from the name; unmatched tokens are left in
 * place as plain text. This is the single source of truth for token
 * parsing, shared by single-task store, bulk (multi-line) store, and the
 * quick-add live preview — so the preview always shows exactly what
 * submitting will do.
 */
class QuickAddParser
{
    public function __construct(private int $userId)
    {
    }

    /**
     * @param bool $parseLocation  Parse +/++ location tokens.
     * @param bool $parseAssignees Parse &user tokens.
     */
    public function parse(
        string $input,
        bool $parseLocation = true,
        bool $parseAssignees = true,
    ): QuickAddTokens {
        $tokens = new QuickAddTokens();

        $name = $this->parseProjectToken($input, $tokens);
        $name = $this->parseTagTokens($name, $tokens);
        if ($parseLocation) {
            $name = $this->parseLocationToken($name, $tokens);
        }
        if ($parseAssignees) {
            $name = $this->parseAssigneeTokens($name, $tokens);
        }

        // Collapse any double-spaces left by token removal
        $tokens->name = trim(preg_replace('/\s{2,}/', ' ', $name));

        return $tokens;
    }

    private function parseProjectToken(string $name, QuickAddTokens $tokens): string
    {
        // Only a token that matches an active project is a project token. An
        // unmatched #word (a hashtag the user wants to keep) stays in the title.
        // A match overrides any project_id the form posted (page context or an
        // autocomplete pick): "#project-b" typed on Project A's page goes to B.
        if (!preg_match_all('/#([\w-]+)/', $name, $matches)) {
            return $name;
        }

        foreach ($matches[1] as $slug) {
            $project = $this->findProject($slug);
            if (!$project) {
                continue;
            }

            $tokens->project = $project;
            $tokens->projectResolved = true;

            return trim(preg_replace('/#' . preg_quote($slug, '/') . '(?![\w-])\s*/', '', $name, 1));
        }

        return $name;
    }

    private function findProject(string $slug): ?Project
    {
        $query = strtolower($slug);
        // Normalize query by stripping hyphens so "#my-project" matches "My Project".
        $queryNorm = str_replace('-', '', $query);
        // Strip hyphens, spaces, apostrophes, and periods from the stored name.
        // e.g. "KJ's Inbox" → "kjsinbox", "My Project" → "myproject" ✓
        $stripped = "LOWER(REPLACE(REPLACE(REPLACE(REPLACE(name, '-', ''), ' ', ''), '''', ''), '.', ''))";

        $project = Project::where(function ($q) use ($query, $queryNorm, $stripped) {
                $q->whereRaw('LOWER(name) = ?', [$query])
                  ->orWhereRaw("{$stripped} = ?", [$queryNorm]);
            })
            ->activeForUser($this->userId)
            ->first();

        if ($project) {
            return $project;
        }

        // Fallback: the autocomplete inserts a JS slug (lowercase, spaces→hyphens,
        // everything outside a-z0-9- removed), which differs from the SQL match
        // for names with other punctuation ("R&D" → "#rd"). Match that rule too so
        // an autocompleted token is always recognized.
        return Project::activeForUser($this->userId)->get()
            ->first(fn (Project $p) => $this->jsSlug($p->name) === $queryNorm);
    }

    /** Mirrors slugify() in layouts/app.blade.php, with hyphens removed for comparison. */
    private function jsSlug(string $name): string
    {
        $slug = preg_replace('/\s+/', '-', mb_strtolower($name));

        return str_replace('-', '', preg_replace('/[^a-z0-9-]/', '', $slug));
    }

    private function parseTagTokens(string $name, QuickAddTokens $tokens): string
    {
        if (!preg_match_all('/@([\w-]+)/', $name, $matches)) {
            return $name;
        }

        $matchedSlugs = [];
        foreach ($matches[1] as $tagSlug) {
            $tag = Tag::active()
                ->where(function ($q) use ($tagSlug) {
                    // Exact match, hyphen-slug match (handles "Long Tag" → "@long-tag"),
                    // space-stripped match (handles "5 minutes" → "@5minutes"),
                    // and prefix match as a last resort.
                    $q->whereRaw('LOWER(tag_name) = ?', [strtolower($tagSlug)])
                      ->orWhereRaw("LOWER(REPLACE(tag_name, ' ', '-')) = ?", [strtolower($tagSlug)])
                      ->orWhereRaw("LOWER(REPLACE(tag_name, ' ', '')) = ?", [strtolower($tagSlug)])
                      ->orWhereRaw('LOWER(tag_name) LIKE ?', [strtolower($tagSlug) . '%']);
                })
                ->first();

            if ($tag) {
                $tokens->tagIds[]   = $tag->id;
                $tokens->tagNames[] = $tag->tag_name;
                $matchedSlugs[]     = preg_quote($tagSlug, '/');
            }
            // Unrecognised @word: leave it as plain text in the title.
        }

        $tokens->tagIds = array_values(array_unique($tokens->tagIds));

        // Only strip the tokens that actually matched a tag.
        if (!empty($matchedSlugs)) {
            $name = trim(preg_replace('/@(' . implode('|', $matchedSlugs) . ')\s*/', '', $name));
        }

        return $name;
    }

    private function parseLocationToken(string $name, QuickAddTokens $tokens): string
    {
        // Supported forms (++ = show as map link):
        //   ++"123 Main St, Town"   ++office   +"Coffee Shop"   +home
        // The (?<!\S) guard keeps a mid-word + (e.g. "5+3") from matching.
        $forms = [
            ['/(?<!\S)\+\+"([^"]+)"/', '/(?<!\S)\+\+"[^"]*"\s*/', true],
            ['/(?<!\S)\+\+(\w[\w-]*)/', '/(?<!\S)\+\+\w[\w-]*\s*/', true],
            ['/(?<!\S)\+"([^"]+)"/',    '/(?<!\S)\+"[^"]*"\s*/',    false],
            ['/(?<!\S)\+(\w[\w-]*)/',   '/(?<!\S)\+\w[\w-]*\s*/',   false],
        ];

        foreach ($forms as [$matchPattern, $stripPattern, $showMap]) {
            if (preg_match($matchPattern, $name, $m)) {
                $tokens->location = $this->resolveLocationToken($m[1]);
                $tokens->showMap  = $showMap;
                return trim(preg_replace($stripPattern, '', $name));
            }
        }

        return $name;
    }

    /**
     * Fuzzy-match a location token against the user's existing task
     * locations. Strips spaces, hyphens, and underscores before comparing so
     * that "coffee-shop", "Coffee Shop", and "coffeeshop" all resolve to the
     * already-stored canonical value (preserving original casing/spacing).
     * Falls back to the raw token if no match is found.
     */
    private function resolveLocationToken(string $token): string
    {
        $normalized = strtolower(preg_replace('/[-_\s]/', '', $token));

        return Task::visibleTo($this->userId)
            ->outsideTemplates()
            ->whereNotNull('location')
            ->where('location', '!=', '')
            ->whereRaw("LOWER(REPLACE(REPLACE(REPLACE(location, ' ', ''), '-', ''), '_', '')) = ?", [$normalized])
            ->value('location') ?? $token;
    }

    private function parseAssigneeTokens(string $name, QuickAddTokens $tokens): string
    {
        if (!preg_match_all('/&([\w-]+)/', $name, $matches)) {
            return $name;
        }

        foreach ($matches[1] as $userSlug) {
            $normalized = strtolower(preg_replace('/[-_]/', '', $userSlug));
            $user = User::whereNull('email_enabled_at')
                ->where(function ($q) use ($normalized, $userSlug) {
                    $q->whereRaw("LOWER(REPLACE(REPLACE(REPLACE(name, ' ', ''), '-', ''), '_', '')) = ?", [$normalized])
                      ->orWhereRaw('LOWER(name) LIKE ?', [strtolower($userSlug) . '%']);
                })
                ->first();

            if ($user) {
                $tokens->assigneeIds[]   = $user->id;
                $tokens->assigneeNames[] = $user->name;
                $name = trim(preg_replace('/&' . preg_quote($userSlug, '/') . '(?=\s|$)/', '', $name));
            } else {
                // Unmatched: leave the &token in the name as-is.
                $tokens->unknownAssignees[] = '&' . $userSlug;
            }
        }

        $tokens->assigneeIds = array_values(array_unique($tokens->assigneeIds));

        return $name;
    }
}
