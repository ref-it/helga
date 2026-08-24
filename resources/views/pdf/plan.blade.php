<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}">
<head>
    <meta charset="utf-8">
    <title>{{ $plan->title }}</title>
    <style>
        @page {
            /* left margin wide enough to punch holes without hitting the text (DIN 5008 Lochrand) */
            margin: 12mm 15mm 15mm 22mm;
        }

        body {
            font-family: sans-serif;
            font-size: 11pt;
            color: #111;
        }

        .plan-header {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 5mm;
        }

        .plan-header td {
            vertical-align: top;
        }

        .plan-logo-cell {
            padding-left: 8mm;
            text-align: right;
        }

        .plan-logo {
            height: 12mm;
        }

        h1 {
            font-size: 16pt;
            margin: 0 0 2mm 0;
        }

        .plan-meta {
            margin-bottom: 5mm;
        }

        .plan-description {
            font-size: 10pt;
            color: #000;
        }

        .plan-contact {
            font-size: 10pt;
            color: #444;
        }

        .plan-description {
            margin: 0 0 2mm 0;
        }

        .group-heading {
            font-size: 12pt;
            font-weight: bold;
            background: #eee;
            text-align: center;
            padding: 2mm 3mm;
            margin: 15mm 0 5mm 0;
        }

        .shift {
            margin-bottom: 7mm;
        }

        .shift-heading {
            width: 100%;
            border-spacing: 0 2px;
            border-bottom: 0.5pt solid #000;
            padding-bottom: 1mm;
            page-break-inside: avoid;
        }

        .shift-heading td {
            vertical-align: bottom;
        }

        .shift-title {
            font-size: 12pt;
            font-weight: bold;
        }

        .health-cert-badge {
            display: inline-block;
            font-size: 8pt;
            font-weight: bold;
            color: #666;
            border: 0.5pt solid #666;
            padding: 0.5mm 1.5mm;
            margin-top: 1mm;
        }

        .shift-meta {
            font-size: 10pt;
            color: #000;
        }

        .shift-description {
            font-size: 10pt;
            color: #000;
            margin-bottom: 2mm;
        }

        table.helpers {
            width: 100%;
            border-collapse: collapse;
        }

        table.helpers th {
            text-align: left;
            font-size: 10pt;
            color: #555;
            border-bottom: 0.5pt solid #999;
            border-right: 0.3pt solid #999;
            padding: 1mm 2mm;
        }

        table.helpers td {
            border-bottom: 0.3pt solid #999;
            border-right: 0.3pt solid #999;
            padding: 2mm;
            vertical-align: top;
            /* generous row height so empty slots can be filled in by hand after printing */
            height: 10mm;
        }

        table.helpers th:last-child,
        table.helpers td:last-child {
            border-right: none;
        }

        table.helpers td.index {
            width: 6mm;
            color: #666;
        }

        table.helpers td.name {
            width: 45%;
        }

        table.helpers th.size,
        table.helpers td.size {
            width: 1%;
            white-space: nowrap;
        }

        table.helpers .contact-info {
            font-size: 8pt;
            color: #000;
        }
    </style>
</head>
<body>
    <table class="plan-header">
        <tr>
            <td>
                <h1>{{ $plan->title }}</h1>
            </td>
            @if($plan->logoDataUri())
                <td class="plan-logo-cell">
                    <img src="{{ $plan->logoDataUri() }}" alt="{{ $plan->title }}" class="plan-logo">
                </td>
            @endif
        </tr>
    </table>

    <div class="plan-meta">
        @if($plan->description)
            <div class="plan-description">
                {!! $plan->description !!}
            </div>
        @endif
        @if(!empty($plan->contact_email) || !empty($plan->contact_phone))
            <div class="plan-contact">
                <p><strong>{{ __('plan.responsible') }}:</strong>
                    @if(!empty($plan->contact_email))
                        {{ $plan->contact_email }}
                    @endif
                    @if(!empty($plan->contact_email) && !empty($plan->contact_phone))
                        |
                    @endif
                    @if(!empty($plan->contact_phone))
                        {{ $plan->contact_phone }}
                    @endif
                </p>
            </div>
        @endif
    </div>

    @foreach($plan->shifts as $index => $shift)
        @if($loop->first || $plan->shifts[$index - 1]->type !== $shift->type)
            @if($shift->type !== '')
                <div class="group-heading">{{ $categoryNames[$shift->type] ?? $shift->type }}</div>
            @endif
        @endif

        <div class="shift">
            <table class="shift-heading">
                <tr>
                    <td class="shift-title">
                        {{ $shift->title }}
                        @if($shift->requires_health_certificate)
                            <br><span class="health-cert-badge">{{ __('shift.healthCertificateRequired') }}</span>
                        @endif
                    </td>
                    <td class="shift-meta" style="text-align: right;">
                        {!! \App\Http\Controllers\PlanController::buildDateString($shift->start, $shift->end) !!}
                    </td>
                </tr>
            </table>

            @if($shift->description)
                <div class="shift-description">{!! $shift->description !!}</div>
            @endif

            <table class="helpers">
                <thead>
                    <tr>
                        <th class="index"></th>
                        <th class="name">{{ __('subscription.name') }}</th>
                        @if($shift->requires_clothing_size)
                            <th class="size">{{ __('subscription.clothingSizeAbbr') }}</th>
                        @endif
                        <th>{{ __('subscription.comment') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @for($slot = 0; $slot < max($shift->team_size, $shift->subscriptions->count()); $slot++)
                        <tr>
                            <td class="index">{{ $slot + 1 }}</td>
                            <td class="name">
                                {{ $shift->subscriptions[$slot]->name ?? '' }}
                                @if($shift->subscriptions[$slot] ?? null)
                                    <div class="contact-info">
                                        {{ $shift->subscriptions[$slot]->email }}
                                        @if($shift->subscriptions[$slot]->phone)
                                            <br>{{ $shift->subscriptions[$slot]->phone }}
                                        @endif
                                    </div>
                                @endif
                            </td>
                            @if($shift->requires_clothing_size)
                                <td class="size">{{ $shift->subscriptions[$slot]->clothing_size ?? '' }}</td>
                            @endif
                            <td>{{ $shift->subscriptions[$slot]->comment ?? '' }}</td>
                        </tr>
                    @endfor
                </tbody>
            </table>
        </div>
    @endforeach

    <script type="text/php">
        if (isset($pdf)) {
            $font = $fontMetrics->getFont('sans-serif');
            $size = 10;
            // the placeholder text is only used to estimate the right-aligned
            // x position - {PAGE_NUM}/{PAGE_COUNT} are substituted with the
            // real numbers per page after rendering, once the page count is known
            $sampleWidth = $fontMetrics->getTextWidth('00 / 00', $font, $size);
            $rightMargin = 12 * 72 / 25.4; // matches the @page right margin (12mm)
            $x = $pdf->get_width() - $rightMargin - $sampleWidth;
            $y = $pdf->get_height() - 45;
            $pdf->page_text($x, $y, '{PAGE_NUM} / {PAGE_COUNT}', $font, $size, array(0.4, 0.4, 0.4));
        }
    </script>
</body>
</html>
