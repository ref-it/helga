<?php

namespace App\Http\Controllers;

use App\Http\Requests\ImportPlanRequest;
use App\Http\Requests\StoreShiftRequest;
use App\Http\Requests\StoreSubscriptionRequest;
use App\Http\Requests\UpdatePlanRequest;
use App\Models\Plan;
use App\Support\PlanPdfRenderer;
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
     * Import a plan from a csv file, always as a brand new plan.
     *
     * Importing into an existing plan is deliberately not offered: the file
     * has no ids, so shifts and helpers could only ever be appended to what
     * is already there, never matched up with it - which silently doubled
     * the plan. Re-importing as a new plan and deleting the old one is the
     * unambiguous way to do the same thing.
     *
     * @param  Request  $request
     */
    public function import(ImportPlanRequest $request)
    {
        if (! $request->file('import')->isValid()) {
            return abort(500, "Can't upload the file");
        }
        $plan = new Plan;
        $plan->user_id = Auth::id();
        $file = $request->file('import');
        $in = fopen($file->getRealPath(), 'r');
        // placeholders for the not-null columns, so the row can be inserted
        // now - the shifts below need a plan id to hang off, and the real
        // values only arrive with the key/value rows further down the file
        $plan->title = '';
        $plan->description = '';
        $plan->owner_email = '';
        $plan->save();
        $shift = null;
        $planData = [];
        // category name => ShiftCategory id, so a category many shifts share
        // is only created once. Starts out empty because the plan is new
        $categoryIds = [];
        // go over all lines and import the data
        while (($data = fgetcsv($in)) !== false) {
            if (preg_match('/^shift$/', $data[0])) {
                // remove the identifier field
                array_shift($data);
                // remove empty field
                array_shift($data);
                $d = [
                    'type' => $this->categoryId($plan, (string) $data[0], $categoryIds),
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
     * Turn a category name from a csv shift row into a ShiftCategory id of
     * the target plan, creating the category on first use. Shifts keep the
     * id in `type`, so the name from the file has to be resolved back into
     * one - see Shift::export() for why the file carries the name.
     *
     * A csv exported before that change carries the id instead, which then
     * becomes a category literally named e.g. "7". That is what such a plan
     * already showed as its group heading before the import, because the id
     * pointed at no category of the new plan and PlanPdfRenderer falls back
     * to printing the raw value.
     *
     * @param  array<string, int>  $known  name => id, grown as categories are created
     */
    private function categoryId(Plan $plan, string $name, array &$known): string
    {
        if ($name === '') {
            return '';
        }
        // matches the shift_categories.name column, so an overlong name is a
        // bad file rather than a failed insert
        if (mb_strlen($name) > 255) {
            return abort(400, 'Invalid csv input');
        }
        $known[$name] ??= $plan->shiftCategories()->create(['name' => $name])->id;

        return (string) $known[$name];
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
        $categoryNames = $plan->shiftCategories->pluck('name', 'id');
        $callback = function () use ($plan, $isTemplate, $categoryNames): void {
            $file = fopen('php://output', 'w');
            $plan->export($file);
            foreach ($plan->shifts()->get() as $shift) {
                fputcsv($file, $shift->export($categoryNames));
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
    public function exportPdf(Plan $plan, PlanPdfRenderer $renderer): Response
    {
        // authorized by the 'can:manage,plan' route middleware
        $raw = $renderer->render($plan, $plan->shiftCategories->pluck('name', 'id'));
        $filename = Str::slug(__('plan.shiftPlan').'-'.$plan->title).'.pdf';

        // Shown in the browser's own viewer rather than dropped into the
        // download folder: the sheet exists to be printed, and every viewer
        // has a save button, so serving it inline takes nothing away. The
        // filename still travels - it is what the viewer offers on save, and
        // what the tab is named after.
        return response($raw, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="'.$filename.'"',
        ]);
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
