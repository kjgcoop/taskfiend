{{--
    Task Side Panel Partial
    Served at GET /tasks/{task}/panel — injected into the panel overlay via fetch().
    No layout wrapper. Script tags are executed by the panel overlay after injection.
--}}

<!-- Panel Header -->
<div class="sticky top-0 z-10 flex items-center justify-between px-4 py-3 bg-gray-900 border-b border-gray-700">
    <div class="flex items-center gap-2 min-w-0">
        <button @click="$dispatch('close-task-panel')"
                class="flex-shrink-0 p-1.5 text-gray-400 hover:text-gray-100 rounded hover:bg-gray-700 transition"
                title="Close panel">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
            </svg>
        </button>
        <span class="text-xs text-gray-500 uppercase tracking-wide font-medium flex-shrink-0">Task #{{ $task->id }}</span>
        <button x-data="copyButton"
                @click.prevent="copy('{{ route('tasks.show', $task) }}')"
                @click.stop
                title="Copy link"
                class="flex-shrink-0 p-1 text-gray-600 hover:text-gray-300 rounded transition">
            <svg x-show="!copied" class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                      d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"/>
            </svg>
            <svg x-show="copied" class="w-3.5 h-3.5 text-green-400" fill="none" stroke="currentColor" viewBox="0 0 24 24" style="display:none">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
            </svg>
        </button>
    </div>
    <div class="flex items-center gap-2">
        @if(!$isInactive)
        <form method="POST" action="{{ route('tasks.duplicate', $task) }}">
            @csrf
            <button type="submit"
                    class="text-xs px-2.5 py-1 bg-gray-700 text-gray-300 rounded hover:bg-gray-600 hover:text-gray-100 transition">
                Duplicate
            </button>
        </form>
        @endif
        <a href="{{ route('tasks.show', $task) }}"
           class="flex items-center gap-1 text-xs px-2.5 py-1 bg-gray-700 text-gray-300 rounded hover:bg-gray-600 hover:text-gray-100 transition">
            Open full page
            <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                      d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"/>
            </svg>
        </a>
    </div>
</div>

