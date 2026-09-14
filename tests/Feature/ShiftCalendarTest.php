<?php

use App\Models\Shift;
use App\Models\User;
use App\Notifications\SendEmailVerification;
use App\Support\ShiftCalendar;
use Illuminate\Support\Facades\Notification;

/**
 * The lines of an iCalendar file, unfolded again: a continuation starts with
 * a space after the break, which has to come back out before the content can
 * be read.
 *
 * @return list<string>
 */
function icalLines(string $ics): array
{
    return explode("\r\n", str_replace("\r\n ", '', trim($ics)));
}

test('a shift can be fetched as a calendar entry', function (): void {
    $owner = User::factory()->create();
    $plan = createOwnedPlan($owner);
    // new plans start inactive, and an inactive plan is not public
    $plan->update(['title' => 'Sommerfest', 'active' => true]);
    $shift = $plan->shifts()->create([
        // comma and semicolon are delimiters in the format itself
        'title' => 'Bar Aufbau, Schicht 1; Treffpunkt',
        'group' => 0,
        'start' => '2026-09-13 14:30:00',
        'end' => '2026-09-13 17:30:00',
        'team_size' => 2,
        'description' => '<p>Bitte pünktlich sein.</p>',
    ]);

    $response = $this->get(route('plan.shift.calendar', ['plan' => $plan->view_id, 'shift' => $shift]));

    $response->assertOk();
    expect($response->headers->get('content-type'))->toStartWith('text/calendar');
    expect($response->headers->get('content-disposition'))->toContain('.ics');

    $lines = icalLines($response->getContent());

    expect($lines)->toContain('BEGIN:VCALENDAR');
    expect($lines)->toContain('END:VCALENDAR');
    expect($lines)->toContain('SUMMARY:Bar Aufbau\\, Schicht 1\\; Treffpunkt');

    // the wall-clock times are read in the application timezone, UTC here,
    // and written as the absolute instant they stand for
    expect($lines)->toContain('DTSTART:20260913T143000Z');
    expect($lines)->toContain('DTEND:20260913T173000Z');

    // the description carries the plan and the shift's own text, as plain
    // text with the line break escaped
    expect($lines)->toContain('DESCRIPTION:Sommerfest\nBitte pünktlich sein.');
});

test('a calendar entry keeps a stable identifier so re-importing updates it', function (): void {
    $owner = User::factory()->create();
    $plan = createOwnedPlan($owner);
    $plan->update(['active' => true]);
    $shift = createShiftForPlan($plan);

    $first = icalLines($this->get(route('plan.shift.calendar', ['plan' => $plan->view_id, 'shift' => $shift]))->getContent());
    $second = icalLines($this->get(route('plan.shift.calendar', ['plan' => $plan->view_id, 'shift' => $shift]))->getContent());

    $uid = fn (array $lines): string => collect($lines)->first(fn (string $l): bool => str_starts_with($l, 'UID:')) ?? '';

    // a UUID, and the same one every time: version 5 derives it from the
    // shift's address, so an export does not hand out a fresh identifier and
    // leave the calendar with duplicates
    expect($uid($first))->toMatch('/^UID:[0-9a-f]{8}-[0-9a-f]{4}-5[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/');
    expect($uid($second))->toBe($uid($first));
});

test('a shift of another plan is not served under that plan', function (): void {
    $owner = User::factory()->create();
    // both active, so a refusal can only come from the mismatch itself
    $plan = createOwnedPlan($owner);
    $plan->update(['active' => true]);
    $other = createOwnedPlan($owner);
    $other->update(['active' => true]);
    $shift = createShiftForPlan($other);

    // the shift has to belong to the plan in the URL, or the view link of one
    // plan would hand out the shifts of another
    $this->get(route('plan.shift.calendar', ['plan' => $plan->view_id, 'shift' => $shift]))
        ->assertForbidden();
});

test('the confirmation mail carries the shift as a calendar attachment', function (): void {
    Notification::fake();

    $owner = User::factory()->create();
    $plan = createOwnedPlan($owner);
    $shift = createShiftForPlan($plan);
    $subscription = $shift->subscriptions()->create(['name' => 'Jane Doe', 'email' => 'jane@example.com']);

    $subscription->sendEmailVerification();

    Notification::assertSentTo(
        $subscription,
        SendEmailVerification::class,
        function (SendEmailVerification $notification) use ($subscription): bool {
            $mail = $notification->toMail($subscription);

            // one attachment, and it is the shift as an appointment
            $attachment = $mail->rawAttachments[0] ?? null;

            return $attachment !== null
                && str_ends_with((string) $attachment['name'], '.ics')
                && str_contains((string) $attachment['data'], 'BEGIN:VEVENT');
        },
    );
});

