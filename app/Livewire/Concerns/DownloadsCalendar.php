<?php

declare(strict_types=1);

namespace App\Livewire\Concerns;

use App\Models\Shift;
use App\Support\ShiftCalendar;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Hands shifts to the visitor as a calendar file.
 *
 * The file is put together for whoever asked and for the page they asked
 * from, so it is served straight out of the component rather than from a URL
 * of its own - there is nothing here another visitor could be sent to.
 */
trait DownloadsCalendar
{
    /**
     * @param  iterable<Shift>  $shifts
     * @param  string  $title  what the file is named after
     * @param  string|null  $name  what a calendar shows the import as, for a
     *                             file holding more than a single appointment
     */
    protected function calendarDownload(iterable $shifts, string $title, ?string $name = null): StreamedResponse
    {
        $calendar = app(ShiftCalendar::class);

        $body = $calendar->buildMany(
            $shifts,
            fn (Shift $shift): string => route('plan.shift.show', [
                'plan' => $shift->plan->view_id,
                'shift' => $shift,
            ]),
            $name,
        );

        return response()->streamDownload(
            fn () => print ($body),
            $calendar->filenameFor($title),
            ['Content-Type' => 'text/calendar; charset=utf-8'],
        );
    }
}
