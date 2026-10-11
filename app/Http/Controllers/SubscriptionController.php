<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Plan;
use App\Models\Shift;
use App\Models\Subscription;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Session;

class SubscriptionController extends Controller
{
    /**
     * Confirm a subscriber's email address for every subscription the one
     * email they got covered. The 'signed' route middleware already rejects
     * tampered or expired links, and the ids are part of what is signed -
     * but they still have to name subscriptions of this plan.
     */
    public function verifyEmails(Plan $plan)
    {
        $this->authorize('show', $plan);

        $ids = array_map(intval(...), (array) request()->query('subscriptions', []));

        $subscriptions = Subscription::whereIn('id', $ids)
            ->whereHas('shift', fn (Builder $query) => $query->where('plan_id', $plan->id))
            ->get();

        abort_if($subscriptions->isEmpty(), 404);

        $subscriptions
            ->reject(fn (Subscription $subscription): bool => $subscription->isEmailVerified())
            ->each(fn (Subscription $subscription) => $subscription->update(['email_verified_at' => now()]));

        Session::flash('info', trans_choice('subscription.emailVerified', $subscriptions->count()));

        return to_route('plan.show', ['plan' => $plan]);
    }

    /**
     * Confirm a single subscription's email address.
     *
     * Superseded by {@see verifyEmails()}, which confirms a whole sign-up at
     * once; this stays so that links sent before that change keep working
     * until they expire.
     */
    public function verifyEmail(Plan $plan, Shift $shift, Subscription $subscription)
    {
        $this->authSubscriber($plan, $shift);
        if (! $subscription->isEmailVerified()) {
            $subscription->update(['email_verified_at' => now()]);
        }
        Session::flash('info', trans_choice('subscription.emailVerified', 1));

        return to_route('plan.show', ['plan' => $plan]);
    }

    /**
     * Unsubscribe the logged-in visitor from a shift, matched by their
     * account email - subscribing stays anonymous, so email is the only
     * identity a subscription and a logged-in user share.
     */
    public function unsubscribeSelf(Plan $plan, Shift $shift)
    {
        $this->authSubscriber($plan, $shift);

        abort_unless($shift->selfUnsubscribeAllowed(), 403);

        $shift->subscriptions()->where('email', Auth::user()->email)->delete();

        Session::flash('info', __('subscription.successfullyRemoved'));

        return to_route('plan.show', ['plan' => $plan]);
    }

    /**
     * Remove the specified resource from storage.
     *
     * @return Response
     */
    public function destroy(Plan $plan, Shift $shift, Subscription $subscription)
    {
        // authorized by the 'can:forceDelete,subscription' route middleware
        $subscription->forceDelete();
        Session::flash('info', __('subscription.successfullyDestroyed'));

        return to_route('plan.shift.subscriptions', ['plan' => $plan, 'shift' => $shift]);
    }
}
