<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreShiftRequest;
use App\Models\Plan;
use App\Models\Shift;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Session;

class ShiftController extends Controller
{
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
