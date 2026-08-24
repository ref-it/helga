@php
    $rangeStart = \Illuminate\Support\Facades\Date::parse($start);
    $rangeEnd = \Illuminate\Support\Facades\Date::parse($end);
@endphp

@if ($rangeStart->isSameDay($rangeEnd))
    {{ $rangeStart->translatedFormat('D, d.m.Y, H:i') }} &ndash; {{ $rangeEnd->translatedFormat('H:i') }}
@else
    {{ $rangeStart->translatedFormat('D, d.m.Y, H:i') }} &ndash; {{ $rangeEnd->translatedFormat('D, d.m.Y, H:i') }}
@endif