test('a description keeps the breaks between its blocks', function (): void {
    $owner = User::factory()->create();
    $plan = createOwnedPlan($owner);
    $plan->update(['title' => 'Sommerfest', 'active' => true]);
    $shift = $plan->shifts()->create([
        'title' => 'Bar Aufbau',
        'group' => 0,
        'start' => '2026-09-13 14:30:00',
        'end' => '2026-09-13 17:30:00',
        'team_size' => 1,
        'description' => '<p>Erster Absatz.</p><p>Zweiter Absatz.</p><ul><li>Ein Punkt</li></ul>',
    ]);

    $lines = icalLines($this->get(route('plan.shift.calendar', ['plan' => $plan->view_id, 'shift' => $shift]))->getContent());
    $description = collect($lines)->first(fn (string $l): bool => str_starts_with($l, 'DESCRIPTION:')) ?? '';

    // strip_tags() drops a block boundary without a trace, so the paragraphs
    // ran into each other as "Erster Absatz.Zweiter Absatz."
    expect($description)->toContain('Erster Absatz.\nZweiter Absatz.');
    expect($description)->toContain('Zweiter Absatz.\nEin Punkt');
    expect($description)->not->toContain('Absatz.Zweiter');
});

test('every line of a calendar file stays within the format limits', function (): void {
    $owner = User::factory()->create();
    $plan = createOwnedPlan($owner);
    $plan->update(['title' => str_repeat('Ein sehr langer Plantitel ', 6), 'active' => true]);
    $shift = $plan->shifts()->create([
        'title' => str_repeat('Eine Schicht mit überlangem Titel ', 4),
        'group' => 0,
        'start' => '2026-09-13 14:30:00',
        'end' => '2026-09-13 17:30:00',
        'team_size' => 1,
        'description' => '<p>'.str_repeat('Beschreibungstext mit Umlauten äöü. ', 8).'</p>',
    ]);

    $raw = $this->get(route('plan.shift.calendar', ['plan' => $plan->view_id, 'shift' => $shift]))->getContent();

    // RFC 5545 folds at 75 octets - bytes, not characters - and the lines end
    // with CRLF. A fold may not cut a UTF-8 sequence in half either.
    expect($raw)->toEndWith("\r\n");
    foreach (explode("\r\n", rtrim($raw, "\r\n")) as $line) {
        expect(strlen($line))->toBeLessThanOrEqual(75);
    }

    expect(mb_check_encoding($raw, 'UTF-8'))->toBeTrue();
});

/**
 * A shift at a fixed wall-clock time, for the timezone cases below.
 */
function shiftAt(User $owner, string $start, string $end): Shift
{
    $plan = createOwnedPlan($owner);
    $plan->update(['active' => true]);

    return $plan->shifts()->create([
        'title' => 'Bar Aufbau',
        'group' => 0,
        'start' => $start,
        'end' => $end,
        'team_size' => 1,
    ]);
}

test('an empty timezone setting is read as UTC, as the framework reads it', function (): void {
    config(['app.timezone' => '']);

    $shift = shiftAt(User::factory()->create(), '2026-09-13 14:30:00', '2026-09-13 17:30:00');
    $lines = icalLines(app(ShiftCalendar::class)->build($shift, 'https://example.com'));

    expect($lines)->toContain('DTSTART:20260913T143000Z');
    expect($lines)->toContain('DTEND:20260913T173000Z');
});

test('the application timezone turns the wall-clock times into an absolute instant', function (): void {
    config(['app.timezone' => 'Europe/Berlin']);
    $owner = User::factory()->create();
    $calendar = app(ShiftCalendar::class);

    // September is CEST, two hours ahead of UTC
    $summer = icalLines($calendar->build(shiftAt($owner, '2026-09-13 14:30:00', '2026-09-13 17:30:00'), 'https://example.com'));
    expect($summer)->toContain('DTSTART:20260913T123000Z');
    expect($summer)->toContain('DTEND:20260913T153000Z');

    // January is CET, one hour ahead - the same wall-clock time, a different
    // instant, which is the whole reason the zone has to be declared
    $winter = icalLines($calendar->build(shiftAt($owner, '2026-01-13 14:30:00', '2026-01-13 17:30:00'), 'https://example.com'));
    expect($winter)->toContain('DTSTART:20260113T133000Z');
    expect($winter)->toContain('DTEND:20260113T163000Z');
});

test('a misspelled timezone is refused rather than silently shifting every time', function (): void {
    config(['app.timezone' => 'Europe/Berln']);

    $shift = shiftAt(User::factory()->create(), '2026-09-13 14:30:00', '2026-09-13 17:30:00');

    // Carbon throws for an unknown zone too, and its exception extends
    // InvalidArgumentException - so the type alone would pass either way.
    // What is asserted is that the message names the setting to correct.
    expect(fn () => app(ShiftCalendar::class)->build($shift, 'https://example.com'))
        ->toThrow(InvalidArgumentException::class, 'APP_TIMEZONE');
});
