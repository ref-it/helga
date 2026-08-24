<?php

namespace App\Policies;

use App\Models\Subscription;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Auth\Access\Response;

class SubscriptionPolicy
{
    use HandlesAuthorization;

    /**
     * Determine whether the user can update the subscription.
     *
     * @return Response|bool
     */
    public function update(User $user, Subscription $subscription)
    {
        return $subscription->shift->plan->isManageableBy($user);
    }

    /**
     * Determine whether the user can permanently delete the subscription.
     *
     * @return Response|bool
     */
    public function forceDelete(User $user, Subscription $subscription)
    {
        return $subscription->shift->plan->isManageableBy($user);
    }
}
