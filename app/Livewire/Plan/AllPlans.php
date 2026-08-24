<?php

declare(strict_types=1);

namespace App\Livewire\Plan;

use App\Models\Plan;
use Illuminate\Contracts\View\Factory;
use Illuminate\Contracts\View\View;
use Livewire\Component;
use Livewire\WithPagination;

class AllPlans extends Component
{
    use WithPagination;

    public string $search = '';

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function render(): Factory|View
    {
        // authorized by the 'can:viewAny,App\Models\Plan' route middleware
        $plans = Plan::with('shifts.subscriptions')
            ->when($this->search !== '', fn ($query) => $query->where('title', 'like', '%'.$this->search.'%'))
            ->orderBy('id', 'desc')
            ->paginate(15);

        return view('livewire.plan.all_plans', [
            'plans' => $plans,
        ])->title(__('plan.adminPlans'));
    }
}
