<?php

namespace App\Livewire\Subscription;

use App\Models\Plan;
use App\Models\Shift;
use App\Models\Subscription;
use Flux\Flux;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Validate;
use Livewire\Component;

class Edit extends Component
{
    #[Locked]
    public Plan $plan;

    #[Locked]
    public Shift $shift;

    #[Locked]
    public Subscription $subscription;

    #[Validate('required|max:100')]
    public string $name = '';

    #[Validate('nullable|regex:/[0-9\s]{10,15}/')]
    public string $phone = '';

    #[Validate('nullable|email|max:100')]
    public string $email = '';

    public bool $notification = false;

    #[Validate('nullable|max:500')]
    public string $comment = '';

    protected function messages(): array
    {
        return [
            'name.required' => __('subscription.nameRequired'),
            'phone.regex' => __('subscription.phoneRegex'),
            'comment.max' => __('subscription.commentMax'),
        ];
    }

    public function mount(Plan $plan, Shift $shift, Subscription $subscription): void
    {
        // authorized by the 'can:update,subscription' route middleware
        $this->plan = $plan;
        $this->shift = $shift;
        $this->subscription = $subscription;

        $this->name = (string) $subscription->name;
        $this->phone = (string) $subscription->phone;
        $this->email = (string) $subscription->email;
        $this->notification = (bool) $subscription->notification;
        $this->comment = (string) $subscription->comment;
    }

    public function render()
    {
        return view('livewire.subscription.edit')->title($this->shift->title);
    }

    public function save()
    {
        $this->authorize('update', $this->subscription);
        $this->validate();

        $this->subscription->update([
            'name' => $this->name,
            'phone' => $this->phone,
            'email' => $this->email,
            // the checkbox is hidden in the UI if the feature is off - never
            // trust a client-supplied true in that case
            'notification' => config('app.reminders_enabled') && $this->notification,
            'comment' => $this->comment,
        ]);

        Flux::toast(variant: 'success', text: __('subscription.successfullyUpdated'));

        return to_route('plan.shift.subscriptions', ['plan' => $this->plan, 'shift' => $this->shift]);
    }
}
