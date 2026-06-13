<x-app-layout>
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <div class="flex-1 min-w-0 mr-4"
                 x-data="taskShowNameEditor"
                 data-task-name="{{ $task->name }}"
                 data-task-id="{{ $task->id }}">
                <div x-show="!editing">
                    <h2 @if(!$isInactive) @click="startEditName()" @endif
                        class="font-semibold text-xl text-gray-100 leading-tight task-title {{ !$isInactive ? 'cursor-pointer hover:text-gray-300' : '' }}">
                        {!! render_title($task->name) !!}
                    </h2>
                </div>
                @if(!$isInactive)
                <div x-show="editing" class="flex items-center gap-2" style="display:none">
                    <input type="text" x-model="name" x-ref="nameInput"
                           @keydown.enter="save()"
                           @keydown.escape="cancel()"
                           class="text-xl font-semibold rounded-md bg-gray-700 border-gray-600 text-gray-100 shadow-sm focus:border-blue-500 focus:ring-blue-500 flex-1 min-w-0 px-2 py-1">
                    <button @click="save()" class="px-3 py-1 bg-blue-600 text-white text-sm rounded hover:bg-blue-700 whitespace-nowrap flex-shrink-0">Save</button>
                    <button @click="cancel()" class="px-3 py-1 bg-gray-700 text-gray-300 text-sm rounded hover:bg-gray-600 whitespace-nowrap flex-shrink-0">Cancel</button>
                </div>
                @endif
                <div class="flex items-center gap-1.5 mt-0.5">
                    <span class="text-sm text-gray-600">#{{ $task->id }}</span>
                    <button x-data="copyButton"
                            @click="copy('{{ route('tasks.show', $task) }}')"
                            title="Copy link"
                            class="p-0.5 text-gray-600 hover:text-gray-300 rounded transition">
                        <svg x-show="!copied" class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                  d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"/>
                        </svg>
                        <svg x-show="copied" class="w-3.5 h-3.5 text-green-400" fill="none" stroke="currentColor" viewBox="0 0 24 24" style="display:none">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                        </svg>
                    </button>
                </div>
            </div>
            @if(!$isInactive)
            <form method="POST" action="{{ route('tasks.duplicate', $task) }}">
                @csrf
                <button type="submit"
                        class="inline-flex items-center px-3 py-1.5 bg-gray-700 text-gray-300 text-sm rounded hover:bg-gray-600 hover:text-gray-100 transition">
                    Duplicate
                </button>
            </form>
            @endif
        </div>
    </x-slot>

    <!-- Breadcrumb for parent hierarchy -->
    @if($task->parent_id)
    <div class="py-4 bg-black">
        <div class="max-w-4xl mx-auto sm:px-6 lg:px-8">
            <nav class="flex items-center space-x-2 text-sm text-gray-400">
                @foreach($task->getAllAncestors()->reverse() as $ancestor)
                    <a href="{{ route('tasks.show', $ancestor) }}"
                       class="hover:text-gray-100 transition">
                        {{ $ancestor->name }}
                    </a>
                    <span class="text-gray-600">/</span>
                @endforeach
                <span class="text-gray-200 font-semibold task-title">{!! render_title($task->name) !!}</span>
            </nav>
        </div>
    </div>
    @endif

    <div class="py-12" x-data="taskEditor({{ $task->id }})">
        <div class="max-w-4xl mx-auto sm:px-6 lg:px-8 space-y-6">
            <!-- Task Details -->
            <div class="bg-[#202020] border border-gray-700 overflow-hidden shadow-sm sm:rounded-lg p-6">
                @php $rescheduleCount = $task->rescheduleCount(); @endphp
                @if($rescheduleCount > 0)
                <div class="mb-4 flex items-center gap-2">
                    <span class="inline-flex items-center px-2 py-1 rounded text-sm font-medium bg-amber-900 bg-opacity-40 text-amber-400 border border-amber-700"
                          title="This task has been rescheduled {{ $rescheduleCount }} time{{ $rescheduleCount === 1 ? '' : 's' }}">
                        ↻ Rescheduled {{ $rescheduleCount }} {{ $rescheduleCount === 1 ? 'time' : 'times' }}
                    </span>
                </div>
                @endif

                @if($isInactive)
                <div class="mb-4 p-3 bg-yellow-900 bg-opacity-20 border border-yellow-600 rounded-lg">
                    <p class="text-sm text-yellow-300">
                        @if(in_array($task->status, ['done', 'archived']))
                            <span class="font-semibold">Task is {{ $task->status }}.</span>
                            This task is read-only.
                        @else
                            <span class="font-semibold">Project is inactive.</span>
                            This task is read-only. To re-enable editing, change the project status back to <strong>Incomplete</strong>.
                        @endif
                    </p>
                </div>
                @endif

                @if($task->recurrence_pattern && $task->status !== 'done' && $task->status !== 'archived')
                <div class="mb-4 p-3 bg-purple-900 bg-opacity-20 border border-purple-500 rounded-lg">
                    <p class="text-sm text-purple-300">
                        <span class="font-semibold">🔄 Recurring Task</span> ({{ $task->recurrence_pattern }}{{ $task->recurrence_floating ? ', floating' : '' }})
                    </p>
                    <p class="text-xs text-purple-400 mt-1">
                        <strong>Completing this instance:</strong> Marking this task as "done" will complete ONLY this instance
                        @if($task->children->count() > 0)
                            (and all {{ $task->children->count() }} subtask(s))
                        @endif
                        and automatically create a new one for the next occurrence.
                    </p>
                    <p class="text-xs text-purple-400 mt-1">
                        <strong>To stop the entire series:</strong> Remove the recurrence pattern below before marking done or archived.
                    </p>
                </div>
                @endif

                <div class="grid grid-cols-2 gap-4 mb-4">
                    <!-- Status -->
                    <div>
                        <span class="text-sm font-medium text-gray-500">Status</span>
                        <div @if(!$isInactive) @click="startEdit('status')" @endif x-show="!editing.status" class="mt-1 p-2 rounded {{ !$isInactive ? 'cursor-pointer hover:bg-gray-700' : '' }}">
                            <span class="inline-block px-2 py-1 text-xs rounded
                                @if($task->status === 'done') bg-green-100 text-green-800
                                @elseif($task->status === 'archived') bg-gray-100 text-gray-800
                                @else bg-blue-100 text-blue-800 @endif">
                                {{ ucfirst($task->status) }}
                            </span>
                            @if($task->recurrence_pattern && $task->status !== 'done')
                                <span class="ml-2 text-xs text-purple-400">🔄</span>
                            @endif
                        </div>
                        @if(!$isInactive)
                        <div x-show="editing.status" class="mt-1">
                            <select x-model="fields.status"
                                    @keydown.enter="saveField('status')"
                                    @keydown.escape="cancelEdit('status')"
                                    class="w-full rounded-md bg-gray-700 border-gray-600 text-gray-100 placeholder-gray-500 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                                <option value="incomplete">Incomplete</option>
                                <option value="done">Done</option>
                                <option value="archived">Archived</option>
                            </select>
                            <div class="flex gap-2 mt-2">
                                <button @click="saveField('status')"
                                        class="px-3 py-1 bg-blue-600 text-white text-sm rounded hover:bg-blue-700">
                                    Save
                                </button>
                                <button @click="cancelEdit('status')"
                                        class="px-3 py-1 bg-gray-700 text-gray-300 text-sm rounded hover:bg-gray-600">
                                    Cancel
                                </button>
                            </div>
                        </div>
                        @endif
                    </div>

                    <!-- Created By (read-only) -->
                    <div>
                        <span class="text-sm font-medium text-gray-500">Created By</span>
                        <p class="mt-1 text-gray-300">{{ $task->creator->name }}</p>
                    </div>

                    <!-- Date -->
                    <div>
                        <span class="text-sm font-medium text-gray-500">Date</span>
                        <div @if(!$isInactive) @click="startEditDate()" @endif x-show="!editing.date" class="mt-1 p-2 rounded min-h-[40px] {{ !$isInactive ? 'cursor-pointer hover:bg-gray-700' : '' }}">
                            @if($task->date)
                                <p class="text-gray-300">{{ \Carbon\Carbon::parse($task->date)->format('l, F j, Y') }}</p>
                            @else
                                <p class="text-gray-400 italic">{{ $isInactive ? 'No date set' : 'Click to set date' }}</p>
                            @endif
                        </div>
                        @if(!$isInactive)
                        <div x-show="editing.date" class="mt-1">
                            <div class="flex gap-2 items-start">
                                <div class="flex-1">
                                    <input type="text" x-model="dateText" x-ref="dateInput"
                                           @input.debounce.300ms="previewDate()"
                                           @keydown.enter="saveDateField()"
                                           @keydown.escape="cancelEdit('date')"
                                           placeholder="tomorrow, next friday, march 15, 3/15..."
                                           class="w-full rounded-md bg-gray-700 border-gray-600 text-gray-100 placeholder-gray-500 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                                </div>
                                <div class="relative flex-shrink-0">
                                    <button @click="$refs.datePicker.showPicker()" type="button"
                                            class="p-2 bg-gray-700 border border-gray-600 rounded-md hover:bg-gray-600 text-gray-400 hover:text-gray-200"
                                            title="Open calendar">
                                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                  d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
                                        </svg>
                                    </button>
                                    <input type="date" x-ref="datePicker"
                                           @change="pickDate($event.target.value)"
                                           :min="new Date().toLocaleDateString('en-CA')"
                                           class="absolute inset-0 opacity-0 w-full h-full cursor-pointer">
                                </div>
                            </div>
                            <div x-show="datePreview" :class="datePast ? 'text-red-400' : 'text-green-400'" class="mt-1 text-xs flex flex-wrap items-baseline gap-x-1">
                                <span x-text="datePreview"></span>
                                <span x-show="projects && projects.length > 0" class="flex flex-wrap items-baseline gap-x-1">
                                    <span class="text-gray-500">&mdash;</span>
                                    <span x-text="projectTaskCount + (projectTaskCount === 1 ? ' task' : ' tasks')"></span>
                                    <template x-for="proj in (projects || [])" :key="proj.name">
                                        <span class="relative group inline-flex items-baseline gap-x-0.5">
                                            <span class="text-gray-500">·</span>
                                            <span class="underline decoration-dotted cursor-help" x-text="proj.name + ' \u00d7' + proj.count"></span>
                                            <div class="absolute hidden group-hover:block bottom-full left-0 mb-1 bg-gray-900 border border-gray-600 rounded p-2 text-gray-200 z-50 shadow-lg min-w-max max-w-xs">
                                                <template x-for="(task, idx) in proj.tasks" :key="idx">
                                                    <div class="flex gap-1.5 py-0.5">
                                                        <span class="flex-shrink-0 text-gray-500">·</span>
                                                        <span x-text="task"></span>
                                                    </div>
                                                </template>
                                                <div x-show="proj.more > 0" x-text="'+' + proj.more + ' more'" class="text-gray-400 italic mt-1 py-0.5"></div>
                                            </div>
                                        </span>
                                    </template>
                                </span>
                            </div>
                            <div x-show="dateError" class="mt-1 text-xs text-red-400" x-text="dateError"></div>
                            <p class="mt-1 text-xs text-gray-500">Type a date or click the calendar icon. Accepts: tomorrow, next friday, march 15, 3/15, 2026-03-15</p>
                            <div class="flex gap-2 mt-2">
                                <button @click="saveDateField()"
                                        class="px-3 py-1 bg-blue-600 text-white text-sm rounded hover:bg-blue-700">
                                    Save
                                </button>
                                <button @click="cancelEdit('date')"
                                        class="px-3 py-1 bg-gray-700 text-gray-300 text-sm rounded hover:bg-gray-600">
                                    Cancel
                                </button>
                                @if($task->date)
                                <button @click="clearDate()"
                                        class="px-3 py-1 bg-gray-700 text-red-400 text-sm rounded hover:bg-gray-600">
                                    Clear
                                </button>
                                @endif
                            </div>
                        </div>
                        @endif
                    </div>

                    <!-- Time -->
                    <div>
                        <span class="text-sm font-medium text-gray-500">Time</span>
                        <div @if(!$isInactive) @click="startEdit('time')" @endif x-show="!editing.time" class="mt-1 p-2 rounded min-h-[40px] {{ !$isInactive ? 'cursor-pointer hover:bg-gray-700' : '' }}">
                            @if($task->time)
                                <p class="text-gray-300">{{ \Carbon\Carbon::parse($task->time)->format('g:i A') }}</p>
                            @else
                                <p class="text-gray-400 italic">{{ $isInactive ? 'No time set' : 'Click to set time (optional)' }}</p>
                            @endif
                        </div>
                        @if(!$isInactive)
                        <div x-show="editing.time" class="mt-1">
                            <input type="time" x-model="fields.time"
                                   @keydown.enter="saveField('time')"
                                   @keydown.escape="cancelEdit('time')"
                                   class="w-full rounded-md bg-gray-700 border-gray-600 text-gray-100 placeholder-gray-500 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                            <div class="flex gap-2 mt-2">
                                <button @click="saveField('time')"
                                        class="px-3 py-1 bg-blue-600 text-white text-sm rounded hover:bg-blue-700">
                                    Save
                                </button>
                                <button @click="cancelEdit('time')"
                                        class="px-3 py-1 bg-gray-700 text-gray-300 text-sm rounded hover:bg-gray-600">
                                    Cancel
                                </button>
                                @if($task->time)
                                <button @click="fields.time = ''; saveField('time')"
                                        class="px-3 py-1 bg-gray-700 text-red-400 text-sm rounded hover:bg-gray-600">
                                    Clear
                                </button>
                                @endif
                            </div>
                        </div>
                        @endif
                    </div>

                    <!-- Duration -->
                    <div>
                        <span class="text-sm font-medium text-gray-500">Duration</span>
                        <div @if(!$isInactive) @click="startEdit('duration_minutes')" @endif x-show="!editing.duration_minutes" class="mt-1 p-2 rounded min-h-[40px] {{ !$isInactive ? 'cursor-pointer hover:bg-gray-700' : '' }}">
                            @if($task->duration_minutes)
                                <p class="text-gray-300">{{ \App\Models\Task::formatDuration($task->duration_minutes) }}</p>
                            @else
                                <p class="text-gray-400 italic">{{ $isInactive ? 'No duration set' : 'Click to set duration (optional)' }}</p>
                            @endif
                        </div>
                        @if(!$isInactive)
                        <div x-show="editing.duration_minutes" class="mt-1">
                            <input type="text" x-model="fields.duration_minutes"
                                   @keydown.enter="saveField('duration_minutes')"
                                   @keydown.escape="cancelEdit('duration_minutes')"
                                   placeholder="e.g. 1h 30m, 90, 2h"
                                   class="w-full rounded-md bg-gray-700 border-gray-600 text-gray-100 placeholder-gray-500 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                            <p class="mt-1 text-xs text-gray-500">Duration (e.g. 90, 1h 30m, 2h). Sizes the block in agenda view.</p>
                            <div class="flex gap-2 mt-2">
                                <button @click="saveField('duration_minutes')"
                                        class="px-3 py-1 bg-blue-600 text-white text-sm rounded hover:bg-blue-700">
                                    Save
                                </button>
                                <button @click="cancelEdit('duration_minutes')"
                                        class="px-3 py-1 bg-gray-700 text-gray-300 text-sm rounded hover:bg-gray-600">
                                    Cancel
                                </button>
                                @if($task->duration_minutes)
                                <button @click="fields.duration_minutes = ''; saveField('duration_minutes')"
                                        class="px-3 py-1 bg-gray-700 text-red-400 text-sm rounded hover:bg-gray-600">
                                    Clear
                                </button>
                                @endif
                            </div>
                        </div>
                        @endif
                    </div>

                    <!-- Location -->
                    <div>
                        <span class="text-sm font-medium text-gray-500">Location</span>
                        <div @if(!$isInactive) @click="startEdit('location')" @endif x-show="!editing.location" class="mt-1 p-2 rounded min-h-[40px] {{ !$isInactive ? 'cursor-pointer hover:bg-gray-700' : '' }}">
                            @if($task->location)
                                @if($task->show_map)
                                    @php $mapUrl = sprintf(config('taskfiend.maps_url_template', 'https://maps.google.com/?q=%s'), urlencode($task->location)); @endphp
                                    <a href="{{ $mapUrl }}" target="_blank" rel="noopener"
                                       @click.stop
                                       class="inline-flex items-center gap-1 text-orange-400 hover:underline">{{ $task->location }}<svg class="inline w-3 h-3 opacity-70 flex-shrink-0" viewBox="0 0 10 10" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M1 9L9 1M9 1H4M9 1V6"/></svg></a>
                                @else
                                    <p class="text-gray-300">{{ $task->location }}</p>
                                @endif
                            @else
                                <p class="text-gray-400 italic">{{ $isInactive ? 'No location set' : 'Click to set location (optional)' }}</p>
                            @endif
                        </div>
                        @if(!$isInactive)
                        <div x-show="editing.location" class="mt-1">
                            <input type="text" x-model="fields.location"
                                   @keydown.enter="saveField('location')"
                                   @keydown.escape="cancelEdit('location')"
                                   placeholder="e.g., Home, Conference Room B, 123 Main St"
                                   class="w-full rounded-md bg-gray-700 border-gray-600 text-gray-100 placeholder-gray-500 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                            <label class="flex items-center gap-2 mt-2 text-sm text-gray-400 cursor-pointer select-none">
                                <input type="checkbox" x-model="fields.show_map"
                                       @change="saveField('show_map')"
                                       class="rounded border-gray-600 bg-gray-700 text-orange-500 focus:ring-orange-500">
                                Show as map link
                            </label>
                            <div class="flex gap-2 mt-2">
                                <button @click="saveField('location')"
                                        class="px-3 py-1 bg-blue-600 text-white text-sm rounded hover:bg-blue-700">
                                    Save
                                </button>
                                <button @click="cancelEdit('location')"
                                        class="px-3 py-1 bg-gray-700 text-gray-300 text-sm rounded hover:bg-gray-600">
                                    Cancel
                                </button>
                                @if($task->location)
                                <button @click="fields.location = ''; saveField('location')"
                                        class="px-3 py-1 bg-gray-700 text-red-400 text-sm rounded hover:bg-gray-600">
                                    Clear
                                </button>
                                @endif
                            </div>
                        </div>
                        @endif
                    </div>

                    <!-- Project -->
                    <div>
                        <span class="text-sm font-medium text-gray-500">Project</span>
                        @if($task->project)
                            <a href="{{ route('projects.show', $task->project) }}" class="ml-1 text-gray-600 hover:text-gray-300 text-xs" title="Go to project">↗</a>
                        @endif
                        <div @if(!$isInactive) @click="startEdit('project_id')" @endif x-show="!editing.project_id" class="mt-1 p-2 rounded {{ !$isInactive ? 'cursor-pointer hover:bg-gray-700' : '' }}">
                            <p class="text-gray-300">{{ $task->project ? $task->project->name : 'Inbox' }}</p>
                        </div>
                        @if(!$isInactive)
                        <div x-show="editing.project_id" class="mt-1">
                            <select x-model="fields.project_id"
                                    @change="saveField('project_id')"
                                    @keydown.escape="cancelEdit('project_id')"
                                    class="w-full rounded-md bg-gray-700 border-gray-600 text-gray-100 placeholder-gray-500 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                                @foreach($projects as $project)
                                    <option value="{{ $project->id }}">{{ $project->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        @endif
                    </div>

                    <!-- Parent Task -->
                    <div>
                        <span class="text-sm font-medium text-gray-500">Parent Task</span>
                        <div @if(!$isInactive) @click="startEdit('parent_id')" @endif x-show="!editing.parent_id" class="mt-1 p-2 rounded {{ !$isInactive ? 'cursor-pointer hover:bg-gray-700' : '' }}">
                            <p class="text-gray-300">
                                @if($task->parent)
                                    {{ $task->parent->name }}
                                @else
                                    None (Top-level task)
                                @endif
                            </p>
                        </div>
                        @if(!$isInactive)
                        <div x-show="editing.parent_id" class="mt-1" @click.outside="parentOpen = false">
                            <div class="relative">
                                <input type="text"
                                       x-model="parentSearch"
                                       @input="fields.parent_id = ''; parentOpen = true"
                                       @focus="parentOpen = true"
                                       @keydown.escape="parentOpen = false"
                                       @keydown.enter.prevent="parentFiltered.length > 0 && selectParent(parentFiltered[0])"
                                       placeholder="Search for a parent task…"
                                       autocomplete="off"
                                       class="w-full rounded-md bg-gray-700 border-gray-600 text-gray-100 placeholder-gray-500 shadow-sm focus:border-blue-500 focus:ring-blue-500 pr-8">
                                <button type="button" x-show="fields.parent_id || parentSearch"
                                        @click="clearParent()"
                                        class="absolute right-2 top-1/2 -translate-y-1/2 text-gray-400 hover:text-gray-100 text-xl leading-none">
                                    &times;
                                </button>
                                <div x-show="parentOpen" x-cloak
                                     class="absolute z-50 w-full mt-1 bg-gray-800 border border-gray-600 rounded-md shadow-lg max-h-60 overflow-y-auto">
                                    <div @mousedown.prevent="clearParent()"
                                         class="px-3 py-2 text-sm text-gray-400 cursor-pointer hover:bg-gray-700 border-b border-gray-700">
                                        None (Top-level task)
                                    </div>
                                    <template x-for="task in parentFiltered" :key="task.id">
                                        <div @mousedown.prevent="selectParent(task)"
                                             class="px-3 py-2 text-sm text-gray-100 cursor-pointer hover:bg-gray-700"
                                             :class="{ 'bg-gray-600': fields.parent_id == task.id }">
                                            <span x-text="task.name"></span>
                                        </div>
                                    </template>
                                    <div x-show="parentSearch && parentFiltered.length === 0"
                                         class="px-3 py-2 text-sm text-gray-500 italic">
                                        No matching tasks found
                                    </div>
                                </div>
                            </div>
                            <div class="flex gap-2 mt-2">
                                <button @click="saveField('parent_id')"
                                        class="px-3 py-1 bg-blue-600 text-white text-sm rounded hover:bg-blue-700">
                                    Save
                                </button>
                                <button @click="cancelEdit('parent_id')"
                                        class="px-3 py-1 bg-gray-700 text-gray-300 text-sm rounded hover:bg-gray-600">
                                    Cancel
                                </button>
                            </div>
                        </div>
                        @endif
                    </div>
                </div>

                <!-- Description -->
                <div class="mt-4">
                    <span class="text-sm font-medium text-gray-500">Description</span>
                    <div @if(!$isInactive) @click="$event.target.closest('a') || startEdit('description')" @endif x-show="!editing.description" class="mt-1 p-2 rounded min-h-[40px] {{ !$isInactive ? 'cursor-pointer hover:bg-gray-700' : '' }}">
                        <div class="markdown-body" x-show="fields.description" x-ref="descHtml"></div>
                        <p x-show="!fields.description" class="text-gray-400 italic">{{ $isInactive ? 'No description' : 'Click to add description' }}</p>
                    </div>
                    @if(!$isInactive)
                    <div x-show="editing.description" class="mt-1">
                        <textarea x-model="fields.description" rows="3"
                                  @keydown.enter.ctrl="saveField('description')"
                                  @keydown.escape="cancelEdit('description')"
                                  class="w-full rounded-md bg-gray-700 border-gray-600 text-gray-100 placeholder-gray-500 shadow-sm focus:border-blue-500 focus:ring-blue-500"></textarea>
                        <div class="flex gap-2 mt-2">
                            <button @click="saveField('description')"
                                    class="px-3 py-1 bg-blue-600 text-white text-sm rounded hover:bg-blue-700">
                                Save
                            </button>
                            <button @click="cancelEdit('description')"
                                    class="px-3 py-1 bg-gray-700 text-gray-300 text-sm rounded hover:bg-gray-600">
                                Cancel
                            </button>
                        </div>
                    </div>
                    @endif
                </div>

                <!-- Recurrence Pattern -->
                <div class="mt-4">
                    <span class="text-sm font-medium text-gray-500">Recurrence</span>
                    <div @if(!$isInactive) @click="startEdit('recurrence_pattern')" @endif x-show="!editing.recurrence_pattern" class="mt-1 p-2 rounded min-h-[40px] {{ !$isInactive ? 'cursor-pointer hover:bg-gray-700' : '' }}">
                        @if($task->recurrence_pattern)
                            <p class="text-purple-400 font-semibold">🔄 {{ $task->recurrence_pattern }}
                                @if($task->recurrence_floating)
                                    <span class="text-xs text-purple-300 font-normal ml-1">(floating — next date relative to completion)</span>
                                @else
                                    <span class="text-xs text-gray-500 font-normal ml-1">(fixed schedule)</span>
                                @endif
                            </p>
                            @if($nextDueDate && $task->status !== 'done')
                            <p class="text-xs text-gray-300 mt-1">Due date after the current: {{ $nextDueDate }}</p>
                            @endif
                            @if($task->status !== 'done')
                            <p class="text-xs text-gray-400 mt-1">ℹ️ When marked done, this task will be completed and a new instance will be created for the next occurrence.</p>
                            @endif
                        @else
                            <p class="text-gray-400 italic">{{ $isInactive ? 'No recurrence set' : 'Click to set recurrence' }}</p>
                        @endif
                    </div>
                    @if(!$isInactive)
                    <div x-show="editing.recurrence_pattern" class="mt-1">
                        <input type="text" x-model="fields.recurrence_pattern"
                               placeholder="e.g., daily, every Monday, weekdays"
                               @keydown.enter="saveField('recurrence_pattern')"
                               @keydown.escape="cancelEdit('recurrence_pattern')"
                               class="w-full rounded-md bg-gray-700 border-gray-600 text-gray-100 placeholder-gray-500 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                        <label class="flex items-center mt-2 text-sm text-gray-400 cursor-pointer">
                            <input type="checkbox" x-model="fields.recurrence_floating"
                                   class="rounded bg-gray-700 border-gray-600 text-purple-500 focus:ring-purple-500 mr-2">
                            Floating (next date relative to when completed, not the original due date)
                        </label>
                        <div class="flex gap-2 mt-2">
                            <button @click="saveField('recurrence_pattern'); saveField('recurrence_floating')"
                                    class="px-3 py-1 bg-blue-600 text-white text-sm rounded hover:bg-blue-700">
                                Save
                            </button>
                            <button @click="cancelEdit('recurrence_pattern')"
                                    class="px-3 py-1 bg-gray-700 text-gray-300 text-sm rounded hover:bg-gray-600">
                                Cancel
                            </button>
                        </div>
                    </div>
                    @endif
                </div>

                <!-- Tags -->
                <div class="mt-4">
                    <span class="text-sm font-medium text-gray-500">Tags</span>
                    <div @if(!$isInactive) @click="startEdit('tag_ids')" @endif x-show="!editing.tag_ids" class="mt-1 p-2 rounded min-h-[40px] {{ !$isInactive ? 'cursor-pointer hover:bg-gray-700' : '' }}">
                        @if($task->tags->count() > 0)
                            <div class="flex gap-2 flex-wrap">
                                @foreach($task->tags as $tag)
                                    <a href="{{ route('tags.show', $tag) }}"
                                       @click.stop
                                       class="inline-block px-2 py-1 text-xs rounded hover:opacity-75"
                                       style="background-color: {{ $tag->color }}22; color: {{ $tag->color }}">
                                        {{ $tag->tag_name }}
                                    </a>
                                @endforeach
                            </div>
                        @else
                            <p class="text-gray-500 italic">{{ $isInactive ? 'No tags' : 'No tags - click to add' }}</p>
                        @endif
                    </div>
                    @if(!$isInactive)
                    <div x-show="editing.tag_ids" class="mt-1">
                        <div class="space-y-2 mb-2 max-h-48 overflow-y-auto border border-gray-600 bg-[#101010] rounded p-3">
                            @forelse($tags as $tag)
                                <label class="flex items-center">
                                    <input type="checkbox" value="{{ $tag->id }}" x-model="fields.tag_ids"
                                           class="rounded border-gray-600 bg-gray-700 text-blue-600 focus:ring-blue-500">
                                    <span class="ml-2 text-sm" style="color: {{ $tag->color }}">{{ $tag->tag_name }}</span>
                                </label>
                            @empty
                                <p class="text-sm text-gray-500">No tags available. Create tags first.</p>
                            @endforelse
                        </div>
                        <div class="flex gap-2 mt-2">
                            <button @click="saveField('tag_ids')"
                                    class="px-3 py-1 bg-blue-600 text-white text-sm rounded hover:bg-blue-700">
                                Save
                            </button>
                            <button @click="cancelEdit('tag_ids')"
                                    class="px-3 py-1 bg-gray-700 text-gray-300 text-sm rounded hover:bg-gray-600">
                                Cancel
                            </button>
                        </div>
                    </div>
                    @endif
                </div>

                <!-- Assignees -->
                @if($task->creator_id === Auth::id())
                <div class="mt-4">
                    <span class="text-sm font-medium text-gray-500">Assigned To</span>
                    <div @if(!$isInactive) @click="startEdit('assignee_ids')" @endif x-show="!editing.assignee_ids" class="mt-1 p-2 rounded {{ !$isInactive ? 'cursor-pointer hover:bg-gray-700' : '' }}">
                        <div class="space-y-1">
                            @foreach($task->assignees as $assignee)
                                <p class="text-sm text-gray-300">{{ $assignee->name }}</p>
                            @endforeach
                        </div>
                    </div>
                    @if(!$isInactive)
                    <div x-show="editing.assignee_ids" class="mt-1">
                        <div class="space-y-2 mb-2 max-h-48 overflow-y-auto border border-gray-600 bg-[#101010] rounded p-3">
                            @foreach($users as $user)
                                <label class="flex items-center {{ $user->id === Auth::id() ? 'cursor-not-allowed opacity-60' : '' }}">
                                    <input type="checkbox" value="{{ $user->id }}" x-model="fields.assignee_ids"
                                           class="rounded border-gray-600 bg-gray-700 text-blue-600 focus:ring-blue-500"
                                           @if($user->id === Auth::id()) disabled title="You cannot remove yourself as you are the task creator" @endif>
                                    <span class="ml-2 text-sm text-gray-300">{{ $user->name }} ({{ $user->email }})</span>
                                </label>
                            @endforeach
                        </div>
                        <div class="flex gap-2 mt-2">
                            <button @click="saveField('assignee_ids')"
                                    class="px-3 py-1 bg-blue-600 text-white text-sm rounded hover:bg-blue-700">
                                Save
                            </button>
                            <button @click="cancelEdit('assignee_ids')"
                                    class="px-3 py-1 bg-gray-700 text-gray-300 text-sm rounded hover:bg-gray-600">
                                Cancel
                            </button>
                        </div>
                    </div>
                    @endif
                </div>
                @else
                <div class="mt-4">
                    <span class="text-sm font-medium text-gray-500">Assigned To</span>
                    <div class="mt-1 space-y-1">
                        @foreach($task->assignees as $assignee)
                            <p class="text-sm text-gray-300">{{ $assignee->name }}</p>
                        @endforeach
                    </div>
                </div>
                @endif
            </div>

            <!-- Tabbed sections: Subtasks, Attachments, Comments, History -->
            <div x-data="tabSwitcher" class="bg-[#202020] border border-gray-700 overflow-hidden shadow-sm sm:rounded-lg">

                <!-- Tab bar -->
                <div class="flex border-b border-gray-700 overflow-x-auto">
                    <button @click="tab = 'comments'"
                            :class="tab === 'comments' ? 'border-b-2 border-blue-500 text-gray-100' : 'text-gray-400 hover:text-gray-200'"
                            class="flex-shrink-0 flex items-center gap-1.5 px-5 py-3 text-sm font-medium transition-colors whitespace-nowrap">
                        Comments
                        @if($task->comments->count() > 0)
                            <span class="px-1.5 py-0.5 text-xs rounded-full bg-gray-700 text-gray-300">{{ $task->comments->count() }}</span>
                        @endif
                    </button>
                    <button @click="tab = 'subtasks'"
                            :class="tab === 'subtasks' ? 'border-b-2 border-blue-500 text-gray-100' : 'text-gray-400 hover:text-gray-200'"
                            class="flex-shrink-0 flex items-center gap-1.5 px-5 py-3 text-sm font-medium transition-colors whitespace-nowrap">
                        Subtasks
                        @if($task->children->count() > 0)
                            <span class="px-1.5 py-0.5 text-xs rounded-full bg-gray-700 text-gray-300">{{ $task->children->count() }}</span>
                        @endif
                    </button>
                    <button @click="tab = 'attachments'"
                            :class="tab === 'attachments' ? 'border-b-2 border-blue-500 text-gray-100' : 'text-gray-400 hover:text-gray-200'"
                            class="flex-shrink-0 flex items-center gap-1.5 px-5 py-3 text-sm font-medium transition-colors whitespace-nowrap">
                        Attachments
                        @if($task->attachments->count() > 0)
                            <span class="px-1.5 py-0.5 text-xs rounded-full bg-gray-700 text-gray-300">{{ $task->attachments->count() }}</span>
                        @endif
                    </button>
                    <button @click="tab = 'history'"
                            :class="tab === 'history' ? 'border-b-2 border-blue-500 text-gray-100' : 'text-gray-400 hover:text-gray-200'"
                            class="flex-shrink-0 flex items-center gap-1.5 px-5 py-3 text-sm font-medium transition-colors whitespace-nowrap">
                        Activity
                        @if($task->changeLogs->count() > 0)
                            <span class="px-1.5 py-0.5 text-xs rounded-full bg-gray-700 text-gray-300">{{ $task->changeLogs->count() }}</span>
                        @endif
                    </button>
                </div>

                <!-- Tab content -->
                <div class="p-6">

                    <!-- Comments tab -->
                    <div x-show="tab === 'comments'">
                        <div class="space-y-4 mb-6">
                            @forelse($task->comments as $comment)
                                <div class="border-l-2 border-gray-600 pl-4">
                                    <div class="flex items-center justify-between">
                                        <span class="text-sm font-medium text-gray-300">{{ $comment->user->name }}</span>
                                        <div class="flex items-center gap-2">
                                            <span class="text-xs text-gray-500">{{ $comment->created_at->diffForHumans() }}</span>
                                            @if($task->creator_id === Auth::id())
                                                <form method="POST" action="{{ route('comments.destroy', [$task, $comment]) }}" class="inline">
                                                    @csrf
                                                    @method('DELETE')
                                                    <button type="submit" class="text-xs text-red-600 hover:underline" @click.prevent="confirmSubmit($el, 'Are you sure?')">
                                                        Delete
                                                    </button>
                                                </form>
                                            @endif
                                        </div>
                                    </div>
                                    <div class="markdown-body mt-1 text-sm">{!! render_body($comment->comment) !!}</div>
                                    @if($comment->file_path)
                                        <p class="mt-1 text-xs">
                                            <a href="{{ route('comments.download', [$task, $comment]) }}" class="text-blue-400 hover:text-blue-300 hover:underline">
                                                📎 <span title="{{ $comment->original_filename }}">{{ strlen($comment->original_filename) > 15 ? substr($comment->original_filename, 0, 12) . '...' : $comment->original_filename }}</span>
                                            </a>
                                            <span class="text-gray-500 ml-2">({{ number_format($comment->file_size / 1024, 1) }} KB)</span>
                                        </p>
                                    @endif
                                </div>
                            @empty
                                <p class="text-sm text-gray-500">No comments yet.</p>
                            @endforelse
                        </div>

                        @if(!$isInactive)
                        <form method="POST" action="{{ route('comments.store', $task) }}" enctype="multipart/form-data">
                            @csrf
                            <textarea name="comment" rows="3" required
                                      class="w-full rounded-md bg-gray-700 border-gray-600 text-gray-100 placeholder-gray-500 shadow-sm focus:border-blue-500 focus:ring-blue-500 mb-2"
                                      placeholder="Add a comment..."></textarea>
                            <div class="flex items-center gap-4" x-data="fileInput">
                                <label class="flex items-center gap-2 cursor-pointer">
                                    <span class="px-3 py-1.5 bg-gray-700 border border-gray-600 text-gray-300 text-sm rounded hover:bg-gray-600">Choose file</span>
                                    <span class="text-sm text-gray-400" x-text="fileName || 'No file chosen'"></span>
                                    <input type="file" name="attachment" class="hidden"
                                           accept=".jpg,.jpeg,.png,.webp,.gif,.heic,.heif,.pdf,.doc,.docx,.xls,.xlsx,.ppt,.pptx,.odt,.ods,.odp,.csv,.txt,.json"
                                           @change="fileName = $event.target.files[0] ? ($event.target.files[0].name.length > 20 ? $event.target.files[0].name.slice(0, 20) + '…' : $event.target.files[0].name) : ''">
                                </label>
                                <button type="submit" class="px-4 py-2 bg-blue-600 text-white text-sm rounded hover:bg-blue-700">
                                    Post Comment
                                </button>
                            </div>
                        </form>
                        @endif
                    </div>

                    <!-- Subtasks tab -->
                    <div x-show="tab === 'subtasks'">
                        <div class="flex justify-between items-center mb-4">
                            <p class="text-sm text-gray-400">
                                @if($task->children->count() > 0)
                                    {{ $task->incompleteChildren()->count() }} of {{ $task->children->count() }} incomplete
                                @else
                                    No subtasks yet.
                                @endif
                            </p>
                            @if(!$isInactive)
                            <a href="{{ route('tasks.create', ['parent_id' => $task->id]) }}"
                               class="inline-flex items-center px-3 py-1 bg-blue-600 text-white text-sm rounded hover:bg-blue-700">
                                + Add Subtask
                            </a>
                            @endif
                        </div>

                        @if($task->children->count() > 0)
                            <div class="space-y-2">
                                <x-subtask-list :tasks="$task->children" :parent="$task" />
                            </div>
                        @endif
                    </div>

                    <!-- Attachments tab -->
                    <div x-show="tab === 'attachments'">
                        @if($task->attachments->count() > 0)
                            <div class="space-y-2 mb-4">
                                @foreach($task->attachments as $attachment)
                                    <div class="flex items-center gap-3 p-2 bg-gray-700 border border-gray-600 rounded">
                                        <div class="flex-shrink-0 w-16 h-16 bg-gray-600 rounded overflow-hidden flex items-center justify-center">
                                            @if(str_starts_with($attachment->mime_type, 'image/'))
                                                <img src="{{ route('attachments.view', [$task, $attachment]) }}"
                                                     alt="{{ $attachment->original_filename }}"
                                                     class="w-full h-full object-cover">
                                            @else
                                                <svg class="w-8 h-8 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                          d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z"></path>
                                                </svg>
                                            @endif
                                        </div>
                                        <div class="flex-1 flex items-center justify-between">
                                            <span class="text-sm text-gray-300" title="{{ $attachment->original_filename }}">{{ strlen($attachment->original_filename) > 15 ? substr($attachment->original_filename, 0, 12) . '...' : $attachment->original_filename }}</span>
                                            <div class="flex gap-2">
                                                <a href="{{ route('attachments.download', [$task, $attachment]) }}" class="text-sm text-blue-400 hover:underline">
                                                    Download
                                                </a>
                                                @if($task->creator_id === Auth::id())
                                                    <form method="POST" action="{{ route('attachments.destroy', [$task, $attachment]) }}" class="inline">
                                                        @csrf
                                                        @method('DELETE')
                                                        <button type="submit" class="text-sm text-red-600 hover:underline" @click.prevent="confirmSubmit($el, 'Are you sure?')">
                                                            Delete
                                                        </button>
                                                    </form>
                                                @endif
                                            </div>
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        @else
                            <p class="text-sm text-gray-500 mb-4">No attachments yet.</p>
                        @endif

                        @if(!$isInactive)
                        <form method="POST" action="{{ route('attachments.store', $task) }}" enctype="multipart/form-data">
                            @csrf
                            <div class="flex items-center gap-2" x-data="fileInput">
                                <label class="flex items-center gap-2 cursor-pointer">
                                    <span class="px-3 py-1.5 bg-gray-700 border border-gray-600 text-gray-300 text-sm rounded hover:bg-gray-600">Choose file</span>
                                    <span class="text-sm text-gray-400" x-text="fileName || 'No file chosen'"></span>
                                    <input type="file" name="attachment" required class="hidden"
                                           accept=".jpg,.jpeg,.png,.webp,.gif,.heic,.heif,.pdf,.doc,.docx,.xls,.xlsx,.ppt,.pptx,.odt,.ods,.odp,.csv,.txt,.json"
                                           @change="fileName = $event.target.files[0] ? ($event.target.files[0].name.length > 20 ? $event.target.files[0].name.slice(0, 20) + '…' : $event.target.files[0].name) : ''">
                                </label>
                                <button type="submit" class="px-4 py-2 bg-blue-600 text-white text-sm rounded hover:bg-blue-700">
                                    Upload
                                </button>
                            </div>
                        </form>
                        @endif
                    </div>

                    <!-- History tab -->
                    <div x-show="tab === 'history'">
                        <div class="space-y-4">
                            <x-change :changes="$task->changeLogs" />
                        </div>
                    </div>

                </div>
            </div>
        </div>
    </div>

    @push('scripts')
    <script nonce="{{ csp_nonce() }}">
        document.addEventListener('alpine:init', () => {
            Alpine.data('taskShowNameEditor', function() {
                return {
                    editing: false,
                    name: '',
                    original: '',
                    taskId: null,
                    init() {
                        this.name = this.$el.dataset.taskName || '';
                        this.original = this.name;
                        this.taskId = parseInt(this.$el.dataset.taskId) || null;
                    },
                    async save() {
                        const fd = new FormData();
                        fd.append('_token', document.querySelector('meta[name=\'csrf-token\']').content);
                        fd.append('field', 'name');
                        fd.append('value', this.name.trim());
                        const res = await fetch('/tasks/' + this.taskId + '/update-field', { method: 'POST', body: fd });
                        const data = await res.json();
                        if (data.success) { window.location.reload(); }
                        else { alert('Error saving: ' + (data.message || 'Failed')); this.name = this.original; this.editing = false; }
                    },
                    cancel() { this.name = this.original; this.editing = false; },
                    startEditName() {
                        this.editing = true;
                        this.$nextTick(() => {
                            if (this.$refs.nameInput) this.$refs.nameInput.focus();
                        });
                    }
                };
            });
        });

        document.addEventListener('alpine:init', () => {
        Alpine.data('taskEditor', function(taskId) {
            return {
                taskId: taskId,
                editing: {},
                renderedDescription: @js(render_body($task->description ?? '')),
                fields: {
                    name: @js($task->name),
                    description: @js($task->description ?? ''),
                    location: @js($task->location ?? ''),
                    status: @js($task->status),
                    date: @js($task->date ?? ''),
                    time: @js($task->time ?? ''),
                    duration_minutes: @js(\App\Models\Task::formatDuration($task->duration_minutes) ?? ''),
                    project_id: @js($task->project_id ?? $defaultProjectId),
                    parent_id: @js($task->parent_id ?? ''),
                    recurrence_pattern: @js($task->recurrence_pattern ?? ''),
                    recurrence_floating: @js((bool) $task->recurrence_floating),
                    show_map: @js((bool) $task->show_map),
                    tag_ids: @js($task->tags->pluck('id')->toArray()),
                    assignee_ids: @js($task->assignees->pluck('id')->toArray()),
                },
                original: {},
                dateText: @js($task->date ? \Carbon\Carbon::parse($task->date)->format('l, F j, Y') : ''),
                datePreview: '',
                dateError: '',
                datePast: false,
                projects: null,
                get projectTaskCount() {
                    let n = 0;
                    if (this.projects) { for (const p of this.projects) n += p.count; }
                    return n;
                },
                _datePreviewTimeout: null,
                parentSearch: @js($task->parent ? $task->parent->name : ''),
                parentOpen: false,
                parentTasks: @js($availableParents->map(fn($t) => ['id' => $t->id, 'name' => str_repeat('→ ', $t->getDepth()) . $t->name, 'rawName' => $t->name])->values()->all()),
                get parentFiltered() {
                    const q = this.parentSearch.toLowerCase().trim();
                    if (!q) return this.parentTasks.slice(0, 10);
                    return this.parentTasks.filter(t => t.rawName.toLowerCase().includes(q)).slice(0, 10);
                },
                selectParent(task) {
                    this.fields.parent_id = task.id;
                    this.parentSearch = task.rawName;
                    this.parentOpen = false;
                },
                clearParent() {
                    this.fields.parent_id = '';
                    this.parentSearch = '';
                    this.parentOpen = false;
                },

                init() {
                    this.original = JSON.parse(JSON.stringify(this.fields));
                    this.$nextTick(() => { if (this.$refs.descHtml) this.$refs.descHtml.innerHTML = this.renderedDescription; });
                },

                startEdit(field) {
                    this.editing[field] = true;
                },

                startEditDate() {
                    this.editing.date = true;
                    this.datePreview = '';
                    this.dateError = '';
                    this.datePast = false;
                    this.$nextTick(() => {
                        if (this.$refs.dateInput) {
                            this.$refs.dateInput.focus();
                            this.$refs.dateInput.select();
                        }
                    });
                },

                cancelEdit(field) {
                    this.editing[field] = false;
                    if (field === 'date') {
                        this.dateText = @js($task->date ? \Carbon\Carbon::parse($task->date)->format('l, F j, Y') : '');
                        this.datePreview = '';
                        this.dateError = '';
                        this.datePast = false;
                    } else if (field === 'parent_id') {
                        this.parentSearch = @js($task->parent ? $task->parent->name : '');
                        this.parentOpen = false;
                    }
                    this.resetField(field);
                },

                resetField(field) {
                    this.fields[field] = JSON.parse(JSON.stringify(this.original[field]));
                },

                async previewDate() {
                    const input = this.dateText.trim();
                    if (!input) {
                        this.datePreview = '';
                        this.dateError = '';
                        this.projects = null;
                        return;
                    }

                    try {
                        const response = await fetch('/tasks/parse-date', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                                'Accept': 'application/json',
                            },
                            body: JSON.stringify({ input: input }),
                        });

                        const data = await response.json();
                        if (data.success) {
                            const today = new Date();
                            today.setHours(0, 0, 0, 0);
                            const resolved = new Date(data.date + 'T00:00:00');
                            if (resolved < today) {
                                this.datePast = true;
                                this.datePreview = 'Proposed date is in the past';
                                this.fields.date = data.date;
                                this.projects = null;
                            } else {
                                this.datePast = false;
                                this.projects = data.projects ?? null;
                                this.datePreview = data.formatted;
                                this.dateError = '';
                                this.fields.date = data.date;
                            }
                        } else {
                            this.datePast = false;
                            this.datePreview = '';
                            this.dateError = 'Could not parse this date';
                            this.projects = null;
                        }
                    } catch (e) {
                        this.datePast = false;
                        this.datePreview = '';
                        this.dateError = '';
                        this.projects = null;
                    }
                },

                async pickDate(value) {
                    if (!value) return;
                    // Convert Y-m-d to readable text for the input
                    const d = new Date(value + 'T12:00:00');
                    const options = { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' };
                    this.dateText = d.toLocaleDateString('en-US', options);
                    this.fields.date = value;
                    this.dateError = '';
                    this.projects = null;

                    try {
                        const response = await fetch('/tasks/parse-date', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                                'Accept': 'application/json',
                            },
                            body: JSON.stringify({ input: value }),
                        });
                        const data = await response.json();
                        this.projects = data.success ? (data.projects ?? null) : null;
                    } catch (e) {
                        this.projects = null;
                    }

                    this.datePreview = this.dateText;
                },

                async saveDateField() {
                    const input = this.dateText.trim();
                    if (!input) {
                        this.fields.date = '';
                        await this.saveField('date');
                        return;
                    }
                    // previewDate() already stores the resolved Y-m-d in fields.date
                    // If it hasn't resolved yet, send the raw text for server-side parsing
                    if (!/^\d{4}-\d{2}-\d{2}$/.test(this.fields.date)) {
                        this.fields.date = input;
                    }
                    await this.saveField('date');
                },

                async clearDate() {
                    this.dateText = '';
                    this.fields.date = '';
                    this.datePreview = '';
                    this.dateError = '';
                    this.datePast = false;
                    await this.saveField('date');
                },

                // Send a single field to the server and update local state on success.
                // Returns true on success, false on failure. Does NOT reload the page.
                async _saveFieldRequest(field) {
                    // Normalize date text → stored date value before sending
                    if (field === 'date') {
                        const input = this.dateText.trim();
                        if (!input) {
                            this.fields.date = '';
                        } else if (!/^\d{4}-\d{2}-\d{2}$/.test(this.fields.date)) {
                            this.fields.date = input;
                        }
                    }

                    const formData = new FormData();
                    formData.append('_token', document.querySelector('meta[name="csrf-token"]').content);
                    formData.append('field', field);

                    if (Array.isArray(this.fields[field])) {
                        this.fields[field].forEach(value => {
                            formData.append(field + '[]', value);
                        });
                    } else {
                        formData.append('value', this.fields[field]);
                    }

                    const response = await fetch(`/tasks/${this.taskId}/update-field`, {
                        method: 'POST',
                        body: formData,
                    });

                    const data = await response.json();

                    if (data.success) {
                        this.original[field] = JSON.parse(JSON.stringify(this.fields[field]));
                        this.editing[field] = false;
                        if (field === 'description' && data.rendered_description !== undefined) {
                            this.renderedDescription = data.rendered_description;
                            this.$nextTick(() => { if (this.$refs.descHtml) this.$refs.descHtml.innerHTML = this.renderedDescription; });
                        }
                        return true;
                    } else {
                        alert('Error saving ' + field + ': ' + (data.message || 'Failed to update'));
                        this.resetField(field);
                        return false;
                    }
                },

                async saveField(field) {
                    try {
                        // Check if marking a recurring task as done
                        if (field === 'status' && this.fields.status === 'done' && this.fields.recurrence_pattern) {
                            const confirmed = confirm(
                                '🔄 Recurring Task: Marking as Done\n\n' +
                                'This will complete THIS instance only and create a new task for the next occurrence.\n\n' +
                                'To stop the recurring series instead:\n' +
                                '• Remove the recurrence pattern first, then mark done, OR\n' +
                                '• Change status to "Archived" instead\n\n' +
                                'Continue with marking this instance done?'
                            );
                            if (!confirmed) {
                                this.resetField(field);
                                this.editing[field] = false;
                                return;
                            }
                        }

                        const otherEditingFields = Object.keys(this.editing).filter(f => this.editing[f] && f !== field);
                        for (const otherField of otherEditingFields) {
                            await this._saveFieldRequest(otherField);
                        }

                        // Save the triggered field and reload
                        const ok = await this._saveFieldRequest(field);
                        if (ok) {
                            window.location.reload();
                        }
                    } catch (error) {
                        console.error('Error:', error);
                        alert('An error occurred while saving');
                        this.resetField(field);
                    }
                },
            };
        });
        });
    </script>
    @endpush
</x-app-layout>
