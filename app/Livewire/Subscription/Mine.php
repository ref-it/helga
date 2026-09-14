<?php

namespace App\Livewire\Subscription;

use App\Livewire\Concerns\DownloadsCalendar;
use App\Models\Shift;
use App\Models\Subscription;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;
use Symfony\Component\HttpFoundation\StreamedResponse;

class Mine extends Component
{
    use DownloadsCalendar;

    /**
     * Every shift the visitor has signed up for, as one calendar file: a
     * single import instead of fetching each shift on its own.
     */
    public function calendar(): StreamedResponse
    {
        $title = __('subscription.mySubscriptions');

        return $this->calendarDownload(
            $this->subscriptions()->map(fn (Subscription $subscription): Shift => $subscription->shift),
            $title,
            $title,
        );
    }

    /**
     * A single one of them, from the menu beside it.
     *
     * The shift is looked up among the visitor's own subscriptions: the id
     * arrives from the browser, so being on this page says nothing about
     * which shift it names.
     */
    public function shiftCalendar(int $shift): StreamedResponse
    {
        $subscription = $this->subscriptions()->first(
            fn (Subscription $subscription): bool => $subscription->shift->id === $shift,
        );

        abort_if($subscription === null, 404);

        return $this->calendarDownload([$subscription->shift], $subscription->shift->title ?: 'shift');
    }

    public function render()
    {
        $byPlan = $this->subscriptions()
            ->groupBy(fn (Subscription $subscription) => $subscription->shift->plan->id);

        return view('livewire.subscription.mine', [
            'byPlan' => $byPlan,
        ])->title(__('subscription.mySubscriptions'));
    }

    /**
     * @return Collection<int, Subscription>
     */
    private function subscriptions(): Collection
    {
        return Subscription::ofSubscriber(Auth::user()?->email);
    }
}
