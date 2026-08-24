<?php

declare(strict_types=1);

namespace App\Livewire\Plan;

use App\Models\Group;
use App\Models\Plan;
use App\Models\PlanShare;
use Illuminate\Contracts\View\Factory;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use Livewire\Attributes\Locked;
use Livewire\Component;

class Admin extends Component
{
    #[Locked]
    public Plan $plan;

    /**
     * The groups currently selected in the management-rights pillbox.
     *
     * @var array<int, string>
     */
    public array $manageGroupsInput = [];

    /**
     * The groups currently selected in the read-only-rights pillbox.
     *
     * @var array<int, string>
     */
    public array $readGroupsInput = [];

    public function mount(Plan $plan): void
    {
        // authorized by the 'can:view,plan' route middleware
        $this->plan = $plan->loadMissing('shifts.subscriptions');
        $this->manageGroupsInput = $plan->sharedGroups()->where('access', PlanShare::MANAGE)->pluck('group')->all();
        $this->readGroupsInput = $plan->sharedGroups()->where('access', PlanShare::READ)->pluck('group')->all();
    }

    public function render(): Factory|View
    {
        return view('livewire.plan.admin', [
            'plan' => $this->plan,
            'categoryNames' => $this->plan->shiftCategories->pluck('name', 'id'),
            'availableGroups' => $this->availableGroups(),
        ])->title($this->plan->title);
    }

    public function updatedManageGroupsInput(): void
    {
        // a group can only have one access level per plan - management wins
        $this->readGroupsInput = array_values(array_diff($this->readGroupsInput, $this->manageGroupsInput));

        $this->syncGroupShares();
    }

    public function updatedReadGroupsInput(): void
    {
        $this->manageGroupsInput = array_values(array_diff($this->manageGroupsInput, $this->readGroupsInput));

        $this->syncGroupShares();
    }

    /**
     * Every group known to the app - accumulated from every user's login,
     * not just the owner's own memberships - plus any group this plan is
     * already shared with (so a stale share stays visible either way).
     */
    private function availableGroups(): Collection
    {
        return Group::orderBy('name')->pluck('name')
            ->merge($this->plan->sharedGroups->pluck('group'))
            ->unique()
            ->values();
    }

    private function syncGroupShares(): void
    {
        $this->authorize('share', $this->plan);

        // never trust client-supplied values - the pillboxes only offer
        // known groups, but a tampered request could send anything
        $known = $this->availableGroups();
        $this->manageGroupsInput = $known->intersect($this->manageGroupsInput)->values()->all();
        $this->readGroupsInput = $known->intersect($this->readGroupsInput)->values()->all();

        foreach ($this->manageGroupsInput as $group) {
            $this->plan->sharedGroups()->updateOrCreate(['group' => $group], ['access' => PlanShare::MANAGE]);
        }

        foreach ($this->readGroupsInput as $group) {
            $this->plan->sharedGroups()->updateOrCreate(['group' => $group], ['access' => PlanShare::READ]);
        }

        $keep = array_merge($this->manageGroupsInput, $this->readGroupsInput);
        $this->plan->sharedGroups()->whereNotIn('group', $keep)->delete();
    }

    public function togglePublished(): void
    {
        $this->authorize('update', $this->plan);
        $this->plan->update(['published' => ! $this->plan->published]);
    }

    public function toggleActive(): void
    {
        $this->authorize('update', $this->plan);
        $this->plan->update(['active' => ! $this->plan->active]);
    }
}
