{{-- spans only: this also renders inside the accordion's <button> --}}
<span class="flex flex-col items-start gap-3">
    <span class="flex flex-wrap items-center gap-3">
        <span class="text-base font-medium">{{ $shift->title }}</span>
        @if($shift->requires_health_certificate)
            <flux:badge size="sm" color="yellow" icon="circle-alert">{{ __('shift.healthCertificateRequired') }}</flux:badge>
        @endif
    </span>
    <span class="block text-sm font-normal">
        {!! \App\Http\Controllers\PlanController::buildDateHtml($shift->start, $shift->end) !!}
    </span>
</span>
