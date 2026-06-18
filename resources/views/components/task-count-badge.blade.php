@props(['count', 'breakdown' => [], 'italic' => false])
{{--
    Displays a task count with an optional project-breakdown tooltip on hover.

    Props:
      count     – PHP integer count (shown as static fallback before Alpine loads)
      breakdown – array of ['name' => string, 'count' => int], sorted descending
      italic    – whether to italicise the count text (used on the Undated view)

    Behaviour:
      • Before Alpine initialises: shows $count from PHP.
      • After Alpine initialises, not filtered: shows total with dotted underline.
      • After Alpine initialises, filtered: shows "showing X of [total]" where only
        [total] has the tooltip — not the filtered number.
--}}
<span x-data class="{{ $italic ? 'italic ' : '' }}text-sm text-gray-500 font-normal">
    {{-- Static fallback: visible before Alpine loads; hidden once it does --}}
    <span :class="$store.taskCount.ready ? 'hidden' : ''">{{ $count }}</span>

    {{-- Dynamic content: hidden by x-cloak until Alpine loads --}}
    <span x-cloak x-show="$store.taskCount.ready" class="inline">
        <span x-show="$store.taskCount.filtered" class="inline">showing&nbsp;<span x-text="$store.taskCount.visible"></span>&nbsp;of&nbsp;</span><span class="relative group inline-block {{ !empty($breakdown) ? 'underline decoration-dotted cursor-default' : '' }}">
            <span x-text="$store.taskCount.total"></span>
            @if(!empty($breakdown))
            <div class="absolute hidden group-hover:block bottom-full left-0 mb-1 bg-gray-900 border border-gray-600 rounded p-2 text-gray-200 z-50 shadow-lg min-w-max text-xs not-italic normal-case font-normal whitespace-nowrap">
                @foreach($breakdown as $item)
                <div class="flex items-center justify-between gap-6 py-0.5">
                    <span class="text-gray-300">{{ $item['name'] }}</span>
                    <span class="text-gray-400 tabular-nums">{{ $item['count'] }}</span>
                </div>
                @endforeach
            </div>
            @endif
        </span>
    </span>
</span>
