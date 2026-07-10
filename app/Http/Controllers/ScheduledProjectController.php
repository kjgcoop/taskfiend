<?php

namespace App\Http\Controllers;

use App\Models\ScheduledProject;
use Illuminate\Http\Request;

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
}
