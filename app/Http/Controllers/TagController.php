<?php

namespace App\Http\Controllers;

use App\Models\Tag;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class TagController extends Controller
{
    public function index()
    {
        $tags = Tag::withCount('tasks')
            ->orderBy('tag_name')
            ->get();

        return view('tags.index', compact('tags'));
    }

    public function create()
    {
        return view('tags.create');
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'tag_name' => 'required|string|max:255|unique:tags,tag_name',
            'color' => 'required|string|size:7|regex:/^#[0-9A-Fa-f]{6}$/',
        ]);

        $tag = Tag::create($validated);

        $this->logChange($tag, 'created tag');

        return redirect()->route('tags.show', $tag)
            ->with('success', 'Tag created successfully.');
    }

    public function show(Tag $tag)
    {
        $tasks = $tag->tasks()
            ->where(function ($q) {
                $q->where('creator_id', Auth::id())
                  ->orWhereHas('assignees', function ($query) {
                      $query->where('users.id', Auth::id());
                  });
            })
            ->where('status', '!=', 'archived')
            ->where('status', '!=', 'done')
            ->with(['creator', 'project', 'assignees', 'attachments', 'comments'])
            ->orderBy('datetime')
            ->get();

        $tag->load('changeLogs.user');

        return view('tags.show', compact('tag', 'tasks'));
    }

    public function edit(Tag $tag)
    {
        return view('tags.edit', compact('tag'));
    }

    public function update(Request $request, Tag $tag)
    {
        $validated = $request->validate([
            'tag_name' => 'required|string|max:255|unique:tags,tag_name,' . $tag->id,
            'color' => 'required|string|size:7|regex:/^#[0-9A-Fa-f]{6}$/',
        ]);

        $changes = [];
        foreach (['tag_name', 'color'] as $field) {
            if ($tag->$field != $validated[$field]) {
                $changes[$field] = ['old' => $tag->$field, 'new' => $validated[$field]];
                $tag->$field = $validated[$field];
            }
        }

        $tag->save();

        foreach ($changes as $field => $change) {
            $this->logChange($tag, "changed {$field} from {$change['old']} to {$change['new']}");
        }

        return redirect()->route('tags.show', $tag)
            ->with('success', 'Tag updated successfully.');
    }

    public function quickStore(Request $request)
    {
        $validated = $request->validate([
            'tag_name' => 'required|string|max:255|unique:tags,tag_name',
        ]);

        $colors = ['#EF4444', '#F59E0B', '#10B981', '#3B82F6', '#8B5CF6', '#EC4899', '#06B6D4', '#F97316'];
        $color = $colors[array_rand($colors)];

        $tag = Tag::create([
            'tag_name' => $validated['tag_name'],
            'color' => $color,
        ]);

        $this->logChange($tag, 'created tag');

        return response()->json([
            'id' => $tag->id,
            'tag_name' => $tag->tag_name,
            'color' => $tag->color,
        ]);
    }

    public function destroy(Tag $tag)
    {
        $tag->tasks()->detach();

        $this->logChange($tag, 'deleted tag');

        $tag->delete();

        return redirect()->route('tags.index')
            ->with('success', 'Tag deleted successfully.');
    }

    protected function logChange(Tag $tag, string $description)
    {
        $tag->changeLogs()->create([
            'date' => now(),
            'user_id' => Auth::id(),
            'entity_type' => 'tags',
            'entity_id' => $tag->id,
            'description' => $description,
        ]);
    }
}
