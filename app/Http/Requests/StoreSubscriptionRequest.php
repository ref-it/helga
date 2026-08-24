<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreSubscriptionRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'max:100'],
            'email' => ['email', 'max:100'],
            'phone' => ['nullable', 'regex:/[0-9\s]{10,15}/'],
            'notification' => ['boolean'],
            'comment' => ['max:500'],
            'locale' => ['required', 'in:de,en,es'],
            'health_certificate_confirmed' => ['boolean'],
            'clothing_size' => ['nullable', 'in:S,M,L,XL,XXL'],
        ];
    }

    /**
     * Messages for validation errors
     *
     * @return string[]
     */
    public function messages()
    {
        return [
            'name.required' => __('subscription.nameRequired'),
            'phone.regex' => __('subscription.phoneRegex'),
            'comment.max' => __('subscription.commentMax'),
            'locale.in' => __('subscription.validLanguage'),
        ];
    }
}
