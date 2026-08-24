<?php

namespace App\Http\Controllers;

use App\Http\Requests\ImportPlanRequest;
use App\Http\Requests\StoreShiftRequest;
use App\Http\Requests\StoreSubscriptionRequest;
use App\Http\Requests\UpdatePlanRequest;
use App\Models\Plan;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class PlanController extends Controller
{
    /**
     * Import a plan from a csv file.
     *
     * @param  Request  $request
     */
    public function import(ImportPlanRequest $request, ?Plan $plan = null)
    {
        if (! $request->file('import')->isValid()) {
            return abort(500, "Can't upload the file");
        }
        if ($plan) {
            $this->authorize('manage', $plan);
        } else {
            $plan = new Plan;
            $plan->user_id = Auth::id();
        }
        $file = $request->file('import');
        $in = fopen($file->getRealPath(), 'r');
        // reset an existing plan if you update
        $plan->title = '';
        $plan->description = '';
        $plan->owner_email = '';
        $plan->save();
        $shift = null;
        $planData = [];
        // go over all lines and import the data
        while (($data = fgetcsv($in)) !== false) {
            if (preg_match('/^shift$/', $data[0])) {
                // remove the identifier field
                array_shift($data);
                // remove empty field
                array_shift($data);
                $type = $data[0];
                $d = [
                    'type' => empty($type) ? '' : $type,
                    'title' => $data[1],
                    'description' => $data[2],
                    'start' => $data[3],
                    'end' => $data[4],
                    'team_size' => $data[5],
                    // Shift::export() writes a false boolean as an empty
                    // string via fputcsv, not as a literal "0" - `??` alone
                    // doesn't catch that, and inserting '' into the boolean
                    // column blows up, so empty string needs to count as false too
                    'requires_health_certificate' => ! empty($data[6]),
                    'requires_clothing_size' => ! empty($data[7]),
                    'group' => 0,
                ];
                $validator = Validator::make($d, (new StoreShiftRequest)->rules(), (new StoreShiftRequest)->messages());
                $validData = $validator->validated();
                $shift = $plan->shifts()->create($validData);
            } elseif (preg_match('/^subscribed$/', $data[0])) {
                // the csv is malformated. We first eed a shift, before we can have a subscriber
                if ($shift === null) {
                    return abort(400, 'Invalid csv input');
                }
                // we use empty fields to separte. Find the start of the data
                $key = 8;
                $d = [
                    'name' => $data[$key],
                    'email' => $data[$key + 1],
                    'phone' => $data[$key + 2],
                    'comment' => $data[$key + 3],
                    // Subscription::export() writes a false boolean as an
                    // empty string via fputcsv, not as a literal "0" - `??`
                    // alone doesn't catch that, so it needs to count as false too
                    'notification' => ! empty($data[$key + 4]),
                    'locale' => $data[$key + 5],
                    'health_certificate_confirmed' => ! empty($data[$key + 6]),
                    // unlike the booleans above, an empty string here is a
                    // genuinely valid "no size given" - only null/undefined
                    // (a shift exported before this field existed) falls
                    // back to null rather than becoming the string ""
                    'clothing_size' => ($data[$key + 7] ?? '') !== '' ? $data[$key + 7] : null,
                ];
                $validator = Validator::make($d, (new StoreSubscriptionRequest)->rules(), (new StoreSubscriptionRequest)->messages());
                $validData = $validator->validated();
                $shift->subscriptions()->create($validData);
            } else {
                // guess the fields from the input!
                $key = $data[0];
                $value = $data[1];
                // Plan::export() writes a false boolean as an empty string
                // via fputcsv for these two fields, not as a literal "0" -
                // it needs to count as false too, or the DB update blows up
                if (in_array($key, ['allow_unsubscribe', 'show_subscriber_names'], true) && $value === '') {
                    $value = false;
                }
                $planData[$key] = $value;
            }
        }
        // Fill the plan and save it later
        if (count($planData) > 0) {
            $validator = Validator::make($planData, (new UpdatePlanRequest)->rules(), (new UpdatePlanRequest)->messages());
            $validData = $validator->validated();
            $plan->fill($validData);
        }

        // delete the file
        File::delete($file->getRealPath());
        $plan->save();

        return to_route('plan.manage', $plan);
    }

    /**
     * Exprt a plan
     *
     * The format of the csv s for humans, and not primary for machines
     * We try to visually separate thigs, so people can use some excell-fu
     * to update a plan.
     *
     * With ?template=1, subscriptions are left out entirely, so the result
     * can be re-imported as a fresh, helper-free copy of the plan.
     */
    public function export(Request $request, Plan $plan)
    {
        // authorized by the 'can:manage,plan' route middleware
        $isTemplate = $request->boolean('template');
        $fileName = ($isTemplate ? 'shift-plan-template-' : 'shift-plan-').$plan->title.'.csv';
        $headers = [
            'Content-type' => 'text/csv',
            'Content-Disposition' => "attachment; filename=$fileName",
            'Pragma' => 'no-cache',
            'Cache-Control' => 'must-revalidate, post-check=0, pre-check=0',
            'Expires' => '0',
        ];

        // export a plan in the csv format
        $callback = function () use ($plan, $isTemplate): void {
            $file = fopen('php://output', 'w');
            $plan->export($file);
            foreach ($plan->shifts()->get() as $shift) {
                fputcsv($file, $shift->export());
                if (! $isTemplate) {
                    foreach ($shift->subscriptions()->get() as $sub) {
                        fputcsv($file, $sub->export());
                    }
                }
            }
            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }

    /**
     * Export a plan with its shifts and subscribed helpers as a printable PDF.
     * Shifts with open slots keep enough room per row to fill them in by hand.
     */
    public function exportPdf(Plan $plan): Response
    {
        // authorized by the 'can:manage,plan' route middleware
        // needed for the "x / y" page number footer - the template's
        // <script type="text/php"> block that draws it is otherwise a no-op
        $pdf = Pdf::setOptions(['isPhpEnabled' => true])->loadView('pdf.plan', [
            'plan' => $plan,
            'categoryNames' => $plan->shiftCategories->pluck('name', 'id'),
        ]);

        return $pdf->download(Str::slug(__('plan.shiftPlan').'-'.$plan->title).'.pdf');
    }

    /**
     * Cleanup old plans and notify.
     */
    public function cron(Request $request)
    {
        $cronKey = $request->get('key', '');
        $confKey = env('API_KEY', false);
        if (isset($confKey) && $cronKey === $confKey) {
            Artisan::call('schichtplan:cleanup');
            Artisan::call('schichtplan:notify-subscribers');
        } else {
            return abort(403);
        }
    }

    /**
     * Remove the plan from storage.
     *
     * @return Response
     */
    public function destroy(Plan $plan)
    {
        $this->authorize('forceDelete', $plan);
        $plan->forceDelete();
        Session::flash('info', __('plan.successfullyDestroyed'));

        return to_route('home');
    }

    // Mo. 10.1 10:00 - 12:00
    // Mo. 10.1 10:00 - Di.11.1 12:00
    // Mo
    public static function buildDateString(string $start, string $end): string
    {
        $start = Date::parse($start);
        $end = Date::parse($end);
        $start->diffInHours($end);
        $res = '';
        if ($start->isSameDay($end)) {
            $res .= $start->translatedFormat('D, d.m.Y, H:i');
            $res .= ' – ';
            $res .= $end->translatedFormat('H:i');
        } else {
            $res .= $start->translatedFormat('D, d.m.Y, H:i');
            $res .= ' –<br>';
            $res .= $end->translatedFormat('D, d.m.Y, H:i');
        }

        return $res;
    }
}
