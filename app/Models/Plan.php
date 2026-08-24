<?php

namespace App\Models;

use App\Support\DescriptionSanitizer;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

#[Fillable([
    'user_id',
    'title',
    'description',
    'contact_email',
    'contact_phone',
    'owner_email',
    'allow_unsubscribe',
    'published',
    'active',
    'logo',
    'show_subscriber_names',
])]
#[Hidden([
    'owner_email',
    'view_id',
])]
class Plan extends Model
{
    use HasFactory, Notifiable;

    protected function casts(): array
    {
        return [
            'published' => 'boolean',
            'active' => 'boolean',
            'show_subscriber_names' => 'boolean',
            'description' => DescriptionSanitizer::CAST,
        ];
    }

    /**
     * Export data to csv
     */
    public function export($csv): void
    {
        fputcsv($csv, ['title', $this->title]);
        fputcsv($csv, ['description', $this->description]);
        fputcsv($csv, ['contact_email', $this->contact_email]);
        fputcsv($csv, ['contact_phone', $this->contact_phone]);
        fputcsv($csv, ['owner_email', $this->owner_email]);
        fputcsv($csv, ['allow_unsubscribe', $this->allow_unsubscribe]);
        fputcsv($csv, ['show_subscriber_names', $this->show_subscriber_names]);
    }

    /**
     * {@inheritDoc}
     *
     * @return bool
     */
    public function save(array $options = [])
    {
        // add a unique public share link for newly created plans
        if (empty($this->view_id)) {
            $this->view_id = Str::random(32);
        }

        return parent::save($options);
    }

    /**
     * The user who owns and created this plan.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * OIDC groups this plan's management has been shared with.
     */
    public function sharedGroups(): HasMany
    {
        return $this->hasMany(PlanShare::class);
    }

    /**
     * Shift categories defined for this plan.
     */
    public function shiftCategories(): HasMany
    {
        return $this->hasMany(ShiftCategory::class);
    }

    /**
     * Scope a query to only plans the owner has published.
     */
    #[Scope]
    protected function published(Builder $query): Builder
    {
        return $query->where('published', true);
    }

    /**
     * Scope a query to only active plans - reachable via their direct link.
     * A deactivated plan is reachable by no one but its owner/shared groups,
     * regardless of published state.
     */
    #[Scope]
    protected function active(Builder $query): Builder
    {
        return $query->where('active', true);
    }

    /**
     * Public URL of the plan's logo, or null if it doesn't have one.
     */
    public function logoUrl(): ?string
    {
        return $this->logo ? Storage::disk('public')->url($this->logo) : null;
    }

    /**
     * The plan's logo as a base64 data URI, or null if it doesn't have one.
     * Used for the PDF export, where dompdf has remote image fetching
     * disabled and can't load the logo via its public URL.
     */
    public function logoDataUri(): ?string
    {
        if (! $this->logo) {
            return null;
        }

        $disk = Storage::disk('public');

        return 'data:'.$disk->mimeType($this->logo).';base64,'.base64_encode($disk->get($this->logo));
    }

    /**
     * Subscriber names are never public - they're only shown to logged-in
     * visitors, and only if the owner opted in via show_subscriber_names.
     */
    public function subscriberNamesVisibleTo(?User $user): bool
    {
        return $this->show_subscriber_names && $user instanceof User;
    }

    /**
     * Whether the given user may manage this plan (create/edit/delete shifts,
     * view/manage subscriptions, export/import) - either as the owner, as a
     * member of a group with management access, or as a global admin
     * (OIDC_ADMIN_GROUPS). Checked live, so revoking the share or the group
     * membership takes effect immediately.
     */
    public function isManageableBy(User $user): bool
    {
        if ($this->user_id === $user->id || $user->isGlobalAdmin()) {
            return true;
        }

        return $this->sharedGroups()
            ->where('access', PlanShare::MANAGE)
            ->whereIn('group', $user->groups ?? [])
            ->exists();
    }

    /**
     * Whether the given user may at least view this plan (and its
     * subscriptions) - either as the owner, as a member of a group with
     * either management or read-only access, or as a global admin
     * (OIDC_ADMIN_GROUPS).
     */
    public function isViewableBy(User $user): bool
    {
        if ($this->user_id === $user->id || $user->isGlobalAdmin()) {
            return true;
        }

        return $this->sharedGroups()->whereIn('group', $user->groups ?? [])->exists();
    }

    /**
     * Get the associated shifts for the plan
     *
     * @return HasMany
     */
    public function shifts()
    {
        // uncategorized shifts always come first; categories after that are
        // ordered by the start of their earliest shift, and shifts within a
        // category (or among the uncategorized ones) by their own start
        return $this->hasMany(Shift::class)
            ->orderByRaw("(case when type = '' then 0 else 1 end) asc")
            ->orderByRaw('(select min(start) from shifts s2 where s2.type = shifts.type) asc')
            ->orderBy('start');
    }

    /**
     * Start of the earliest shift, or null if this plan has no shifts yet.
     */
    public function firstShiftStart(): ?string
    {
        return $this->shifts->min('start');
    }

    /**
     * End of the latest shift, or null if this plan has no shifts yet.
     */
    public function lastShiftEnd(): ?string
    {
        return $this->shifts->max('end');
    }

    /**
     * Total number of helper slots across all of this plan's shifts.
     */
    public function totalSlotsCount(): int
    {
        return $this->shifts->sum('team_size');
    }

    /**
     * Number of helper slots already filled across all of this plan's
     * shifts. Capped per shift at its team_size, so an over-subscribed
     * shift doesn't count more slots as filled than it actually has.
     */
    public function filledSlotsCount(): int
    {
        return $this->shifts->sum(fn (Shift $shift): int => min($shift->subscriptions->count(), $shift->team_size));
    }

    /**
     * Check if any of the associated shifts has a specific type
     */
    public function anyType(): bool
    {
        foreach ($this->shifts as $shift) {
            if ($shift->type !== '') {
                return true;
            }
        }

        return false;
    }

    /**
     * Route notifications for the mail channel.
     * This is a fix for a laravel problem with the reset mail
     *
     * @return string
     */
    public function routeNotificationForMail()
    {
        return $this->owner_email;
    }
}
