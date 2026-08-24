<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreShiftRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'title' => ['required', 'max:200'],
            'type' => ['max:200'],
            'description' => ['max:10000'],
            'group' => ['int', 'numeric', 'max:100', 'min:0'],
            'start' => ['required', 'date', 'before:end'],
            'end' => ['required', 'date', 'after:start'],
            'team_size' => ['required', 'int', 'max:100', 'min:0', 'numeric'],
            'requires_health_certificate' => ['boolean'],
            'requires_clothing_size' => ['boolean'],
            'unsubscribe_lock_hours' => ['integer', 'min:0'],
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
            'title.required' => __('shift.titleRequired'),
            'start.required' => __('shift.startRequired'),
            'start.before' => __('shift.startBefore'),
            'end.required' => __('shift.endRequired'),
            'end.after' => __('shift.endAfter'),
            'team_size.required' => __('shift.team_sizeRequired'),
        ];
    }
}
