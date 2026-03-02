<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\Production;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * PreviewCycleTimeRequest
 *
 * Validates the payload for the AJAX cycle-time preview endpoint.
 * POST /api/v1/process-masters/preview-cycle-time
 *
 * Used by the routing builder before the user saves,
 * so they can see the server-confirmed total without persisting.
 */
class PreviewCycleTimeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'steps'                          => ['required', 'array', 'min:1', 'max:20'],
            'steps.*.process_master_id'      => [
                'required',
                'integer',
                Rule::exists('process_masters', 'id')->where('is_active', true),
            ],
            'steps.*.standard_cycle_time'    => ['nullable', 'numeric', 'min:0.01', 'max:9999.99'],
        ];
    }
}
