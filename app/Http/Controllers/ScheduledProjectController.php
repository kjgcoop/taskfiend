<?php

namespace App\Http\Controllers;

use App\Models\ScheduledProject;
use Illuminate\Http\Request;

class ScheduledProjectController extends Controller
{
    public function index(Request $request)
    {
        $scheduled = ScheduledProject::where('user_id', $request->user()->id)
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
}
