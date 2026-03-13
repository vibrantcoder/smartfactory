<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\Production;

use App\Domain\Shared\Enums\Permission;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateProcessMasterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can(Permission::UPDATE_PROCESS_MASTER->value) ?? false;
    }

    public function rules(): array
    {
        $processMasterId = $this->route('process_master')?->id;

        return [
            'name'                 => ['required', 'string', 'max:100'],
            'code'                 => [
                'required',
                'string',
                'max:20',
                'regex:/^[A-Z0-9_\-]+$/i',
                Rule::unique('process_masters', 'code')->ignore($processMasterId),
            ],
            'machine_type_default' => ['nullable', 'string', 'max:50'],
            'process_type'         => ['nullable', 'string', 'in:inhouse,outside'],
            'description'          => ['nullable', 'string', 'max:500'],
            'is_active'            => ['required', 'boolean'],
        ];
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('code')) {
            $this->merge(['code' => strtoupper($this->input('code'))]);
        }
    }
}
