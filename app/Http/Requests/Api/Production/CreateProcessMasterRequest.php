<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\Production;

use App\Domain\Shared\Enums\Permission;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CreateProcessMasterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can(Permission::CREATE_PROCESS_MASTER->value) ?? false;
    }

    public function rules(): array
    {
        return [
            'name'                 => ['required', 'string', 'max:100'],
            'code'                 => [
                'required',
                'string',
                'max:20',
                'regex:/^[A-Z0-9_\-]+$/i',
                Rule::unique('process_masters', 'code'),
            ],
            'machine_type_default' => ['nullable', 'string', 'max:50'],
            'process_type'         => ['nullable', 'string', 'in:inhouse,outside'],
            'description'          => ['nullable', 'string', 'max:500'],
        ];
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('code')) {
            $this->merge(['code' => strtoupper($this->input('code'))]);
        }
    }
}
