<?php

namespace App\Notifications;

use App\Http\Controllers\PlanController;
use App\Models\Shift;
use App\Models\Subscription;
use App\Support\ShiftCalendar;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Collection;

/**
 * The one email a sign-up produces, however many shifts it covered: a single
 * link confirming the address for all of them, every shift as one calendar
 * file, and - where the plan allows it - an unsubscribe link per shift.
 */
class SendEmailVerification extends Notification
{
    use Queueable;

    /**
     * @param  Collection<int, Subscription>  $subscriptions  the sign-ups this
     *                                                        email covers, all for the same address
     * @param  array<int, string>  $unsubscribeLinks  keyed by subscription id
     */
    public function __construct(
        private string $verifyLink,
        private Collection $subscriptions,
        private array $unsubscribeLinks = [],
    ) {}

    /**
     * Get the notification's delivery channels.
     *
     * @param  mixed  $notifiable
     */
    public function via($notifiable): array
    {
        return ['mail'];
    }

    /**
     * Get the mail representation of the notification.
     *
     * @param  mixed  $notifiable
     */
    public function toMail($notifiable): MailMessage
    {
        $shifts = $this->subscriptions->map(fn (Subscription $subscription): Shift => $subscription->shift);

        $mail = (new MailMessage)
            ->subject(__('subscription.verifyEmailSubject'))
            ->line($shifts->count() === 1
                ? __('subscription.verifyEmailIntro')
                : __('subscription.verifyEmailIntroMany'));

        // with a single shift the mail is about that one shift and says so in
        // its intro; a sign-up covering several has to name which
        if ($shifts->count() > 1) {
            foreach ($shifts as $shift) {
                $mail->line('- '.$shift->title.', '.$this->schedule($shift));
            }
        }

        $mail->action(__('subscription.verifyEmailAction'), $this->verifyLink)
            ->line(__('subscription.verifyEmailOutro'));

        $this->attachCalendar($mail, $shifts);
        $this->addUnsubscribeLinks($mail);

        return $mail;
    }

    /**
     * Get the array representation of the notification.
     *
     * @param  mixed  $notifiable
     */
    public function toArray($notifiable): array
    {
        return [
            //
        ];
    }

    /**
     * The shifts travel with the mail rather than as a link: a helper gets
     * them into their calendar without visiting the site again, and mail
     * clients offer the file as appointments to accept. Several shifts go
     * into a single file, which a calendar reads as one import.
     *
     * @param  Collection<int, Shift>  $shifts
     */
    private function attachCalendar(MailMessage $mail, Collection $shifts): void
    {
        if ($shifts->isEmpty()) {
            return;
        }

        $calendar = app(ShiftCalendar::class);

        $body = $calendar->buildMany(
            $shifts,
            fn (Shift $shift): string => route('plan.shift.show', [
                'plan' => $shift->plan->view_id,
                'shift' => $shift,
            ]),
            $shifts->count() > 1 ? $shifts->first()->plan->title : null,
        );

        $name = $shifts->count() === 1
            ? $calendar->filename($shifts->first())
            : $calendar->filenameFor((string) $shifts->first()->plan->title);

        $mail->attachData($body, $name, ['mime' => 'text/calendar; charset=utf-8; method=PUBLISH']);
    }

    /**
     * A MailMessage only supports a single action button, and verifying the
     * address is the primary call to action here - so the unsubscribe links
     * stay plain (markdown-autolinked) URLs. Each shift keeps its own, so a
     * combined sign-up can still be undone one shift at a time.
     */
    private function addUnsubscribeLinks(MailMessage $mail): void
    {
        if ($this->unsubscribeLinks === []) {
            return;
        }

        $mail->line($this->subscriptions->count() === 1
            ? __('subscription.unsubscribeLinkIntro')
            : __('subscription.unsubscribeLinkIntroMany'));

        foreach ($this->subscriptions as $subscription) {
            $link = $this->unsubscribeLinks[$subscription->id] ?? null;

            if ($link === null) {
                continue;
            }

            $mail->line($this->subscriptions->count() === 1
                ? '<'.$link.'>'
                : $subscription->shift->title.': <'.$link.'>');
        }
    }

    /**
     * When a shift runs, as plain text - the shared builder marks up the
     * break between two days, which a mail line has no use for.
     */
    private function schedule(Shift $shift): string
    {
        return str_replace(
            '<br>',
            ' ',
            PlanController::buildDateString((string) $shift->start, (string) $shift->end),
        );
    }
}
