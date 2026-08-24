<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Plan;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class PlanPolicy
{
    use HandlesAuthorization;

    /**
     * Determine whether the user can view the admin overview listing every
     * plan in the system, regardless of ownership or sharing - global admins
     * (OIDC_ADMIN_GROUPS) only.
     */
    public function viewAny(User $user): bool
    {
        return $user->isGlobalAdmin();
    }

    /**
     * Determine whether the user can view the given plan's management page -
     * either as its owner or as a member of a group it has been shared with,
     * with either management or read-only access.
     */
    public function view(User $user, Plan $plan): bool
    {
        return $plan->isViewableBy($user);
    }

    /**
     * Determine whether the given visitor may see the plan's public page (and
     * subscribe/unsubscribe from its shifts) - anyone, once the plan is
     * active, regardless of whether it's published (published only controls
     * whether it's listed on the home page); otherwise only the owner or a
     * shared-group member (management or read-only). Nullable user so
     * guests are evaluated too instead of being denied outright.
     */
    public function show(?User $user, Plan $plan): bool
    {
        return $plan->active || ($user && $plan->isViewableBy($user));
    }

    /**
     * Determine whether the user can create/edit/delete shifts, manage
     * subscriptions, and export/import the given plan.
     */
    public function manage(User $user, Plan $plan): bool
    {
        return $plan->isManageableBy($user);
    }

    /**
     * Determine whether the user can update the plan's own settings.
     */
    public function update(User $user, Plan $plan): bool
    {
        return $plan->isManageableBy($user);
    }

    /**
     * Determine whether the user can permanently delete the plan.
     * Owner-only - shared-group members do not get this, but global admins
     * (OIDC_ADMIN_GROUPS) do.
     */
    public function forceDelete(User $user, Plan $plan): bool
    {
        return $plan->user_id === $user->id || $user->isGlobalAdmin();
    }

    /**
     * Determine whether the user can add/remove the plan's shared groups.
     * Owner-only - shared-group members do not get this, but global admins
     * (OIDC_ADMIN_GROUPS) do.
     */
    public function share(User $user, Plan $plan): bool
    {
        return $plan->user_id === $user->id || $user->isGlobalAdmin();
    }
}
