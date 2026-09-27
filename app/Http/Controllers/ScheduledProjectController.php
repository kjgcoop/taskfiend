<?php

namespace App\Http\Controllers;

use App\Exceptions\InvalidTemplateException;
use App\Models\ScheduledProject;
use App\Services\ProjectTemplateArchive;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class ScheduledProjectController extends Controller
{
    public function index(Request $request)
    {
        $scheduled = ScheduledProject::where('user_id', $request->user()->id)
            ->where('is_created', false)
            ->with('template')
            ->orderBy('start_date')
            ->get();

        return view('scheduled-projects.index', compact('scheduled'));
    }

    public function destroy(Request $request, ScheduledProject $scheduledProject)
    {
        if ($scheduledProject->user_id !== $request->user()->id) {
            abort(403);
        }

        $scheduledProject->delete();

        return back()->with('status', 'Scheduled project cancelled.');
    }

    public function update(Request $request, ScheduledProject $scheduledProject)
    {
        if ($scheduledProject->user_id !== $request->user()->id) {
            abort(403);
        }

        if ($scheduledProject->is_created) {
            return response()->json([
                'success' => false,
                'message' => 'This project has already been created and can no longer be renamed here.',
            ], 422);
        }

        $request->validate([
            'project_name' => 'required|string|max:255',
        ]);

        $scheduledProject->update(['project_name' => $request->project_name]);

        return response()->json(['success' => true, 'project_name' => $scheduledProject->project_name]);
    }

    public function createNow(Request $request, ScheduledProject $scheduledProject)
    {
        if ($scheduledProject->user_id !== $request->user()->id) {
            abort(403);
        }

        if ($scheduledProject->is_created) {
            return back()->with('error', 'This project has already been created.');
        }

        $template = $scheduledProject->template;
        if (!$template) {
            return back()->with('error', 'The template for this scheduled project no longer exists.');
        }

        $zipPath = Storage::disk('private')->path($template->filename);
        if (!file_exists($zipPath)) {
            return back()->with('error', 'Template file not found on the server.');
        }

        try {
            $project = app(ProjectTemplateArchive::class)
                ->createProject($zipPath, $scheduledProject->project_name, $request->user(), $template->id, $template->name);
        } catch (InvalidTemplateException $e) {
            return back()->with('error', $e->getMessage());
        } catch (\Throwable $e) {
            report($e);
            return back()->with('error', 'Failed to create project from template, so nothing was created.');
        }

        $scheduledProject->delete();

        return redirect()->route('projects.show', $project)
            ->with('status', 'Project "' . $project->name . '" created.');
    }
}
