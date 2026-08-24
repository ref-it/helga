<?php

declare(strict_types=1);

namespace App\Livewire\Subscription;

use App\Models\Plan;
use App\Models\Shift;
use Flux\Flux;
use Livewire\Attributes\Locked;
use Livewire\Component;

class ConfirmRemove extends Component
{
    #[Locked]
    public Plan $plan;

    #[Locked]
    public Shift $shift;

    #[Locked]
    public string $confirmation;

    public function mount(Plan $plan, Shift $shift, string $confirmation): void
    {
        // anonymous visitors reach this via the link emailed to them, but
        // only once the plan is published - we can't use auth Policies for
        // the shift/plan integrity check below
        if ($shift->plan->id !== $plan->id) {
            abort(403);
        }
        $this->authorize('show', $plan);

        $this->plan = $plan;
        $this->shift = $shift;
        $this->confirmation = $confirmation;
    }

    public function render()
    {
        return view('livewire.subscription.confirm-remove')->title($this->shift->title);
    }

    public function confirm()
    {
        if ($this->shift->selfUnsubscribeAllowed()) {
            foreach ($this->shift->subscriptions as $subscription) {
                if ($subscription->confirmation === $this->confirmation) {
                    $subscription->delete();
                    Flux::toast(variant: 'success', text: __('subscription.successfullyRemoved'));
                }
            }
        }

        return to_route('plan.show', ['plan' => $this->plan]);
    }
}
