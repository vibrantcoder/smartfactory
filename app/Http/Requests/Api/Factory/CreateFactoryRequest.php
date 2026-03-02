<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\Factory;

use App\Domain\Shared\Enums\Permission;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * CreateFactoryRequest
 *
 * AUTHORIZATION: create.factory permission (Super Admin only).
 *
 * UNIQUE CONSTRAINT:
 *   Factory codes are globally unique (not factory-scoped).
 *   Rule::unique('factories', 'code') with no additional WHERE.
 */
class CreateFactoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can(Permission::CREATE_FACTORY->value) ?? false;
    }

    public function rules(): array
    {
        return [
            'name'     => ['required', 'string', 'min:2', 'max:150'],
            'code'     => [
                'required',
                'string',
                'min:3',
                'max:20',
                'regex:/^[A-Z0-9\-]+$/i',          // alphanumeric + hyphen only
                Rule::unique('factories', 'code'),   // global unique — no WHERE clause
            ],
            'location' => ['nullable', 'string', 'max:255'],
            'timezone' => [
                'required',
                'string',
                Rule::in(timezone_identifiers_list()),
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'code.regex'  => 'Factory code may only contain letters, numbers, and hyphens.',
            'code.unique' => 'This factory code is already in use.',
            'timezone.in' => 'The timezone must be a valid IANA timezone identifier.',
        ];
    }

    protected function prepareForValidation(): void
    {
        // Normalise code to uppercase before validation runs
        if ($this->has('code')) {
            $this->merge(['code' => strtoupper(trim($this->input('code', '')))]);
        }
    }
}
