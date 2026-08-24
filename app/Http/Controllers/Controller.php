<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Plan;
use App\Models\Shift;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Foundation\Bus\DispatchesJobs;
use Illuminate\Foundation\Validation\ValidatesRequests;
use Illuminate\Routing\Controller as BaseController;

class Controller extends BaseController
{
    use AuthorizesRequests, DispatchesJobs, ValidatesRequests;

    /**
     * Anonymous user can just subscribe to a known plan and shift, but only
     * once the plan is published (or they're its owner/a shared-group member)
     */
    protected function authSubscriber(Plan $plan, Shift $shift)
    {
        // anonymous user can subscribe to the plan they have the link to
        // we can't use auth Policies for this integrity check
        if ($shift->plan->id !== $plan->id) {
            abort(403);
        }
        $this->authorize('show', $plan);
    }
}
