<?php

namespace App\Livewire\Subscription;

use App\Models\Subscription;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

class Mine extends Component
{
    public function render()
    {
        $user = Auth::user();

        // subscribing stays anonymous, there's no account link to a
        // subscription - matching on email is the only identity we have
        $subscriptions = $user->email
            ? Subscription::where('email', $user->email)
                ->with('shift.plan')
                ->get()
                ->sortBy('shift.start')
            : collect();

        $byPlan = $subscriptions->groupBy(fn ($subscription) => $subscription->shift->plan->id);

        return view('livewire.subscription.mine', [
            'byPlan' => $byPlan,
        ])->title(__('subscription.mySubscriptions'));
    }
}
