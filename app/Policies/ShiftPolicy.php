<?php

namespace App\Policies;

use App\Models\Plan;
use App\Models\Shift;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Auth\Access\Response;

class ShiftPolicy
{
    use HandlesAuthorization;

    /**
     * Determine whether the user can create a shift on the given plan.
     */
    public function create(User $user, Plan $plan): bool
    {
        return $plan->isManageableBy($user);
    }

    /**
     * Determine whether the user can update the shift.
     *
     * @return Response|bool
     */
    public function update(User $user, Shift $shift)
    {
        return $shift->plan->isManageableBy($user);
    }

    /**
     * Determine whether the user can permanently delete the shift.
     *
     * @return Response|bool
     */
    public function forceDelete(User $user, Shift $shift)
    {
        return $shift->plan->isManageableBy($user);
    }
}
