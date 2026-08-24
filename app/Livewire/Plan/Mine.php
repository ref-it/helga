<?php

namespace App\Livewire\Plan;

use App\Models\Plan;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;
use Livewire\WithPagination;

class Mine extends Component
{
    use WithPagination;

    public string $search = '';

    public function updatingSearch(): void
    {
        $this->resetPage('ownedPage');
        $this->resetPage('sharedPage');
    }

    public function render()
    {
        $user = Auth::user();

        $owned = $user->plans()
            ->with('shifts.subscriptions')
            ->when($this->search !== '', fn ($query) => $query->where('title', 'like', '%'.$this->search.'%'))
            ->orderBy('id', 'desc')
            ->paginate(15, ['*'], 'ownedPage');

        $shared = Plan::whereHas('sharedGroups', function ($query) use ($user): void {
            $query->whereIn('group', $user->groups ?? []);
        })
            ->where('user_id', '!=', $user->id)
            ->with('shifts.subscriptions')
            ->when($this->search !== '', fn ($query) => $query->where('title', 'like', '%'.$this->search.'%'))
            ->orderBy('id', 'desc')
            ->paginate(15, ['*'], 'sharedPage');

        return view('livewire.plan.mine', [
            'owned' => $owned,
            'shared' => $shared,
        ])->title(__('plan.myPlans'));
    }
}
