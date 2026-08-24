<?php

namespace App\Livewire\Shift;

use App\Models\Plan;
use App\Models\Shift;
use Livewire\Attributes\Locked;
use Livewire\Component;

class Show extends Component
{
    #[Locked]
    public Plan $plan;

    #[Locked]
    public Shift $shift;

    public function mount(Plan $plan, Shift $shift): void
    {
        // anonymous visitors can reach this via the link on the plan's
        // public page, but only once the plan is published - we can't use
        // auth Policies for the shift/plan integrity check below
        if ($shift->plan->id !== $plan->id) {
            abort(403);
        }
        $this->authorize('show', $plan);

        $this->plan = $plan;
        $this->shift = $shift;
    }

    public function render()
    {
        return view('livewire.shift.show')->title($this->shift->title);
    }
}
