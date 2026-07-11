<?php

namespace App\Services;

use App\Models\Project;

/**
 * Result of QuickAddParser::parse() — the task name with matched tokens
 * stripped, plus everything the tokens resolved to.
 */
class QuickAddTokens
{
    /** Task name with all matched tokens removed and whitespace collapsed. */
    public string $name = '';

    /** Project matched from a #token, if any. */
    public ?Project $project = null;

    /**
     * True when the #token was accounted for — either matched to a project
     * here, or pre-resolved by the caller (quick-add autocomplete).
     */
    public bool $projectResolved = false;

    /** @var int[] Tag IDs matched from @tokens. */
    public array $tagIds = [];

    /** @var string[] Matched tag names, in token order (for previews). */
    public array $tagNames = [];

    /** Location resolved from a +/++ token, if any. */
    public ?string $location = null;

    /** True when the location came from a ++token (render as map link). */
    public bool $showMap = false;

    /** @var int[] User IDs matched from &tokens. */
    public array $assigneeIds = [];

    /** @var string[] Matched user names, in token order (for previews). */
    public array $assigneeNames = [];

    /** @var string[] Unmatched &tokens, including the & prefix (for previews). */
    public array $unknownAssignees = [];
}