<!-- Panel Body -->
@php
$_panelTaskJson = json_encode([
    'taskId'      => $task->id,
    'fields'      => [
        'name'               => $task->name,
        'description'        => $task->description ?? '',
        'location'           => $task->location ?? '',
        'status'             => $task->status,
        'date'               => $task->date ?? '',
        'time'               => $task->time ?? '',
        'duration_minutes'   => \App\Models\Task::formatDuration($task->duration_minutes) ?? '',
        'project_id'         => $task->project_id ?? $defaultProjectId,
        'recurrence_pattern' => $task->recurrence_pattern ?? '',
        'recurrence_floating'=> (bool) $task->recurrence_floating,
        'show_map'           => (bool) $task->show_map,
        'tag_ids'            => $task->tags->pluck('id')->toArray(),
        'assignee_ids'       => $task->assignees->pluck('id')->toArray(),
    ],
    'dateText'    => $task->date ? \Carbon\Carbon::parse($task->date)->format('l, F j, Y') : '',
    'allTags'     => $tags->map(fn($t) => ['id' => $t->id, 'name' => $t->tag_name, 'color' => $t->color])->values(),
    'allProjects' => $projects->map(fn($p) => ['id' => $p->id, 'name' => $p->name])->values(),
]);
@endphp
<div class="p-5 space-y-5" x-data="taskPanelEditor" data-task-json="{{ $_panelTaskJson }}">
    <div data-rendered-description hidden>{!! render_body($task->description ?? '') !!}</div>

    {{-- Recurrence warning --}}
    @if($task->recurrence_pattern && $task->status !== 'done' && $task->status !== 'archived')
    <div class="p-3 bg-purple-900 bg-opacity-20 border border-purple-500 rounded-lg">
        <p class="text-sm text-purple-300">
            <span class="font-semibold">🔄 Recurring Task</span>
            ({{ $task->recurrence_pattern }}{{ $task->recurrence_floating ? ', floating' : '' }})
        </p>
        <p class="text-xs text-purple-400 mt-1">
            Marking as done will complete this instance and create the next occurrence automatically.
        </p>
    </div>
    @endif

    {{-- Inactive warning --}}
    @if($isInactive)
    <div class="p-3 bg-yellow-900 bg-opacity-20 border border-yellow-600 rounded-lg">
        <p class="text-sm text-yellow-300">
            @if(in_array($task->status, ['done', 'archived']))
                <span class="font-semibold">Task is {{ $task->status }}.</span>
                This task is read-only.
            @else
                <span class="font-semibold">Project is inactive.</span>
                This task is read-only.
            @endif
        </p>
    </div>
    @endif

    <!-- Task Name -->
    <div>
        <span class="text-xs font-medium text-gray-500 uppercase tracking-wide">Task Name</span>
        <div @if(!$isInactive) @click="startEdit('name')" @endif
             x-show="!editing.name"
             class="mt-1 p-2 rounded text-lg font-semibold text-gray-100 task-title {{ !$isInactive ? 'cursor-pointer hover:bg-gray-700' : '' }}">
            {!! render_title($task->name) !!}
        </div>
        @if(!$isInactive)
        <div x-show="editing.name" class="mt-1">
            <div class="relative">
                <input type="text" x-ref="nameInput"
                       x-model="fields.name"
                       @input="handleNameInput($event)"
                       @keydown="handleNameKeydown($event)"
                       @blur="nameAC.show = false"
                       class="w-full rounded-md bg-gray-700 border-gray-600 text-gray-100 placeholder-gray-500 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                <div x-show="nameAC.show" x-cloak
                     class="absolute z-50 left-0 right-0 top-full mt-0.5 bg-gray-800 border border-gray-600 rounded-md shadow-lg overflow-hidden">
                    <template x-for="item in nameAC.results" :key="item.id">
                        <button type="button"
                                @mousedown.prevent="selectNameAutocomplete(item)"
                                :class="nameAC.results.indexOf(item) === nameAC.activeIndex ? 'bg-gray-700' : 'hover:bg-gray-700'"
                                class="w-full text-left flex items-center gap-2 px-3 py-1.5 text-sm text-gray-200">
                            <span x-show="nameAC.type === 'tag'"
                                  class="inline-block w-2 h-2 rounded-full flex-shrink-0"
                                  :style="'background-color:' + (item.color || '#888')"></span>
                            <span x-text="item.name"></span>
                        </button>
                    </template>
                </div>
            </div>
            <div class="flex gap-2 mt-2">
                <button @click="saveField('name')" class="px-3 py-1 bg-blue-600 text-white text-sm rounded hover:bg-blue-700">Save</button>
                <button @click="cancelEdit('name')" class="px-3 py-1 bg-gray-700 text-gray-300 text-sm rounded hover:bg-gray-600">Cancel</button>
            </div>
        </div>
        @endif
    </div>

    <!-- 2-column field grid -->
    <div class="grid grid-cols-2 gap-x-6 gap-y-4">

        <!-- Status -->
        <div>
            <span class="text-xs font-medium text-gray-500 uppercase tracking-wide">Status</span>
            <div @click="startEdit('status')"
                 x-show="!editing.status"
                 class="mt-1 p-2 rounded cursor-pointer hover:bg-gray-700">
                <span class="inline-block px-2 py-0.5 text-xs rounded
                    @if($task->status === 'done') bg-green-100 text-green-800
                    @elseif($task->status === 'archived') bg-gray-100 text-gray-800
                    @else bg-blue-100 text-blue-800 @endif">
                    {{ ucfirst($task->status) }}
                </span>
                @if($task->recurrence_pattern && $task->status !== 'done')
                    <span class="ml-1 text-xs text-purple-400">🔄</span>
                @endif
            </div>
            <div x-show="editing.status" class="mt-1">
                <select x-model="fields.status"
                        x-ref="statusInput"
                        @keydown.escape.stop="cancelEdit('status')"
                        class="w-full rounded-md bg-gray-700 border-gray-600 text-gray-100 shadow-sm focus:border-blue-500 focus:ring-blue-500 text-sm">
                    <option value="incomplete">Incomplete</option>
                    <option value="done">Done</option>
                    <option value="archived">Archived</option>
                </select>
                <div class="flex gap-2 mt-2">
                    <button @click="saveField('status')" class="px-3 py-1 bg-blue-600 text-white text-sm rounded hover:bg-blue-700">Save</button>
                    <button @click="cancelEdit('status')" class="px-3 py-1 bg-gray-700 text-gray-300 text-sm rounded hover:bg-gray-600">Cancel</button>
                </div>
            </div>
        </div>

        <!-- Created By -->
        <div>
            <span class="text-xs font-medium text-gray-500 uppercase tracking-wide">Created By</span>
            <p class="mt-1 p-2 text-sm text-gray-300">{{ $task->creator->name }}</p>
        </div>

        <!-- Date -->
        <div>
            <span class="text-xs font-medium text-gray-500 uppercase tracking-wide">Date</span>
            <div @if(!$isInactive) @click="startEditDate()" @endif
                 x-show="!editing.date"
                 class="mt-1 p-2 rounded min-h-[36px] {{ !$isInactive ? 'cursor-pointer hover:bg-gray-700' : '' }}">
                @if($task->date)
                    <p class="text-sm text-gray-300">{{ \Carbon\Carbon::parse($task->date)->format('l, F j, Y') }}</p>
                @else
                    <p class="text-sm text-gray-400 italic">{{ $isInactive ? 'No date set' : 'Click to set date' }}</p>
                @endif
            </div>
            @if(!$isInactive)
            <div x-show="editing.date" class="mt-1">
                <div class="flex gap-2 items-start">
                    <div class="flex-1">
                        <input type="text" x-model="dateText" x-ref="dateInput"
                               @input.debounce.300ms="previewDate()"
                               @keydown.enter.prevent="saveDateField()"
                               @keydown.escape.stop="cancelEdit('date')"
                               placeholder="tomorrow, next friday, march 15..."
                               class="w-full rounded-md bg-gray-700 border-gray-600 text-gray-100 placeholder-gray-500 shadow-sm focus:border-blue-500 focus:ring-blue-500 text-sm">
                    </div>
                    <div class="relative flex-shrink-0">
                        <button @click="$refs.datePicker.showPicker()" type="button"
                                class="p-2 bg-gray-700 border border-gray-600 rounded-md hover:bg-gray-600 text-gray-400 hover:text-gray-200">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                      d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                            </svg>
                        </button>
                        <input type="date" x-ref="datePicker" @change="pickDate($event.target.value)"
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
                                <span class="underline decoration-dotted cursor-help" x-text="proj.name + ' &dash; ' + proj.count"></span>
                                <div class="absolute hidden group-hover:block bottom-full left-0 mb-1 bg-gray-900 border border-gray-600 rounded p-2 text-gray-200 z-50 shadow-lg min-w-max max-w-xs">
                                    <template x-for="task in proj.tasks" :key="task">
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
                <div class="flex gap-2 mt-2">
                    <button @click="saveDateField()" class="px-3 py-1 bg-blue-600 text-white text-sm rounded hover:bg-blue-700">Save</button>
                    <button @click="cancelEdit('date')" class="px-3 py-1 bg-gray-700 text-gray-300 text-sm rounded hover:bg-gray-600">Cancel</button>
                    @if($task->date)
                    <button @click="clearDate()" class="px-3 py-1 bg-gray-700 text-red-400 text-sm rounded hover:bg-gray-600">Clear</button>
                    @endif
                </div>
            </div>
            @endif
        </div>

        <!-- Time -->
        <div>
            <span class="text-xs font-medium text-gray-500 uppercase tracking-wide">Time</span>
            <div @if(!$isInactive) @click="startEdit('time')" @endif
                 x-show="!editing.time"
                 class="mt-1 p-2 rounded min-h-[36px] {{ !$isInactive ? 'cursor-pointer hover:bg-gray-700' : '' }}">
                @if($task->time)
                    <p class="text-sm text-gray-300">{{ \Carbon\Carbon::parse($task->time)->format('g:i A') }}</p>
                @else
                    <p class="text-sm text-gray-400 italic">{{ $isInactive ? 'No time set' : 'Click to set time' }}</p>
                @endif
            </div>
            @if(!$isInactive)
            <div x-show="editing.time" class="mt-1">
                <input type="time" x-model="fields.time"
                       x-ref="timeInput"
                       @keydown.enter="saveField('time')"
                       @keydown.escape.stop="cancelEdit('time')"
                       class="w-full rounded-md bg-gray-700 border-gray-600 text-gray-100 shadow-sm focus:border-blue-500 focus:ring-blue-500 text-sm">
                <div class="flex gap-2 mt-2">
                    <button @click="saveField('time')" class="px-3 py-1 bg-blue-600 text-white text-sm rounded hover:bg-blue-700">Save</button>
                    <button @click="cancelEdit('time')" class="px-3 py-1 bg-gray-700 text-gray-300 text-sm rounded hover:bg-gray-600">Cancel</button>
                    @if($task->time)
                    <button @click="clearAndSave('time')" class="px-3 py-1 bg-gray-700 text-red-400 text-sm rounded hover:bg-gray-600">Clear</button>
                    @endif
                </div>
            </div>
            @endif
        </div>

        <!-- Duration -->
        <div>
            <span class="text-xs font-medium text-gray-500 uppercase tracking-wide">Duration</span>
            <div @if(!$isInactive) @click="startEdit('duration_minutes')" @endif
                 x-show="!editing.duration_minutes"
                 class="mt-1 p-2 rounded min-h-[36px] {{ !$isInactive ? 'cursor-pointer hover:bg-gray-700' : '' }}">
                @if($task->duration_minutes)
                    <p class="text-sm text-gray-300">{{ \App\Models\Task::formatDuration($task->duration_minutes) }}</p>
                @else
                    <p class="text-sm text-gray-400 italic">{{ $isInactive ? 'No duration' : 'Click to set duration' }}</p>
                @endif
            </div>
            @if(!$isInactive)
            <div x-show="editing.duration_minutes" class="mt-1">
                <input type="text" x-model="fields.duration_minutes"
                       x-ref="duration_minutesInput"
                       @keydown.enter="saveField('duration_minutes')"
                       @keydown.escape.stop="cancelEdit('duration_minutes')"
                       placeholder="e.g. 1h 30m, 90, 2h"
                       class="w-full rounded-md bg-gray-700 border-gray-600 text-gray-100 placeholder-gray-500 shadow-sm focus:border-blue-500 focus:ring-blue-500 text-sm">
                <div class="flex gap-2 mt-2">
                    <button @click="saveField('duration_minutes')" class="px-3 py-1 bg-blue-600 text-white text-sm rounded hover:bg-blue-700">Save</button>
                    <button @click="cancelEdit('duration_minutes')" class="px-3 py-1 bg-gray-700 text-gray-300 text-sm rounded hover:bg-gray-600">Cancel</button>
                    @if($task->duration_minutes)
                    <button @click="clearAndSave('duration_minutes')"
                            class="px-3 py-1 bg-gray-700 text-red-400 text-sm rounded hover:bg-gray-600">Clear</button>
                    @endif
                </div>
            </div>
            @endif
        </div>

        <!-- Location -->
        <div>
            <span class="text-xs font-medium text-gray-500 uppercase tracking-wide">Location</span>
            <div @if(!$isInactive) @click="startEdit('location')" @endif
                 x-show="!editing.location"
                 class="mt-1 p-2 rounded min-h-[36px] {{ !$isInactive ? 'cursor-pointer hover:bg-gray-700' : '' }}">
                @if($task->location)
                    @if($task->show_map)
                        @php $mapUrl = sprintf(config('taskfiend.maps_url_template', 'https://maps.google.com/?q=%s'), urlencode($task->location)); @endphp
                        <a href="{{ $mapUrl }}" target="_blank" rel="noopener" @click.stop
                           title="{{ $task->location }}"
                           class="inline-flex items-center gap-1 text-sm text-orange-400 hover:underline">{{ $task->location }}<svg class="inline w-3 h-3 opacity-70 flex-shrink-0" viewBox="0 0 10 10" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M1 9L9 1M9 1H4M9 1V6"/></svg></a>
                    @else
                        <p class="text-sm text-gray-300">{{ $task->location }}</p>
                    @endif
                @else
                    <p class="text-sm text-gray-400 italic">{{ $isInactive ? 'No location' : 'Click to set location' }}</p>
                @endif
            </div>
            @if(!$isInactive)
            <div x-show="editing.location" class="mt-1">
                <input type="text" x-model="fields.location"
                       x-ref="locationInput"
                       @keydown.enter="saveField('location')"
                       @keydown.escape.stop="cancelEdit('location')"
                       placeholder="e.g., Conference Room B, Zoom, 123 Main St"
                       class="w-full rounded-md bg-gray-700 border-gray-600 text-gray-100 placeholder-gray-500 shadow-sm focus:border-blue-500 focus:ring-blue-500 text-sm">
                <div class="flex items-center gap-2 mt-2">
                    <label class="flex items-center gap-1.5 text-xs text-gray-400 cursor-pointer">
                        <input type="checkbox" x-model="fields.show_map"
                               @change="saveField('show_map')"
                               class="rounded bg-gray-700 border-gray-600 text-orange-500 focus:ring-orange-500">
                        Map link
                    </label>
                </div>
                <div class="flex gap-2 mt-2">
                    <button @click="saveField('location')" class="px-3 py-1 bg-blue-600 text-white text-sm rounded hover:bg-blue-700">Save</button>
                    <button @click="cancelEdit('location')" class="px-3 py-1 bg-gray-700 text-gray-300 text-sm rounded hover:bg-gray-600">Cancel</button>
                    @if($task->location)
                    <button @click="clearAndSave('location')"
                            class="px-3 py-1 bg-gray-700 text-red-400 text-sm rounded hover:bg-gray-600">Clear</button>
                    @endif
                </div>
            </div>
            @endif
        </div>

        <!-- Project -->
        <div>
            <span class="text-xs font-medium text-gray-500 uppercase tracking-wide">Project</span>
            <div @if(!$isInactive) @click="startEdit('project_id')" @endif
                 x-show="!editing.project_id"
                 class="mt-1 p-2 rounded {{ !$isInactive ? 'cursor-pointer hover:bg-gray-700' : '' }}">
                <p class="text-sm text-gray-300">{{ $task->project ? $task->project->name : 'Inbox' }}</p>
            </div>
            @if(!$isInactive)
            <div x-show="editing.project_id" class="mt-1">
                <select x-model="fields.project_id"
                        x-ref="project_idInput"
                        @change="saveField('project_id')"
                        @keydown.escape.stop="cancelEdit('project_id')"
                        class="w-full rounded-md bg-gray-700 border-gray-600 text-gray-100 shadow-sm focus:border-blue-500 focus:ring-blue-500 text-sm">
                    @foreach($projects as $project)
                        <option value="{{ $project->id }}">{{ $project->name }}</option>
                    @endforeach
                </select>
            </div>
            @endif
        </div>

    </div>{{-- end grid --}}

    <!-- Description -->
    <div>
        <span class="text-xs font-medium text-gray-500 uppercase tracking-wide">Description</span>
        <div @if(!$isInactive) @click="$event.target.closest('a') || startEdit('description')" @endif
             x-show="!editing.description"
             class="mt-1 p-2 rounded min-h-[40px] {{ !$isInactive ? 'cursor-pointer hover:bg-gray-700' : '' }}">
            <div class="markdown-body" x-show="fields.description"
                 x-ref="descHtml"></div>
            <p x-show="!fields.description" class="text-sm text-gray-400 italic">
                {{ $isInactive ? 'No description' : 'Click to add description' }}
            </p>
        </div>
        @if(!$isInactive)
        <div x-show="editing.description" class="mt-1">
            <textarea x-model="fields.description" rows="4"
                      x-ref="descriptionInput"
                      @keydown.ctrl.enter="saveField('description')"
                      @keydown.escape.stop="cancelEdit('description')"
                      class="w-full rounded-md bg-gray-700 border-gray-600 text-gray-100 placeholder-gray-500 shadow-sm focus:border-blue-500 focus:ring-blue-500 text-sm"></textarea>
            <p class="text-xs text-gray-500 mt-1">Ctrl+Enter to save. Markdown supported.</p>
            <div class="flex gap-2 mt-2">
                <button @click="saveField('description')" class="px-3 py-1 bg-blue-600 text-white text-sm rounded hover:bg-blue-700">Save</button>
                <button @click="cancelEdit('description')" class="px-3 py-1 bg-gray-700 text-gray-300 text-sm rounded hover:bg-gray-600">Cancel</button>
            </div>
        </div>
        @endif
    </div>

    <!-- Recurrence -->
    <div>
        <span class="text-xs font-medium text-gray-500 uppercase tracking-wide">Recurrence</span>
        <div @if(!$isInactive) @click="startEdit('recurrence_pattern')" @endif
             x-show="!editing.recurrence_pattern"
             class="mt-1 p-2 rounded min-h-[36px] {{ !$isInactive ? 'cursor-pointer hover:bg-gray-700' : '' }}">
            @if($task->recurrence_pattern)
                <p class="text-sm text-purple-400 font-semibold">🔄 {{ $task->recurrence_pattern }}</p>
                @if($nextDueDate)
                    <p class="text-xs text-gray-400 mt-0.5">Next after current: {{ $nextDueDate }}</p>
                @endif
            @else
                <p class="text-sm text-gray-400 italic">{{ $isInactive ? 'No recurrence' : 'Click to set recurrence' }}</p>
            @endif
        </div>
        @if(!$isInactive)
        <div x-show="editing.recurrence_pattern" class="mt-1">
            <input type="text" x-model="fields.recurrence_pattern"
                   x-ref="recurrence_patternInput"
                   placeholder="e.g., daily, every Monday, weekdays"
                   @keydown.enter="saveField('recurrence_pattern')"
                   @keydown.escape.stop="cancelEdit('recurrence_pattern')"
                   class="w-full rounded-md bg-gray-700 border-gray-600 text-gray-100 placeholder-gray-500 shadow-sm focus:border-blue-500 focus:ring-blue-500 text-sm">
            <label class="flex items-center mt-2 text-sm text-gray-400 cursor-pointer">
                <input type="checkbox" x-model="fields.recurrence_floating"
                       class="rounded bg-gray-700 border-gray-600 text-purple-500 focus:ring-purple-500 mr-2">
                Floating (next date relative to completion)
            </label>
            <div class="flex gap-2 mt-2">
                <button @click="saveRecurrence()"
                        class="px-3 py-1 bg-blue-600 text-white text-sm rounded hover:bg-blue-700">Save</button>
                <button @click="cancelEdit('recurrence_pattern')"
                        class="px-3 py-1 bg-gray-700 text-gray-300 text-sm rounded hover:bg-gray-600">Cancel</button>
            </div>
        </div>
        @endif
    </div>

    <!-- Tags -->
    <div>
        <span class="text-xs font-medium text-gray-500 uppercase tracking-wide">Tags</span>
        <div @if(!$isInactive) @click="startEdit('tag_ids')" @endif
             x-show="!editing.tag_ids"
             class="mt-1 p-2 rounded min-h-[36px] {{ !$isInactive ? 'cursor-pointer hover:bg-gray-700' : '' }}">
            @if($task->tags->count() > 0)
                <div class="flex gap-1 flex-wrap">
                    @foreach($task->tags as $tag)
                        <span class="inline-block px-2 py-0.5 text-xs rounded"
                              style="background-color: {{ $tag->color }}22; color: {{ $tag->color }}">
                            {{ $tag->tag_name }}
                        </span>
                    @endforeach
                </div>
            @else
                <p class="text-sm text-gray-400 italic">{{ $isInactive ? 'No tags' : 'No tags — click to add' }}</p>
            @endif
        </div>
        @if(!$isInactive)
        <div x-show="editing.tag_ids" class="mt-1"
             x-ref="tag_idsInput" tabindex="-1"
             @keydown.escape.stop="cancelEdit('tag_ids')">
            <div class="space-y-1.5 mb-2 max-h-40 overflow-y-auto border border-gray-600 bg-[#101010] rounded p-3">
                @forelse($tags as $tag)
                    <label class="flex items-center">
                        <input type="checkbox" value="{{ $tag->id }}" x-model="fields.tag_ids"
                               class="rounded border-gray-600 bg-gray-700 text-blue-600 focus:ring-blue-500">
                        <span class="ml-2 text-sm" style="color: {{ $tag->color }}">{{ $tag->tag_name }}</span>
                    </label>
                @empty
                    <p class="text-sm text-gray-500">No tags available.</p>
                @endforelse
            </div>
            <div class="flex gap-2 mt-2">
                <button @click="saveField('tag_ids')" class="px-3 py-1 bg-blue-600 text-white text-sm rounded hover:bg-blue-700">Save</button>
                <button @click="cancelEdit('tag_ids')" class="px-3 py-1 bg-gray-700 text-gray-300 text-sm rounded hover:bg-gray-600">Cancel</button>
            </div>
        </div>
        @endif
    </div>

    <!-- Assignees -->
    @if($task->creator_id === Auth::id())
    <div>
        <span class="text-xs font-medium text-gray-500 uppercase tracking-wide">Assigned To</span>
        <div @if(!$isInactive) @click="startEdit('assignee_ids')" @endif
             x-show="!editing.assignee_ids"
             class="mt-1 p-2 rounded {{ !$isInactive ? 'cursor-pointer hover:bg-gray-700' : '' }}">
            <div class="space-y-0.5">
                @forelse($task->assignees as $assignee)
                    <p class="text-sm text-gray-300">{{ $assignee->name }}</p>
                @empty
                    <p class="text-sm text-gray-400 italic">Unassigned</p>
                @endforelse
            </div>
        </div>
        @if(!$isInactive)
        <div x-show="editing.assignee_ids" class="mt-1"
             x-ref="assignee_idsInput" tabindex="-1"
             @keydown.escape.stop="cancelEdit('assignee_ids')">
            <div class="space-y-1.5 mb-2 max-h-40 overflow-y-auto border border-gray-600 bg-[#101010] rounded p-3">
                @foreach($users as $user)
                    <label class="flex items-center {{ $user->id === Auth::id() ? 'cursor-not-allowed opacity-60' : '' }}">
                        <input type="checkbox" value="{{ $user->id }}" x-model="fields.assignee_ids"
                               class="rounded border-gray-600 bg-gray-700 text-blue-600 focus:ring-blue-500"
                               @if($user->id === Auth::id()) disabled title="You cannot remove yourself as task creator" @endif>
                        <span class="ml-2 text-sm text-gray-300">{{ $user->name }}</span>
                    </label>
                @endforeach
            </div>
            <div class="flex gap-2 mt-2">
                <button @click="saveField('assignee_ids')" class="px-3 py-1 bg-blue-600 text-white text-sm rounded hover:bg-blue-700">Save</button>
                <button @click="cancelEdit('assignee_ids')" class="px-3 py-1 bg-gray-700 text-gray-300 text-sm rounded hover:bg-gray-600">Cancel</button>
            </div>
        </div>
        @endif
    </div>
    @else
    <div>
        <span class="text-xs font-medium text-gray-500 uppercase tracking-wide">Assigned To</span>
        <div class="mt-1 p-2 space-y-0.5">
            @foreach($task->assignees as $assignee)
                <p class="text-sm text-gray-300">{{ $assignee->name }}</p>
            @endforeach
        </div>
    </div>
    @endif

    <!-- Tabbed section: Comments / Subtasks / Attachments -->
    <div x-data="tabSwitcher" class="border border-gray-700 rounded-lg bg-[#202020]">

        <!-- Tab bar -->
        <div class="flex border-b border-gray-700 overflow-x-auto">
            <button @click="tab = 'comments'"
                    :class="tab === 'comments' ? 'border-b-2 border-blue-500 text-gray-100' : 'text-gray-400 hover:text-gray-200'"
                    class="flex-shrink-0 flex items-center gap-1.5 px-4 py-2.5 text-sm font-medium transition-colors whitespace-nowrap">
                Comments
                @if($task->comments->count() > 0)
                    <span class="px-1.5 py-0.5 text-xs rounded-full bg-gray-700 text-gray-300">{{ $task->comments->count() }}</span>
                @endif
            </button>
            <button @click="tab = 'subtasks'"
                    :class="tab === 'subtasks' ? 'border-b-2 border-blue-500 text-gray-100' : 'text-gray-400 hover:text-gray-200'"
                    class="flex-shrink-0 flex items-center gap-1.5 px-4 py-2.5 text-sm font-medium transition-colors whitespace-nowrap">
                Subtasks
                @if($task->children->count() > 0)
                    <span class="px-1.5 py-0.5 text-xs rounded-full bg-gray-700 text-gray-300">{{ $task->children->count() }}</span>
                @endif
            </button>
            <button @click="tab = 'attachments'"
                    :class="tab === 'attachments' ? 'border-b-2 border-blue-500 text-gray-100' : 'text-gray-400 hover:text-gray-200'"
                    class="flex-shrink-0 flex items-center gap-1.5 px-4 py-2.5 text-sm font-medium transition-colors whitespace-nowrap">
                Attachments
                @if($task->attachments->count() > 0)
                    <span class="px-1.5 py-0.5 text-xs rounded-full bg-gray-700 text-gray-300">{{ $task->attachments->count() }}</span>
                @endif
            </button>
            <button @click="tab = 'activity'"
                    :class="tab === 'activity' ? 'border-b-2 border-blue-500 text-gray-100' : 'text-gray-400 hover:text-gray-200'"
                    class="flex-shrink-0 flex items-center gap-1.5 px-4 py-2.5 text-sm font-medium transition-colors whitespace-nowrap">
                Activity
                @if($task->changeLogs->count() > 0)
                    <span class="px-1.5 py-0.5 text-xs rounded-full bg-gray-700 text-gray-300">{{ $task->changeLogs->count() }}</span>
                @endif
            </button>
        </div>

        <!-- Tab content -->
        <div class="p-4">

            <!-- Comments -->
            <div x-show="tab === 'comments'">
                <div class="space-y-3 mb-4">
                    @forelse($task->comments as $comment)
                        <div class="border-l-2 border-gray-600 pl-3">
                            <div class="flex items-center justify-between">
                                <span class="text-sm font-medium text-gray-300">{{ $comment->user->name }}</span>
                                <div class="flex items-center gap-2">
                                    <span class="text-xs text-gray-500">{{ $comment->created_at->diffForHumans() }}</span>
                                    @if($task->creator_id === Auth::id())
                                        <form method="POST" action="{{ route('comments.destroy', [$task, $comment]) }}" class="inline">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="text-xs text-red-600 hover:underline"
                                                    @click.prevent="confirmSubmit($el, 'Delete this comment?')">Delete</button>
                                        </form>
                                    @endif
                                </div>
                            </div>
                            <div class="markdown-body mt-0.5 text-sm">{!! render_body($comment->comment) !!}</div>
                            @if($comment->file_path)
                                <p class="mt-0.5 text-xs">
                                    <a href="{{ route('comments.download', [$task, $comment]) }}"
                                       class="text-blue-400 hover:underline">
                                        📎 {{ strlen($comment->original_filename) > 15 ? substr($comment->original_filename, 0, 12) . '...' : $comment->original_filename }}
                                    </a>
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
                    <textarea name="comment" rows="2" required
                              class="w-full rounded-md bg-gray-700 border-gray-600 text-gray-100 placeholder-gray-500 shadow-sm focus:border-blue-500 focus:ring-blue-500 text-sm mb-2"
                              placeholder="Add a comment..."></textarea>
                    <div class="flex items-center gap-3" x-data="fileInput">
                        <label class="flex items-center gap-2 cursor-pointer">
                            <span class="px-2 py-1 bg-gray-700 border border-gray-600 text-gray-300 text-xs rounded hover:bg-gray-600">Choose file</span>
                            <span class="text-xs text-gray-400" x-text="fileName || 'No file chosen'"></span>
                            <input type="file" name="attachment" class="hidden"
                                   accept=".jpg,.jpeg,.png,.webp,.gif,.heic,.heif,.pdf,.doc,.docx,.xls,.xlsx,.ppt,.pptx,.odt,.ods,.odp,.csv,.txt,.json"
                                   @change="fileName = $event.target.files[0] ? ($event.target.files[0].name.length > 20 ? $event.target.files[0].name.slice(0, 20) + '…' : $event.target.files[0].name) : ''">
                        </label>
                        <button type="submit" class="px-3 py-1.5 bg-blue-600 text-white text-sm rounded hover:bg-blue-700">
                            Post
                        </button>
                    </div>
                </form>
                @endif
            </div>

            <!-- Subtasks -->
            <div x-show="tab === 'subtasks'">
                @if($task->children->count() > 0)
                    <div class="space-y-2 mb-3">
                        @foreach($task->children as $child)
                            <div class="flex items-center gap-3 p-2 bg-gray-800 rounded border border-gray-700">
                                <div class="flex-shrink-0 w-4 h-4 rounded-full border-2 {{ $child->status === 'done' ? 'bg-green-600 border-green-600' : 'border-gray-500' }}"></div>
                                <a href="{{ route('tasks.show', $child) }}"
                                   class="flex-1 text-sm {{ $child->status === 'done' ? 'text-gray-500 line-through' : 'text-gray-300 hover:text-gray-100' }}">
                                    {{ $child->name }}
                                </a>
                            </div>
                        @endforeach
                    </div>
                    <p class="text-xs text-gray-500 mb-2">
                        {{ $task->incompleteChildren()->count() }} of {{ $task->children->count() }} incomplete
                    </p>
                @else
                    <p class="text-sm text-gray-500 mb-3">No subtasks yet.</p>
                @endif
                @if(!$isInactive)
                <a href="{{ route('tasks.create', ['parent_id' => $task->id]) }}"
                   class="inline-flex items-center px-3 py-1 bg-blue-600 text-white text-sm rounded hover:bg-blue-700">
                    + Add Subtask
                </a>
                @endif
            </div>

            <!-- Attachments -->
            <div x-show="tab === 'attachments'">
                @if($task->attachments->count() > 0)
                    <div class="space-y-2 mb-3">
                        @foreach($task->attachments as $attachment)
                            <div class="flex items-center gap-3 p-2 bg-gray-700 border border-gray-600 rounded">
                                <div class="flex-shrink-0 w-12 h-12 bg-gray-600 rounded overflow-hidden flex items-center justify-center">
                                    @if(str_starts_with($attachment->mime_type, 'image/'))
                                        <img src="{{ route('attachments.view', [$task, $attachment]) }}"
                                             alt="{{ $attachment->original_filename }}"
                                             class="w-full h-full object-cover">
                                    @else
                                        <svg class="w-6 h-6 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                  d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z"/>
                                        </svg>
                                    @endif
                                </div>
                                <div class="flex-1 flex items-center justify-between min-w-0">
                                    <span class="text-sm text-gray-300 truncate" title="{{ $attachment->original_filename }}">
                                        {{ $attachment->original_filename }}
                                    </span>
                                    <div class="flex gap-2 ml-2 flex-shrink-0">
                                        <a href="{{ route('attachments.download', [$task, $attachment]) }}"
                                           class="text-xs text-blue-400 hover:underline">Download</a>
                                        @if($task->creator_id === Auth::id())
                                            <form method="POST" action="{{ route('attachments.destroy', [$task, $attachment]) }}" class="inline">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="text-xs text-red-600 hover:underline"
                                                        @click.prevent="confirmSubmit($el, 'Delete this attachment?')">Delete</button>
                                            </form>
                                        @endif
                                    </div>
                                </div>
                            </div>
                        @endforeach
                    </div>
                @else
                    <p class="text-sm text-gray-500 mb-3">No attachments yet.</p>
                @endif
                @if(!$isInactive)
                <form method="POST" action="{{ route('attachments.store', $task) }}" enctype="multipart/form-data">
                    @csrf
                    <div class="flex gap-2 items-center" x-data="fileInput">
                        <label class="flex items-center gap-2 cursor-pointer">
                            <span class="px-2 py-1 bg-gray-700 border border-gray-600 text-gray-300 text-xs rounded hover:bg-gray-600">Choose file</span>
                            <span class="text-xs text-gray-400" x-text="fileName || 'No file chosen'"></span>
                            <input type="file" name="attachment" required class="hidden"
                                   accept=".jpg,.jpeg,.png,.webp,.gif,.heic,.heif,.pdf,.doc,.docx,.xls,.xlsx,.ppt,.pptx,.odt,.ods,.odp,.csv,.txt,.json"
                                   @change="fileName = $event.target.files[0] ? ($event.target.files[0].name.length > 20 ? $event.target.files[0].name.slice(0, 20) + '…' : $event.target.files[0].name) : ''">
                        </label>
                        <button type="submit" class="px-3 py-1.5 bg-blue-600 text-white text-sm rounded hover:bg-blue-700 flex-shrink-0">
                            Upload
                        </button>
                    </div>
                </form>
                @endif
            </div>

            <!-- Activity -->
            <div x-show="tab === 'activity'">
                <x-change :changes="$task->changeLogs" />
            </div>

        </div>
    </div>{{-- end tabs --}}

</div>{{-- end panel body x-data --}}

{{-- taskPanelEditor is registered in the main layout's nonce script (alpine:init). --}}
{{-- Per-task data is passed via data-task-json on the x-data element above. --}}
