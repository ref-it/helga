<?php

namespace App\Livewire\Subscription;

use App\Models\Plan;
use App\Models\Shift;
use App\Models\Subscription;
use Flux\Flux;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Component;

class Create extends Component
{
    #[Locked]
    public Plan $plan;

    /**
     * The shifts this sign-up covers - one subscription is created per
     * shift from the single set of contact details below. Usually just the
     * one shift whose "subscribe" button was clicked, but the plan view can
     * hand over several ticked shifts at once.
     *
     * @var array<int, int>
     */
    #[Locked]
    public array $shiftIds = [];

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

    /**
     * @return EloquentCollection<int, Shift>
     */
    #[Computed]
    public function shifts(): EloquentCollection
    {
        return Shift::whereIn('id', $this->shiftIds)
            ->with('subscriptions')
            ->orderBy('start')
            ->get();
    }

    /**
     * A field is only asked for - and only enforced - if at least one of the
     * selected shifts needs it.
     */
    #[Computed]
    public function requiresHealthCertificate(): bool
    {
        return $this->shifts->contains('requires_health_certificate', true);
    }

    #[Computed]
    public function requiresClothingSize(): bool
    {
        return $this->shifts->contains('requires_clothing_size', true);
    }

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
            'health_certificate_confirmed' => $this->requiresHealthCertificate ? 'accepted' : 'boolean',
            // only shifts that require it force a size to be picked
            'clothing_size' => $this->requiresClothingSize ? 'required|in:S,M,L,XL,XXL' : 'nullable|in:S,M,L,XL,XXL',
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

    public function mount(Plan $plan, ?Shift $shift = null)
    {
        // anonymous users can subscribe to a shift they have the link to,
        // but only once the plan has been published - we can't use auth
        // Policies for the shift/plan integrity check below
        $this->authorize('show', $plan);

        $this->plan = $plan;

        // one route carries a single shift in its path, the other a list of
        // ticked shifts in its query string - a `Shift` the container made up
        // for the missing route parameter has no record behind it
        if ($shift instanceof Shift && $shift->exists) {
            if ($shift->plan_id !== $plan->id) {
                abort(403);
            }

            // a direct link to a shift that is over - the plan page no longer
            // offers it, so don't show a form that can't succeed
            if ($shift->hasEnded()) {
                Flux::toast(variant: 'danger', text: __('subscription.enoughSubscription'));

                return $this->redirectRoute('plan.show', ['plan' => $plan], navigate: true);
            }

            $this->shiftIds = [$shift->id];
        } else {
            $this->shiftIds = $this->shiftIdsFromRequest($plan);

            if ($this->shiftIds === []) {
                Flux::toast(variant: 'danger', text: __('subscription.enoughSubscription'));

                return $this->redirectRoute('plan.show', ['plan' => $plan], navigate: true);
            }
        }

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

        return null;
    }

    public function refreshCaptcha(): void
    {
        $this->captchaUrl = captcha_src();
        $this->captcha = '';
    }

    public function render()
    {
        return view('livewire.subscription.create')
            ->title(__('subscription.subscribe').': '.$this->plan->title);
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

        // a shift may well have filled up while the form was open - sign up
        // for the ones that still have room and report on the rest - a shift
        // may also have ended while the form was open
        $open = $this->shifts->filter(
            fn (Shift $shift): bool => ! $shift->hasEnded()
                && $shift->team_size > $shift->subscriptions()->count()
        );

        if ($open->isEmpty()) {
            Flux::toast(variant: 'danger', text: __('subscription.enoughSubscription'));

            return to_route('plan.show', ['plan' => $this->plan]);
        }

        // a logged-in visitor's email is their account's own, already
        // trusted via the OIDC login - anonymous visitors must confirm theirs
        $isVerified = $user && $this->email !== '';

        $subscriptions = $open->map(fn (Shift $shift): Subscription => $shift->subscriptions()->create([
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
        ]));

        $skipped = $this->shifts->count() - $subscriptions->count();

        if (! $user) {
            // one sign-up, one email - it confirms the address for every
            // subscription it just created
            Subscription::sendEmailVerificationFor($subscriptions);

            Flux::toast(variant: 'success', text: $subscriptions->count() === 1
                ? __('subscription.successfullyCreatedVerifyEmail')
                : __('subscription.successfullyCreatedManyVerifyEmail', ['count' => $subscriptions->count()]));
        } else {
            Flux::toast(variant: 'success', text: $subscriptions->count() === 1
                ? __('subscription.successfullyCreated')
                : __('subscription.successfullyCreatedMany', ['count' => $subscriptions->count()]));
        }

        if ($skipped > 0) {
            Flux::toast(variant: 'warning', text: __('subscription.someShiftsTaken', ['count' => $skipped]));
        }

        return to_route('plan.show', ['plan' => $this->plan]);
    }

    /**
     * The ticked shifts from the `shifts` query parameter, reduced to the
     * ones of this plan the visitor could actually still join - anything
     * else is stale (filled up meanwhile) or forged.
     *
     * @return array<int, int>
     */
    private function shiftIdsFromRequest(Plan $plan): array
    {
        $requested = array_map(intval(...), (array) request()->query('shifts', []));

        return $plan->shifts()
            ->with('subscriptions')
            ->whereIn('id', $requested)
            ->get()
            ->filter(fn (Shift $shift): bool => $shift->isOpenFor(Auth::user()))
            ->pluck('id')
            ->all();
    }
}
