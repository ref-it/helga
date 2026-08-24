<?php

namespace App\Livewire\Plan;

use App\Models\Plan;
use Illuminate\Contracts\View\Factory;
use Illuminate\Contracts\View\View;
use Livewire\Component;

class Show extends Component
{
    public function render(): Factory|View
    {
        $plan = Plan::where('view_id', request()->route('plan'))->with('shifts.subscriptions')->firstOrFail();

        $this->authorize('show', $plan);

        return view('livewire.plan.show', [
            'plan' => $plan,
            'categoryNames' => $plan->shiftCategories->pluck('name', 'id'),
        ])->title($plan->title);
    }
}
