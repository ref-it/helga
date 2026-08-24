<?php

namespace App\Livewire\Plan;

use App\Models\Plan;
use Flux\Flux;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Validate;
use Livewire\Component;

class Create extends Component
{
    #[Validate('required|string')]
    public string $title = '';

    #[Validate('required|string|max:10000')]
    public string $description = '';

    #[Validate('nullable|email|max:200')]
    public string $contact_email = '';

    #[Validate('nullable|regex:/[0-9\s]{10,15}/')]
    public string $contact_phone = '';

    public $allow_unsubscribe = false;

    public $show_subscriber_names = false;

    protected function messages(): array
    {
        return [
            'contact_email.email' => __('plan.contactEmailInvalid'),
            'contact_phone.regex' => __('plan.contactPhoneRegex'),
        ];
    }

    public function render()
    {
        return view('livewire.plan.create')->title(__('plan.heading'));
    }

    public function save()
    {
        // route requires auth:oidc - only logged-in users reach this
        $this->validate();

        $plan = Plan::create([
            'user_id' => Auth::id(),
            'title' => $this->title,
            'description' => $this->description,
            'owner_email' => Auth::user()->email,
            'contact_email' => $this->contact_email,
            'contact_phone' => $this->contact_phone,
            'allow_unsubscribe' => $this->allow_unsubscribe,
            'show_subscriber_names' => $this->show_subscriber_names,
        ]);

        // redirect with success message
        Flux::toast(variant: 'success', text: __('plan.successfullyCreated'));

        return to_route('plan.manage', $plan);
    }
}
