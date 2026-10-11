<?php

namespace App\Livewire\Plan;

use App\Models\Plan;
use App\Models\Shift;
use Flux\Flux;
use Illuminate\Contracts\View\Factory;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Locked;
use Livewire\Component;

class Show extends Component
{
    #[Locked]
    public Plan $plan;

    /**
     * Ids of the shifts ticked for a combined sign-up. Checkbox values
     * arrive as strings, so these are cast before they are used.
     *
     * @var array<int, string>
     */
    public array $selectedShifts = [];

    public function mount(Plan $plan): void
    {
        $this->authorize('show', $plan);

        $this->plan = $plan;
    }

    public function clearSelection(): void
    {
        $this->selectedShifts = [];
    }

    /**
     * Hand the ticked shifts over to the sign-up form, which creates one
     * subscription per shift from a single set of contact details.
     */
    public function subscribeToSelected(): mixed
    {
        $shiftIds = $this->openShiftIds()
            ->intersect(array_map('intval', $this->selectedShifts))
            ->values();

        if ($shiftIds->isEmpty()) {
            // every ticked shift filled up (or was joined elsewhere) while
            // this page was open - the re-render drops the stale checkboxes
            $this->selectedShifts = [];
            Flux::toast(variant: 'danger', text: __('subscription.enoughSubscription'));

            return null;
        }

        return to_route('plan.subscription.createMany', [
            'plan' => $this->plan->view_id,
            'shifts' => $shiftIds->all(),
        ]);
    }

    public function render(): Factory|View
    {
        $plan = $this->plan->load('shifts.subscriptions');

        return view('livewire.plan.show', [
            'plan' => $plan,
            'categoryNames' => $plan->shiftCategories->pluck('name', 'id'),
        ])->title($plan->title);
    }

    /**
     * The shifts this visitor could still sign up for - the checkboxes are
     * only rendered for these, so anything else in {@see $selectedShifts}
     * is stale or forged and gets dropped.
     *
     * @return Collection<int, int>
     */
    private function openShiftIds(): Collection
    {
        return $this->plan->load('shifts.subscriptions')->shifts
            ->filter(fn (Shift $shift): bool => $shift->isOpenFor(Auth::user()))
            ->pluck('id');
    }
}
