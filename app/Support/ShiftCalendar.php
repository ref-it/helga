<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\Shift;
use DateTimeImmutable;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Ramsey\Uuid\Uuid;
use Spatie\IcalendarGenerator\Components\Calendar;
use Spatie\IcalendarGenerator\Components\Event;
use Spatie\IcalendarGenerator\Properties\TextProperty;

/**
 * Builds the iCalendar (RFC 5545) representation of a shift, so that helpers
 * can put it into their own calendar.
 */
class ShiftCalendar
{
    public function build(Shift $shift, string $url): string
    {
        return Calendar::create()
            // Identifies the product that wrote the file, and is what a
            // calendar shows when it names the source of an entry. RFC 5545
            // only recommends the //vendor//product//lang form, so the plain
            // name is valid.
            ->productIdentifier((string) config('app.name', 'HELGA'))
            // the times go in as UTC instants, which need no VTIMEZONE
            ->withoutAutoTimezoneComponents()
            ->event(
                Event::create()
                    ->uniqueIdentifier($this->uid($url))
                    ->name($shift->title)
                    ->description($this->description($shift))
                    ->url($url)
                    ->startsAt($this->dateTime((string) $shift->start))
                    ->endsAt($this->dateTime((string) $shift->end))
            )
            // Says the file publishes an appointment rather than inviting
            // anyone to one - without it a mail client may take the
            // attachment for an ordinary file instead of an entry to add.
            ->appendProperty(TextProperty::create('METHOD', 'PUBLISH'))
            ->get()
            // the builder joins the lines with CRLF but leaves the last one
            // bare, where RFC 5545 section 3.1 ends every content line with it
            ."\r\n";
    }

    /**
     * The filename the download is offered under.
     */
    public function filename(Shift $shift): string
    {
        return Str::slug($shift->title ?: 'shift').'.ics';
    }

    /**
     * One shift time as the calendar should read it.
     *
     * Shifts are entered through datetime-local fields, stored without a
     * timezone and shown back unchanged, so the wall-clock time alone says
     * nothing about the instant it means. The application timezone is what
     * fills that in: the time is read in it and handed on as UTC.
     *
     * Not as TZID: that would oblige us to ship a VTIMEZONE component
     * carrying the daylight-saving rules, where an absolute UTC instant needs
     * nothing and is unambiguous in every client.
     */
    private function dateTime(string $value): DateTimeImmutable
    {
        return Date::parse($value, $this->timezone())->utc()->toDateTimeImmutable();
    }

    /**
     * The configured application timezone. An empty setting means the same
     * here as it does to the framework, which only calls
     * date_default_timezone_set() for a non-empty value: UTC.
     */
    private function timezone(): string
    {
        $zone = trim((string) config('app.timezone'));

        if ($zone === '') {
            return 'UTC';
        }

        // a typo here would silently move every appointment, so it is caught
        // rather than left to produce plausible-looking wrong times
        if (! in_array($zone, timezone_identifiers_list(), true)) {
            throw new InvalidArgumentException(
                'APP_TIMEZONE is not a known timezone identifier: '.$zone,
            );
        }

        return $zone;
    }

    /**
     * A UUID for the shift, derived from its address rather than drawn at
     * random: version 5 hashes a name inside a namespace, so the same shift
     * always yields the same identifier. That is what makes re-importing
     * update the entry instead of adding a second one - a random UUID would
     * fill the calendar with duplicates on every export.
     */
    private function uid(string $url): string
    {
        return Uuid::uuid5(Uuid::NAMESPACE_URL, $url)->toString();
    }

    /**
     * The plan's name plus the shift's own text, as plain text: a calendar
     * entry carries no markup, and the description is sanitized HTML.
     */
    private function description(Shift $shift): string
    {
        $parts = [$shift->plan->title];

        // The block elements have to leave a break behind them before the
        // tags go: strip_tags() drops them without a trace, and two
        // paragraphs would run into each other as "...sein.Treffpunkt...".
        $html = preg_replace('#</(?:p|li|blockquote|h[1-6])\s*>|<br\s*/?>#i', "\n", (string) $shift->description);

        $text = trim(html_entity_decode(strip_tags((string) $html), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
        if ($text !== '') {
            // spaces and tabs collapse, but the breaks between blocks stay
            $text = preg_replace('/[^\S\n]+/u', ' ', $text) ?? $text;
            $parts[] = trim(preg_replace('/\n{2,}/', "\n", $text) ?? $text);
        }

        return implode("\n", array_filter($parts));
    }
}
