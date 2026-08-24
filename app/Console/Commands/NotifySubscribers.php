<?php

namespace App\Console\Commands;

use App\Models\Shift;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Date;

#[Description('Notify subscribers 1 day before theire shift')]
#[Signature('schichtplan:notify-subscribers')]
class NotifySubscribers extends Command
{
    /**
     * Execute the console command.
     * todo: merge with the sendReminder function
     */
    public function handle(): int
    {
        if (! config('app.reminders_enabled')) {
            return Command::SUCCESS;
        }

        $done = [];
        // get all shifts we want to notify
        $toNotify = Shift::whereDate('start', '<=', date('Y-m-d', strtotime('+1 day')))
            ->where('notified', '<>', '1')->get();
        foreach ($toNotify as $shift) {
            $planId = $shift->plan->id;
            if (! isset($done[$planId])) {
                $done[$planId] = [];
            }
            if (Date::parse($shift->start) > Date::now()) {
                foreach ($shift->subscriptions as $sub) {
                    // don't email an address nobody has confirmed ownership of
                    if ($sub->notification && $sub->isEmailVerified()) {
                        if (! isset($done[$planId][$sub->email])) {
                            $sub->sendReminder();
                            $done[$planId][$sub->email] = true;
                        }
                    }
                }
            }
            $shift->notified = true;
            $shift->save();
        }

        return Command::SUCCESS;
    }
}
