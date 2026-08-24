<?php

namespace App\Livewire\Subscription;

use App\Models\Plan;
use App\Models\Shift;
use App\Models\Subscription;
use Flux\Flux;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Locked;
use Livewire\Component;

class Create extends Component
{
    #[Locked]
    public Plan $plan;

    #[Locked]
    public Shift $shift;

    public string $name = '';

    public string $phone = '';

    public string $email = '';

    public bool $notification = false;

    public string $comment = '';

    public bool $health_certificate_confirmed = false;

    public string $clothing_size = '';

    /**
     * Whether {@see $phone} was taken over from the logged-in user's account
     * (the OIDC "phone" claim) rather than guessed or left blank - only then
     * is the field locked, mirroring name/email.
     */
    #[Locked]
    public bool $phoneFromAccount = false;

    public string $captchaUrl = '';

    public string $captcha = '';

    protected function rules(): array
    {
        return [
            'name' => 'required|max:100',
            'phone' => 'nullable|regex:/[0-9\s]{10,15}/',
            // anonymous visitors must provide a (later verified) email; a
            // logged-in visitor's own account email is trusted already
            'email' => Auth::check() ? 'nullable|email|max:100' : 'required|email|max:100',
            'comment' => 'nullable|max:500',
            // only shifts that require it force this checkbox to be ticked
            'health_certificate_confirmed' => $this->shift->requires_health_certificate ? 'accepted' : 'boolean',
            // only shifts that require it force a size to be picked
            'clothing_size' => $this->shift->requires_clothing_size ? 'required|in:S,M,L,XL,XXL' : 'nullable|in:S,M,L,XL,XXL',
            // the captcha guards against anonymous spam signups - a logged-in
            // visitor is already authenticated via OIDC, so it's skipped
            'captcha' => Auth::check() ? 'nullable' : 'required|captcha',
        ];
    }

    protected function messages(): array
    {
        return [
            'name.required' => __('subscription.nameRequired'),
            'phone.regex' => __('subscription.phoneRegex'),
            'email.required' => __('subscription.emailRequired'),
            'comment.max' => __('subscription.commentMax'),
            'health_certificate_confirmed.accepted' => __('subscription.healthCertificateRequired'),
            'clothing_size.required' => __('subscription.clothingSizeRequired'),
            'captcha.required' => __('subscription.captchaRequired'),
            'captcha.captcha' => __('subscription.captchaRequired'),
        ];
    }

    public function mount(Plan $plan, Shift $shift): void
    {
        // anonymous users can subscribe to a shift they have the link to,
        // but only once the plan has been published - we can't use auth
        // Policies for the shift/plan integrity check below
        if ($shift->plan->id !== $plan->id) {
            abort(403);
        }
        $this->authorize('show', $plan);

        $this->plan = $plan;
        $this->shift = $shift;

        // no need for a captcha challenge if the visitor is already
        // authenticated via OIDC
        if (! Auth::check()) {
            $this->refreshCaptcha();
        }

        // logged-in visitors sign up as themselves - name/email come from
        // their account and can't be edited (see the view and save() below);
        // signing up stays fully anonymous otherwise
        if ($user = Auth::user()) {
            $this->name = (string) $user->name;
            $this->email = (string) $user->email;

            // prefer the account's own phone number (from the OIDC "phone"
            // scope, if granted) - it's locked, just like name/email, since
            // it's no longer just a guess. Otherwise fall back to a
            // convenience guess from their last subscription, which stays
            // editable since it isn't trusted as the verified account value
            if ($user->phone) {
                $this->phone = (string) $user->phone;
                $this->phoneFromAccount = true;
            } else {
                $this->phone = (string) (Subscription::where('email', $user->email)
                    ->whereNotNull('phone')
                    ->where('phone', '!=', '')
                    ->orderByDesc('id')
                    ->value('phone') ?? '');
            }
        }
    }

    public function refreshCaptcha(): void
    {
        $this->captchaUrl = captcha_src();
        $this->captcha = '';
    }

    public function render()
    {
        return view('livewire.subscription.create')->title($this->shift->title);
    }

    public function save()
    {
        $user = Auth::user();

        if ($user) {
            // never trust client-supplied values for these - the fields are
            // readonly in the UI, but a tampered request could still set them
            $this->name = (string) $user->name;
            $this->email = (string) $user->email;

            if ($this->phoneFromAccount) {
                $this->phone = (string) $user->phone;
            }
        }

        try {
            $this->validate();
        } catch (ValidationException $e) {
            // the captcha token is consumed by every check attempt, so a fresh
            // image is required before the user can submit again
            if (! $user) {
                $this->refreshCaptcha();
            }
            throw $e;
        }

        if ($this->shift->team_size <= $this->shift->subscriptions()->count()) {
            Flux::toast(variant: 'danger', text: __('subscription.enoughSubscription'));

            return to_route('plan.show', ['plan' => $this->plan]);
        }

        // a logged-in visitor's email is their account's own, already
        // trusted via the OIDC login - anonymous visitors must confirm theirs
        $isVerified = $user && $this->email !== '';

        $subscription = $this->shift->subscriptions()->create([
            'name' => $this->name,
            'phone' => $this->phone,
            'email' => $this->email,
            // never trust a client-supplied true here if the feature is off -
            // the checkbox is hidden in the UI, but a tampered request could
            // still set it
            'notification' => config('app.reminders_enabled') && $this->notification,
            'comment' => $this->comment,
            'locale' => app()->getLocale(),
            'email_verified_at' => $isVerified ? now() : null,
            'health_certificate_confirmed' => $this->health_certificate_confirmed,
            'clothing_size' => $this->clothing_size !== '' ? $this->clothing_size : null,
        ]);

        if (! $user) {
            $subscription->sendEmailVerification();
            Flux::toast(variant: 'success', text: __('subscription.successfullyCreatedVerifyEmail'));
        } else {
            Flux::toast(variant: 'success', text: __('subscription.successfullyCreated'));
        }

        return to_route('plan.show', ['plan' => $this->plan]);
    }
}
