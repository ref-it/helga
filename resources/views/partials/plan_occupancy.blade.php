@php
    $totalSlots = $plan->totalSlotsCount();
    $filledSlots = $plan->filledSlotsCount();
@endphp

@include('partials.occupancy_ring', [
    'total' => $totalSlots,
    'filled' => $filledSlots,
    'title' => __('plan.slotsOccupied', ['filled' => $filledSlots, 'total' => $totalSlots]),
])
