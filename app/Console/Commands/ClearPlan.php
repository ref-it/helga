<?php

namespace App\Console\Commands;

use App\Models\Plan;
use App\Models\Shift;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Description('Remove all passed plans without activity in the configured number of days (PLAN_CLEANUP_DAYS).')]
#[Signature('schichtplan:cleanup')]
class ClearPlan extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $timeGiotine = new \DateTime(config('app.plan_cleanup_days').' days ago');

        // Remove shifts which are older than the configured cleanup period
        Shift::where('start', '<', $timeGiotine)
            ->orWhere('end', '<', $timeGiotine)
            ->delete();

        // Remove all plans older than the configured cleanup period without shifts
        Plan::where('updated_at', '<', $timeGiotine)
            ->doesnthave('shifts')
            ->delete();

        return Command::SUCCESS;
    }
}
