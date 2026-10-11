<?php

use App\Livewire\Plan\Show;
use App\Livewire\Subscription\Create;
use App\Models\Shift;
use App\Models\Subscription;
use App\Models\User;
use App\Notifications\SendEmailVerification;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\URL;
use Livewire\Livewire;

/**
 * Adds a second shift to the plan the given shift belongs to.
 */
function addShiftToPlan(Shift $shift, array $attributes = []): Shift
{
    return $shift->plan->shifts()->create(array_merge([
        'title' => 'Second shift',
        'description' => 'Another shift',
        'group' => 1,
        'start' => now()->addDay(),
        'end' => now()->addDay()->addHours(2),
        'team_size' => 2,
    ], $attributes));
}

test('a shift with room shows a checkbox on the plan page', function (): void {
    $shift = createPlanWithShift();

    $response = $this->get(route('plan.show', $shift->plan))->assertOk();

    expect(shiftCheckboxState($response->getContent(), $shift))->toBe('enabled');
});

test('a full shift shows a disabled checkbox', function (): void {
    $shift = createPlanWithShift();
    $shift->update(['team_size' => 1]);
    $shift->subscriptions()->create(['name' => 'Jane', 'email' => 'jane@example.com']);

    $response = $this->get(route('plan.show', $shift->plan))->assertOk();

    expect(shiftCheckboxState($response->getContent(), $shift))->toBe('disabled');
});

test('ticking shifts sends the visitor to the sign-up form for exactly those shifts', function (): void {
    $first = createPlanWithShift();
    $second = addShiftToPlan($first);

    Livewire::test(Show::class, ['plan' => $first->plan])
        ->assertDontSeeHtml('wire:click="subscribeToSelected"')
        ->set('selectedShifts', [(string) $first->id, (string) $second->id])
        ->assertSeeHtml('wire:click="subscribeToSelected"')
        ->call('subscribeToSelected')
        ->assertRedirect(route('plan.subscription.createMany', [
            'plan' => $first->plan->view_id,
            'shifts' => [$first->id, $second->id],
        ]));
});

test('a forged shift id is dropped from the selection', function (): void {
    $shift = createPlanWithShift();
    $otherPlansShift = createPlanWithShift();

    Livewire::test(Show::class, ['plan' => $shift->plan])
        ->set('selectedShifts', [(string) $shift->id, (string) $otherPlansShift->id])
        ->call('subscribeToSelected')
        ->assertRedirect(route('plan.subscription.createMany', [
            'plan' => $shift->plan->view_id,
            'shifts' => [$shift->id],
        ]));
});

test('clearing the selection empties it', function (): void {
    $shift = createPlanWithShift();

    Livewire::test(Show::class, ['plan' => $shift->plan])
        ->set('selectedShifts', [(string) $shift->id])
        ->call('clearSelection')
        ->assertSet('selectedShifts', []);
});

test('one sign-up creates a subscription for every selected shift', function (): void {
    $first = createPlanWithShift();
    $second = addShiftToPlan($first);
    $user = User::factory()->create(['name' => 'Jane Doe', 'email' => 'jane@example.com']);

    Livewire::actingAs($user)
        ->withQueryParams(['shifts' => [$first->id, $second->id]])
        ->test(Create::class, ['plan' => $first->plan])
        ->set('comment', 'See you there')
        ->call('save')
        ->assertHasNoErrors();

    expect($first->subscriptions()->where('email', 'jane@example.com')->count())->toBe(1)
        ->and($second->subscriptions()->where('email', 'jane@example.com')->count())->toBe(1)
        ->and($second->subscriptions()->first()->comment)->toBe('See you there');
});

test('the sign-up form lists every selected shift', function (): void {
    $first = createPlanWithShift();
    $second = addShiftToPlan($first);

    $this->get(route('plan.subscription.createMany', [
        'plan' => $first->plan->view_id,
        'shifts' => [$first->id, $second->id],
    ]))
        ->assertOk()
        ->assertSee($first->title)
        ->assertSee($second->title)
        ->assertSee($first->plan->title);
});

