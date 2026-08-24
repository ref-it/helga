<?php

namespace App\Console\Commands;

use App\Models\Subscription;
use App\Models\User;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Description('Remove all users who neither administer a plan nor are subscribed to a shift.')]
#[Signature('schichtplan:cleanup-users')]
class ClearUsers extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        User::whereDoesntHave('plans')
            ->whereNotIn('email', Subscription::select('email'))
            ->delete();

        return Command::SUCCESS;
    }
}
