<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\Machine;

use App\Domain\Shared\Enums\Permission;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * CreateMachineRequest
 *
 * FACTORY-SCOPED UNIQUE VALIDATION:
 *   Machine code must be unique within the factory, NOT globally.
 *   Rule::unique('machines', 'code')->where('factory_id', $factoryId)
 *   This targets the uq_machines_factory_code composite index.
 *
 * SECURITY: factory_id is NOT a validated field — it is ALWAYS injected
 * from the authenticated user's factory_id in the action layer.
 */
class CreateMachineRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can(Permission::CREATE_MACHINE->value) ?? false;
    }

    public function rules(): array
    {
        $factoryId = $this->user()?->factory_id ?? $this->integer('factory_id');

        return [
            'name'         => ['required', 'string', 'min:2', 'max:150'],
            'code'         => [
                'required',
                'string',
                'max:50',
                'regex:/^[A-Z0-9\-_]+$/i',
                // Composite unique: code + factory_id
                Rule::unique('machines', 'code')
                    ->where('factory_id', $factoryId),
            ],
            'type'         => ['required', 'string', 'max:100'],
            'model'        => ['nullable', 'string', 'max:100'],
            'manufacturer' => ['nullable', 'string', 'max:150'],
            'installed_at' => ['nullable', 'date', 'before_or_equal:today'],
        ];
    }

    public function messages(): array
    {
        return [
            'code.regex'  => 'Machine code may only contain letters, numbers, hyphens, and underscores.',
            'code.unique' => 'This machine code is already in use within your factory.',
        ];
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('code')) {
            $this->merge(['code' => strtoupper(trim($this->input('code', '')))]);
        }
    }
}
