<?php

declare(strict_types=1);

namespace App\Livewire\Plan;

use App\Models\Plan;
use Flux\Flux;
use Illuminate\Contracts\View\Factory;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Storage;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Validate;
use Livewire\Component;
use Livewire\WithFileUploads;

class Edit extends Component
{
    use WithFileUploads;

    #[Locked]
    public Plan $plan;

    #[Validate('required|string|max:200')]
    public string $title = '';

    #[Validate('required|string|max:10000')]
    public string $description = '';

    #[Locked]
    #[Validate('required|email')]
    public string $owner_email = '';

    #[Validate('nullable|email|max:200')]
    public string $contact_email = '';

    #[Validate('nullable|regex:/[0-9\s]{10,15}/')]
    public string $contact_phone = '';

    public bool $allow_unsubscribe = false;

    public bool $show_subscriber_names = false;

    /**
     * The plan's currently stored logo path, or null if it doesn't have one
     * (or the user just removed it). Kept separate from $newLogo, which
     * holds a freshly selected upload not yet saved to the plan.
     */
    public ?string $existingLogo = null;

    #[Validate('nullable|image:allow_svg|max:2048')]
    public $newLogo;

    public function mount(Plan $plan): void
    {
        // authorized by the 'can:manage,plan' route middleware
        $this->plan = $plan;
        $this->title = $plan->title;
        $this->description = $plan->description;
        $this->owner_email = $plan->owner_email;
        $this->contact_email = (string) $plan->contact_email;
        $this->contact_phone = (string) $plan->contact_phone;
        $this->allow_unsubscribe = (bool) $plan->allow_unsubscribe;
        $this->show_subscriber_names = (bool) $plan->show_subscriber_names;
        $this->existingLogo = $plan->logo;
    }

    public function removeLogo(): void
    {
        $this->newLogo = null;
        $this->existingLogo = null;
    }

    protected function messages(): array
    {
        return [
            'title.required' => __('plan.titleRequired'),
            'description.required' => __('plan.descriptionRequired'),
            'owner_email.required' => __('plan.emailRequired'),
            'contact_email.email' => __('plan.contactEmailInvalid'),
            'contact_phone.regex' => __('plan.contactPhoneRegex'),
            'newLogo.image' => __('plan.logoInvalid'),
            'newLogo.max' => __('plan.logoMax'),
        ];
    }

    public function render(): Factory|View
    {
        return view('livewire.plan.edit')->title($this->plan->title);
    }

    public function save()
    {
        $this->authorize('update', $this->plan);
        $this->validate();

        $logo = $this->plan->logo;

        if ($this->newLogo) {
            if ($logo) {
                Storage::disk('public')->delete($logo);
            }
            $logo = $this->newLogo->store('plan-logos', 'public');
        } elseif ($this->existingLogo === null && $logo) {
            Storage::disk('public')->delete($logo);
            $logo = null;
        }

        $this->plan->update([
            'title' => $this->title,
            'description' => $this->description,
            'owner_email' => $this->owner_email,
            'contact_email' => $this->contact_email,
            'contact_phone' => $this->contact_phone,
            'allow_unsubscribe' => $this->allow_unsubscribe,
            'show_subscriber_names' => $this->show_subscriber_names,
            'logo' => $logo,
        ]);

        Flux::toast(variant: 'success', text: __('plan.successfullyUpdated'));

        return to_route('plan.manage', $this->plan);
    }
}
