<?php

namespace App\Http\Controllers;

use App\Exceptions\InvalidTemplateException;
use App\Models\Project;
use App\Models\ProjectTemplate;
use App\Models\ScheduledProject;
use App\Services\DateParser;
use App\Services\ProjectTemplateArchive;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class ProjectTemplateController extends Controller
{
    public function __construct(private ProjectTemplateArchive $archive)
    {
    }

    /**
     * List templates: the current user's own templates, then public templates
     * created by others.
     */
    public function index(Request $request)
    {
        $user = $request->user();

        $myTemplates = ProjectTemplate::where('created_by', $user->id)
            ->orderBy('name')
            ->with('creator')
            ->get();

        $publicTemplates = ProjectTemplate::where('is_public', true)
            ->where('created_by', '!=', $user->id)
            ->orderBy('name')
            ->with('creator')
            ->get();

        return view('templates.index', compact('myTemplates', 'publicTemplates'));
    }

    /**
     * Save a project as a stored template (zip on disk + DB record).
     * Only the project creator may do this.
     */
    public function store(Request $request, Project $project)
    {
        $request->validate([
            'template_name'        => 'required|string|max:255',
            'template_description' => 'nullable|string|max:1000',
            'is_public'            => 'nullable|boolean',
        ]);

        $user = $request->user();

        if ($project->user_id !== $user->id) {
            abort(403, 'Only the project creator can save it as a template.');
        }

        $zipPath = $this->archive->build($project);

        if ($zipPath === false) {
            return back()->with('error', 'Failed to create template archive. Please ensure the zip utility is installed on the server.');
        }

        // Move zip to permanent storage
        $storedFilename = 'project-templates/' . uniqid('tpl_') . '.zip';
        Storage::disk('private')->put($storedFilename, file_get_contents($zipPath));
        unlink($zipPath);

        ProjectTemplate::create([
            'name'        => $request->template_name,
            'description' => $request->template_description,
            'filename'    => $storedFilename,
            'created_by'  => $user->id,
            'is_public'   => $request->boolean('is_public', false),
        ]);

        return back()->with('status', 'Template "' . $request->template_name . '" saved successfully.');
    }

    /**
     * Create a new project from a stored template.
     */
    public function createFromTemplate(Request $request, ProjectTemplate $template)
    {
        $request->validate([
            'project_name' => 'required|string|max:255',
            'start_date'   => 'nullable|string|max:255',
        ]);

        $user = $request->user();

        if (!$template->is_public && $template->created_by !== $user->id) {
            abort(403, 'You do not have access to this template.');
        }

        // Parse start_date (natural language or blank = today)
        $startDate = null;
        if ($request->filled('start_date')) {
            $parsed = (new DateParser())->parseTaskInput($request->start_date);
            $startDate = $parsed['date'] ?? null;
        }

        $today = now()->toDateString();

        // If start_date is in the future, schedule instead of creating immediately
        if ($startDate && $startDate > $today) {
            ScheduledProject::create([
                'template_id'  => $template->id,
                'user_id'      => $user->id,
                'project_name' => $request->project_name,
                'start_date'   => $startDate,
            ]);

            $formatted = Carbon::parse($startDate)->format('l, F j, Y');
            return redirect()->route('templates.index')
                ->with('status', 'Project "' . $request->project_name . '" scheduled to be created on ' . $formatted . '.');
        }

        // Create immediately
        $zipPath = Storage::disk('private')->path($template->filename);

        if (!file_exists($zipPath)) {
            return back()->with('error', 'Template file not found on the server.');
        }

        try {
            $project = $this->archive->createProject($zipPath, $request->project_name, $user, $template->id, $template->name);
        } catch (InvalidTemplateException $e) {
            return back()->with('error', $e->getMessage());
        } catch (\Throwable $e) {
            report($e);
            return back()->with('error', 'Failed to create project from template, so nothing was created.');
        }

        return redirect()->route('projects.show', $project)
            ->with('status', 'Project created from template "' . $template->name . '".');
    }

    /**
     * Save an uploaded project template zip directly as a stored template,
     * without going through the intermediate "import as project, then save
     * as template" round trip. The zip must be in the same format produced
     * by ProjectTemplateArchive::build() (a template.json with
     * template_type "project").
     */
    public function importZip(Request $request)
    {
        $request->validate([
            'template_file'         => 'required|file|mimes:zip|max:10240',
            'template_name'         => 'required|string|max:255',
            'template_description'  => 'nullable|string|max:1000',
            'is_public'             => 'nullable|boolean',
        ], [
            // PHP rejects files over upload_max_filesize before Laravel sees them,
            // and the stock "failed to upload" message doesn't say why.
            'template_file.uploaded' => 'The template file failed to upload. It may be larger than the server\'s upload limit (upload_max_filesize = ' . ini_get('upload_max_filesize') . ').',
        ]);

        $user = $request->user();

        $zipPath = $request->file('template_file')->path();

        try {
            $this->archive->readManifest($zipPath);
        } catch (InvalidTemplateException $e) {
            return back()->with('error', $e->getMessage());
        }

        // The uploaded zip is already in the right format — store it as-is.
        $storedFilename = 'project-templates/' . uniqid('tpl_') . '.zip';
        Storage::disk('private')->put($storedFilename, file_get_contents($zipPath));

        ProjectTemplate::create([
            'name'        => $request->template_name,
            'description' => $request->template_description,
            'filename'    => $storedFilename,
            'created_by'  => $user->id,
            'is_public'   => $request->boolean('is_public', false),
        ]);

        return back()->with('status', 'Template "' . $request->template_name . '" imported successfully.');
    }

    /**
     * Rename a stored template. Only the creator may rename.
     */
    public function updateName(Request $request, ProjectTemplate $template)
    {
        if ($template->created_by !== $request->user()->id) {
            return response()->json(['success' => false, 'message' => 'Only the template creator can rename it.'], 403);
        }

        $name = trim((string) $request->input('name'));

        if ($name === '') {
            return response()->json(['success' => false, 'message' => 'Name cannot be empty'], 400);
        }
        if (strlen($name) > 255) {
            return response()->json(['success' => false, 'message' => 'Name cannot exceed 255 characters.'], 422);
        }

        $template->update(['name' => $name]);

        return response()->json(['success' => true, 'name' => $template->name]);
    }

    /**
     * Delete a stored template (zip + DB record). Only the creator may delete.
     */
    public function destroy(Request $request, ProjectTemplate $template)
    {
        if ($template->created_by !== $request->user()->id) {
            abort(403, 'You can only delete your own templates.');
        }

        if (Storage::disk('private')->exists($template->filename)) {
            Storage::disk('private')->delete($template->filename);
        }

        $template->delete();

        return back()->with('status', 'Template deleted.');
    }
}
