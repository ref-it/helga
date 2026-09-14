@php
    $rangeStart = \Illuminate\Support\Facades\Date::parse($start);
    $rangeEnd = \Illuminate\Support\Facades\Date::parse($end);
@endphp

{{-- Each end stays in one piece, and the dash keeps the company of the one
     before it: where the line is too narrow for the range, it breaks after
     the dash rather than anywhere inside a date. --}}
<span class="whitespace-nowrap">{{ $rangeStart->translatedFormat('D, d.m.Y, H:i') }} &ndash;</span>
@if ($rangeStart->isSameDay($rangeEnd))
    <span class="whitespace-nowrap">{{ $rangeEnd->translatedFormat('H:i') }}</span>
@else
    <span class="whitespace-nowrap">{{ $rangeEnd->translatedFormat('D, d.m.Y, H:i') }}</span>
@endif
