<div class="flex flex-col md:flex-row md:items-start justify-between gap-6">
    <h1 class="text-2xl font-semibold">{{ $plan->title }}</h1>

    @if ($plan->logoUrl())
        <img src="{{ $plan->logoUrl() }}" alt="{{ $plan->title }}" class="h-18 shrink-0 object-contain">
    @endif
</div>

@include('partials.rich_text', ['html' => $plan->description])

@if (!empty($plan->contact_email) || !empty($plan->contact_phone))
    <p>
        <strong>{{ __('plan.responsible') }}:</strong>
        @if (!empty($plan->contact_email))
            <flux:link href="mailto:{{ $plan->contact_email }}">{{ $plan->contact_email }}</flux:link>
        @endif
        @if (!empty($plan->contact_email) && !empty($plan->contact_phone))
            &nbsp;|&nbsp;
        @endif
        @if (!empty($plan->contact_phone))
            <flux:link href="tel:{{ str_replace(' ', '', $plan->contact_phone) }}">{{ $plan->contact_phone }}</flux:link>
        @endif
    </p>
@endif
