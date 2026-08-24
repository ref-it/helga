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
     * email address they signed up with. Anonymous subscribers have no
     * account to unsubscribe with later, so - if the plan allows it - the
     * same email also carries a link to unsubscribe from this shift.
     */
    public function sendEmailVerification(): void
    {
        $link = URL::temporarySignedRoute('plan.subscription.verifyEmail', now()->addDays(7), [
            'plan' => $this->shift->plan,
            'shift' => $this->shift,
            'subscription' => $this,
        ]);

        $unsubscribeLink = null;

        if ($this->shift->plan->allow_unsubscribe) {
            $this->confirmation = Str::random(24);
            $this->save();
            $unsubscribeLink = route('plan.subscription.confirmRemove', [
                'plan' => $this->shift->plan,
                'shift' => $this->shift,
                'confirmation' => $this->confirmation,
            ]);
        }

        $this->notify(new SendEmailVerification($link, $unsubscribeLink));
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
