<?php

namespace App\Notifications;

use App\Models\Shift;
use App\Support\ShiftCalendar;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class SendEmailVerification extends Notification
{
    use Queueable;

    /**
     * Create a new notification instance.
     */
    public function __construct(
        private string $verifyLink,
        private ?string $unsubscribeLink = null,
        private ?Shift $shift = null,
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
        $mail = (new MailMessage)
            ->subject(__('subscription.verifyEmailSubject'))
            ->line(__('subscription.verifyEmailIntro'))
            ->action(__('subscription.verifyEmailAction'), $this->verifyLink)
            ->line(__('subscription.verifyEmailOutro'));

        if ($this->shift instanceof Shift) {
            // The shift travels with the mail rather than as a link: a helper
            // gets it into their calendar without visiting the site again,
            // and mail clients offer the file as an appointment to accept.
            $calendar = app(ShiftCalendar::class);
            $mail->attachData(
                $calendar->build(
                    $this->shift,
                    route('plan.shift.show', [
                        'plan' => $this->shift->plan->view_id,
                        'shift' => $this->shift,
                    ]),
                ),
                $calendar->filename($this->shift),
                ['mime' => 'text/calendar; charset=utf-8; method=PUBLISH'],
            );
        }

        if ($this->unsubscribeLink !== null) {
            // a MailMessage only supports a single action button, and
            // verifying the address is the primary call to action here -
            // so the unsubscribe link stays a plain (markdown-autolinked) URL
            $mail->line(__('subscription.unsubscribeLinkIntro'))
                ->line('<'.$this->unsubscribeLink.'>');
        }

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
}