test('a shift the visitor cannot join is dropped before the form is shown', function (): void {
    $first = createPlanWithShift();
    $second = addShiftToPlan($first, ['team_size' => 1]);
    $second->subscriptions()->create(['name' => 'Someone', 'email' => 'someone@example.com']);

    $this->get(route('plan.subscription.createMany', [
        'plan' => $first->plan->view_id,
        'shifts' => [$first->id, $second->id],
    ]))
        ->assertOk()
        ->assertSee($first->title)
        ->assertDontSee($second->title);
});

test('the sign-up form redirects back to the plan when no selected shift is left', function (): void {
    $shift = createPlanWithShift();
    $shift->update(['team_size' => 1]);
    $shift->subscriptions()->create(['name' => 'Someone', 'email' => 'someone@example.com']);

    $this->get(route('plan.subscription.createMany', [
        'plan' => $shift->plan->view_id,
        'shifts' => [$shift->id],
    ]))->assertRedirect(route('plan.show', ['plan' => $shift->plan]));
});

test('the health certificate confirmation is required when only one of the selected shifts needs it', function (): void {
    $first = createPlanWithShift();
    $second = addShiftToPlan($first, ['requires_health_certificate' => true]);
    $user = User::factory()->create(['name' => 'Jane Doe', 'email' => 'jane@example.com']);

    Livewire::actingAs($user)
        ->withQueryParams(['shifts' => [$first->id, $second->id]])
        ->test(Create::class, ['plan' => $first->plan])
        ->call('save')
        ->assertHasErrors(['health_certificate_confirmed' => 'accepted']);

    expect($first->subscriptions()->count())->toBe(0)
        ->and($second->subscriptions()->count())->toBe(0);
});

test('a guest gets one verification email covering every shift they signed up for', function (): void {
    Notification::fake();
    config(['captcha.disable' => true]);

    $first = createPlanWithShift();
    $second = addShiftToPlan($first);

    Livewire::withQueryParams(['shifts' => [$first->id, $second->id]])
        ->test(Create::class, ['plan' => $first->plan])
        ->set('name', 'Jane Doe')
        ->set('email', 'jane@example.com')
        ->set('captcha', 'anything')
        ->call('save')
        ->assertHasNoErrors();

    Notification::assertCount(1);

    expect($first->subscriptions()->count())->toBe(1)
        ->and($second->subscriptions()->count())->toBe(1);
});

test('the one verification email names every shift and carries them in a single calendar file', function (): void {
    Notification::fake();
    config(['captcha.disable' => true]);

    $first = createPlanWithShift();
    $second = addShiftToPlan($first);

    Livewire::withQueryParams(['shifts' => [$first->id, $second->id]])
        ->test(Create::class, ['plan' => $first->plan])
        ->set('name', 'Jane Doe')
        ->set('email', 'jane@example.com')
        ->set('captcha', 'anything')
        ->call('save');

    $subscription = $first->subscriptions()->firstOrFail();

    Notification::assertSentTo(
        $subscription,
        SendEmailVerification::class,
        function (SendEmailVerification $notification) use ($subscription, $first, $second): bool {
            $mail = $notification->toMail($subscription);
            $lines = collect($mail->introLines)->implode("\n");

            // one file holding both shifts, rather than one attachment each
            $attachments = $mail->rawAttachments;

            return str_contains($lines, $first->title)
                && str_contains($lines, $second->title)
                && count($attachments) === 1
                && substr_count((string) $attachments[0]['data'], 'BEGIN:VEVENT') === 2;
        },
    );
});

