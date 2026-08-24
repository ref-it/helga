<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'plan_id',
    'group',
    'access',
])]
class PlanShare extends Model
{
    /**
     * Full management rights: create/edit/delete shifts, manage
     * subscriptions, export/import.
     */
    public const MANAGE = 'manage';

    /**
     * Read-only access: can view the plan and its subscriptions, but not
     * change anything.
     */
    public const READ = 'read';

    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class);
    }
}
