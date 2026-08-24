<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StorePlanRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'title' => ['required', 'max:200'],
            'description' => ['required', 'max:10000'],
            'contact_email' => ['nullable', 'email', 'max:200'],
            'contact_phone' => ['nullable', 'regex:/[0-9\s]{10,15}/'],
            'owner_email' => ['required', 'email'],
            'allow_unsubscribe' => ['boolean'],
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
            'title.required' => __('plan.titleRequired'),
            'description.required' => __('plan.descriptionRequired'),
            'contact_email.email' => __('plan.contactEmailInvalid'),
            'contact_phone.regex' => __('plan.contactPhoneRegex'),
            'owner_email.required' => __('plan.emailRequired'),
        ];
    }
}
