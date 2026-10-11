<?php

namespace App\Models;

use App\Notifications\SendEmailVerification;
use App\Notifications\SendShiftReminder;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;

#[Fillable([
    'name',
    'phone',
    'email',
    'comment',
    'notification',
    'locale',
    'email_verified_at',
    'health_certificate_confirmed',
    'clothing_size',
])]
#[Hidden([
    'phone',
    'email',
    'comment',
    'confirmation',
    'locale',
])]
class Subscription extends Model
{
    use HasFactory, Notifiable;

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'health_certificate_confirmed' => 'boolean',
        ];
    }

    /**
     * Whether the subscriber's email address has been confirmed - either by
     * clicking the verification link, or automatically because they signed
     * up while logged in with that exact email address.
     */
    public function isEmailVerified(): bool
    {
        return $this->email_verified_at !== null;
    }

    /**
     * Send a signed link the subscriber can click to confirm they own the
     * email address they signed up with.
     */
    public function sendEmailVerification(): void
    {
        static::sendEmailVerificationFor(collect([$this]));
    }

    /**
     * Send the single email a whole sign-up produces, whether it covered one
     * shift or several: one signed link confirming the address for every
     * subscription it created at once. Anonymous subscribers have no account
     * to unsubscribe with later, so - if the plan allows it - the same email
     * also carries a link to unsubscribe from each of the shifts.
     *
     * @param  Collection<int, Subscription>  $subscriptions  all for the same
     *                                                        address, created by the same sign-up
     */
    public static function sendEmailVerificationFor(Collection $subscriptions): void
    {
        $subscriptions = $subscriptions->values();

        if ($subscriptions->isEmpty()) {
            return;
        }

        $plan = $subscriptions->first()->shift->plan;

        // the ids travel in the query string, which a signed URL covers just
        // as it does the path - tampering with the list breaks the signature
        $link = URL::temporarySignedRoute('plan.subscription.verifyEmails', now()->addDays(7), [
            'plan' => $plan,
            'subscriptions' => $subscriptions->pluck('id')->all(),
        ]);

        $unsubscribeLinks = [];

        if ($plan->allow_unsubscribe) {
            foreach ($subscriptions as $subscription) {
                $subscription->confirmation = Str::random(24);
                $subscription->save();
                $unsubscribeLinks[$subscription->id] = route('plan.subscription.confirmRemove', [
                    'plan' => $plan,
                    'shift' => $subscription->shift,
                    'confirmation' => $subscription->confirmation,
                ]);
            }
        }

        // every one of them carries the same address, so whichever is handed
        // the notification reaches the same inbox
        $subscriptions->first()->notify(new SendEmailVerification($link, $subscriptions, $unsubscribeLinks));
    }

    /**
     * Export a subscription
     */
    public function export(): array
    {
        return ['subscribed', '', '', '', '', '', '', '', $this->name, $this->email,
            $this->phone, $this->comment, $this->notification, $this->locale, $this->health_certificate_confirmed,
            $this->clothing_size];
    }

    /**
     * The subscriptions of whoever holds this address, with shift and plan
     * loaded, in the order the shifts start.
     *
     * Subscribing stays anonymous - a subscription carries no link to an
     * account, so a matching email address is the only identity a logged-in
     * visitor and their own entries share.
     *
     * @return Collection<int, Subscription>
     */
    public static function ofSubscriber(?string $email): Collection
    {
        if ($email === null || $email === '') {
            return collect();
        }

        return static::where('email', $email)
            ->with('shift.plan')
            ->get()
            ->sortBy('shift.start');
    }

    /**
     * Subscription belongs to a shift
     *
     * @return BelongsTo
     */
    public function shift()
    {
        return $this->belongsTo(Shift::class);
    }

    /**
     * Send a reminder email
     * todo: merge with command logic
     */
    public function sendReminder(): void
    {
        $plan = $this->shift->plan;
        $viewLink = route('plan.show', ['plan' => $plan->view_id]);
        $summary = [];
        // send only one email for all shifts an email subscribed
        foreach ($plan->shifts as $shift) {
            foreach ($shift->subscriptions as $sub) {
                if ($sub->email == $this->email) {
                    $summary[] = $shift->title.': '.
                        explode(' ', $shift->start)[1].' – '.
                        explode(' ', $shift->end)[1];
                    break;
                }
            }
        }
        $this->notify(new SendShiftReminder($viewLink, implode(', ', $summary), $this->locale));
    }
}
