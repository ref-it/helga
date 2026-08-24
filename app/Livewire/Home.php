<?php

namespace App\Livewire;

use App\Models\Plan;
use Livewire\Component;

class Home extends Component
{
    public string $search = '';

    public function render()
    {
        $plans = Plan::published()
            ->active()
            ->with('shifts.subscriptions')
            ->withMin('shifts', 'start')
            ->when($this->search !== '', fn ($query) => $query->where('title', 'like', '%'.$this->search.'%'))
            ->orderByDesc('shifts_min_start')
            ->get();

        return view('livewire.home', [
            'plans' => $plans,
        ])->title(__('home.Shiftplan'));
    }
}
