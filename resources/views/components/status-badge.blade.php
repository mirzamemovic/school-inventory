@props(['status'])
{{-- Colour never travels alone: the badge always carries the word, because
     some users are colourblind and all of them are in a sunlit corridor. --}}
<span {{ $attributes->class(['inline-flex items-center rounded px-2 py-0.5 text-xs font-semibold whitespace-nowrap', $status->badgeClasses()]) }}>
    {{ $status->label() }}
</span>