test('the one verification link confirms the address for every shift at once', function (): void {
    config(['captcha.disable' => true]);

    $first = createPlanWithShift();
    $second = addShiftToPlan($first);

    Livewire::withQueryParams(['shifts' => [$first->id, $second->id]])
        ->test(Create::class, ['plan' => $first->plan])
        ->set('name', 'Jane Doe')
        ->set('email', 'jane@example.com')
        ->set('captcha', 'anything')
        ->call('save');

    $subscriptions = Subscription::where('email', 'jane@example.com')->get();

    expect($subscriptions)->toHaveCount(2)
        ->and($subscriptions->every(fn (Subscription $s): bool => ! $s->isEmailVerified()))->toBeTrue();

    $link = URL::temporarySignedRoute('plan.subscription.verifyEmails', now()->addDays(7), [
        'plan' => $first->plan,
        'subscriptions' => $subscriptions->pluck('id')->all(),
    ]);

    $this->get($link)->assertRedirect(route('plan.show', $first->plan));

    expect(Subscription::where('email', 'jane@example.com')->get()
        ->every(fn (Subscription $s): bool => $s->isEmailVerified()))->toBeTrue();
});

test('the one verification email keeps an unsubscribe link per shift', function (): void {
    Notification::fake();
    config(['captcha.disable' => true]);

    $first = createPlanWithShift();
    $first->plan->update(['allow_unsubscribe' => true]);
    $second = addShiftToPlan($first);

    Livewire::withQueryParams(['shifts' => [$first->id, $second->id]])
        ->test(Create::class, ['plan' => $first->plan])
        ->set('name', 'Jane Doe')
        ->set('email', 'jane@example.com')
        ->set('captcha', 'anything')
        ->call('save');

    $subscriptions = Subscription::where('email', 'jane@example.com')->get();

    expect($subscriptions->every(fn (Subscription $s): bool => $s->confirmation !== null))->toBeTrue();

    Notification::assertSentTo(
        $subscriptions->first(),
        SendEmailVerification::class,
        function (SendEmailVerification $notification) use ($subscriptions): bool {
            $lines = collect($notification->toMail($subscriptions->first())->outroLines)->implode("\n");

            return $subscriptions->every(fn (Subscription $s): bool => str_contains($lines, (string) $s->confirmation));
        },
    );
});

test('a verification link naming a subscription of another plan is rejected', function (): void {
    $shift = createPlanWithShift();
    $foreign = createPlanWithShift();
    $foreignSubscription = $foreign->subscriptions()->create(['name' => 'Jane', 'email' => 'jane@example.com']);

    $link = URL::temporarySignedRoute('plan.subscription.verifyEmails', now()->addDays(7), [
        'plan' => $shift->plan,
        'subscriptions' => [$foreignSubscription->id],
    ]);

    $this->get($link)->assertNotFound();

    expect($foreignSubscription->fresh()->isEmailVerified())->toBeFalse();
});

test('a verification link with a tampered subscription list is rejected', function (): void {
    $shift = createPlanWithShift();
    $subscription = $shift->subscriptions()->create(['name' => 'Jane', 'email' => 'jane@example.com']);
    $other = $shift->subscriptions()->create(['name' => 'John', 'email' => 'john@example.com']);

    $link = URL::temporarySignedRoute('plan.subscription.verifyEmails', now()->addDays(7), [
        'plan' => $shift->plan,
        'subscriptions' => [$subscription->id],
    ]);

    $this->get($link.'&subscriptions[]='.$other->id)->assertForbidden();

    expect($other->fresh()->isEmailVerified())->toBeFalse();
});

test('a shift that fills up while the form is open is skipped', function (): void {
    $first = createPlanWithShift();
    $second = addShiftToPlan($first, ['team_size' => 1]);
    $user = User::factory()->create(['name' => 'Jane Doe', 'email' => 'jane@example.com']);

    $component = Livewire::actingAs($user)
        ->withQueryParams(['shifts' => [$first->id, $second->id]])
        ->test(Create::class, ['plan' => $first->plan]);

    $second->subscriptions()->create(['name' => 'Faster', 'email' => 'faster@example.com']);

    $component->call('save')->assertHasNoErrors();

    expect($first->subscriptions()->where('email', 'jane@example.com')->count())->toBe(1)
        ->and($second->subscriptions()->where('email', 'jane@example.com')->count())->toBe(0);
});
