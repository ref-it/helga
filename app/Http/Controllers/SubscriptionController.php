<?php

namespace App\Http\Controllers;

use App\Models\Plan;
use App\Models\Shift;
use App\Models\Subscription;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Session;

class SubscriptionController extends Controller
{
    /**
     * Confirm a subscriber's email address via the signed link sent to them.
     * The 'signed' route middleware already rejects tampered or expired links.
     */
    public function verifyEmail(Plan $plan, Shift $shift, Subscription $subscription)
    {
        $this->authSubscriber($plan, $shift);
        if (! $subscription->isEmailVerified()) {
            $subscription->update(['email_verified_at' => now()]);
        }
        Session::flash('info', __('subscription.emailVerified'));

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
