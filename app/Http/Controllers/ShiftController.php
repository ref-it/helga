<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreShiftRequest;
use App\Models\Plan;
use App\Models\Shift;
use App\Support\ShiftCalendar;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Session;

class ShiftController extends Controller
{
    /**
     * The shift as a calendar entry, so a helper can put it into their own
     * calendar.
     *
     * Reachable by anyone holding the plan's view link, like the shift page
     * itself - a helper is not necessarily a user of this application, and
     * the entry carries nothing the shift page does not already show.
     */
    public function calendar(Plan $plan, Shift $shift, ShiftCalendar $calendar): Response
    {
        $this->authSubscriber($plan, $shift);

        $url = route('plan.shift.show', ['plan' => $plan->view_id, 'shift' => $shift]);

        return response($calendar->build($shift, $url), 200, [
            'Content-Type' => 'text/calendar; charset=utf-8',
            'Content-Disposition' => 'attachment; filename="'.$calendar->filename($shift).'"',
        ]);
    }

    /**
     * Store a newly created shift in storage.
     *
     * @return Response
     */
    public function store(StoreShiftRequest $request, Plan $plan)
    {
        // authorized by the 'can:create,App\Models\Shift,plan' route middleware
        $data = $request->validated();
        $plan->shifts()->create($data);
        Session::flash('info', __('shift.successfullyCreated'));

        return to_route('plan.manage', $plan);
    }

    /**
     * Remove the specified shift from storage.
     *
     * @return Response
     */
    public function destroy(Plan $plan, Shift $shift)
    {
        // authorized by the 'can:forceDelete,shift' route middleware
        $shift->forceDelete();
        Session::flash('info', __('shift.successfullyDestroyed'));

        return to_route('plan.manage', $plan);
    }
}
