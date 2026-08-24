<?php

declare(strict_types=1);

namespace App\Livewire\Shift;

use App\Models\Plan;
use App\Models\Shift;
use Livewire\Attributes\Locked;
use Livewire\Component;

class Subscriptions extends Component
{
    #[Locked]
    public Plan $plan;

    #[Locked]
    public Shift $shift;

    public function mount(Plan $plan, Shift $shift): void
    {
        // authorized by the 'can:view,plan' route middleware
        if ($shift->plan->id !== $plan->id) {
            abort(403);
        }

        $this->plan = $plan;
        $this->shift = $shift;
    }

    public function render()
    {
        return view('livewire.shift.subscriptions', [
            'plan' => $this->plan,
            'shift' => $this->shift,
        ])->title($this->shift->title);
    }
}
