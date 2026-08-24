<?php

namespace App\Models;

use App\Support\DescriptionSanitizer;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'type',
    'title',
    'group',
    'description',
    'start',
    'end',
    'team_size',
    'requires_health_certificate',
    'requires_clothing_size',
    'unsubscribe_lock_hours',
])]
class Shift extends Model
{
    use HasFactory;

    /**
     * Mirrors the DB column default so that shifts created without an
     * explicit value (CSV import, tests) already have a usable in-memory
     * value - Eloquent doesn't re-read column defaults after an insert.
     */
    protected $attributes = [
        'unsubscribe_lock_hours' => 24,
    ];

    protected function casts(): array
    {
        return [
            'requires_health_certificate' => 'boolean',
            'requires_clothing_size' => 'boolean',
            'description' => DescriptionSanitizer::CAST,
        ];
    }

    /**
     * Export shifts
     */
    public function export(): array
    {
        return [
            'shift', '', $this->type, $this->title, $this->description,
            $this->start, $this->end, $this->team_size, $this->requires_health_certificate,
            $this->requires_clothing_size,
        ];
    }

    /**
     * {@inheritDoc}
     *
     * @return bool
     */
    public function save(array $options = [])
    {
        // Empty types
        if (empty($this->type)) {
            $this->type = '';
        }

        return parent::save($options);
    }

    /**
     * Shift belongs to a plan
     *
     * @return BelongsTo
     */
    public function plan()
    {
        return $this->belongsTo(Plan::class);
    }

    /**
     * Shift can have many subscriptions
     *
     * @return HasMany
     */
    public function subscriptions()
    {
        return $this->hasMany(Subscription::class);
    }

    /**
     * Whether the given user already has a subscription for this shift.
     * Subscribing stays anonymous, so this matches on the user's email -
     * the only identity a subscription and a logged-in user share.
     */
    public function isSubscribedBy(?User $user): bool
    {
        return $user instanceof User && $this->subscriptions->contains('email', $user->email);
    }

    /**
     * Whether a subscriber may currently remove themselves from this shift -
     * the plan must allow self-unsubscription, and this shift's own
     * unsubscribe lock window (hours before start) must not have begun yet.
     */
    public function selfUnsubscribeAllowed(): bool
    {
        return $this->plan->allow_unsubscribe
            && strtotime($this->start) > strtotime("+{$this->unsubscribe_lock_hours} hours");
    }
}
