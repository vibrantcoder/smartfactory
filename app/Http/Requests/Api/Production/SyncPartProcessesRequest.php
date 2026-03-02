<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\Production;

use App\Domain\Shared\Enums\Permission;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * SyncPartProcessesRequest
 *
 * Validates the routing replacement payload for PUT /parts/{part}/processes.
 * The entire process array is always replaced atomically.
 */
class SyncPartProcessesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can(Permission::UPDATE_PART->value) ?? false;
    }

    public function rules(): array
    {
        return [
            'processes'                          => ['required', 'array', 'min:1', 'max:20'],
            'processes.*.process_master_id'      => [
                'required',
                'integer',
                Rule::exists('process_masters', 'id'),
            ],
            'processes.*.machine_type_required'  => ['nullable', 'string', 'max:50'],
            'processes.*.standard_cycle_time'    => ['nullable', 'numeric', 'min:0', 'max:99999.99'],
            'processes.*.notes'                  => ['nullable', 'string', 'max:500'],
        ];
    }

    public function messages(): array
    {
        return [
            'processes.min' => 'At least one process step is required.',
            'processes.max' => 'A part cannot have more than 20 process steps.',
            'processes.*.process_master_id.exists' => 'Process master [:input] does not exist.',
        ];
    }
}
