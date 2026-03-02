<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\Machine;

use App\Domain\Shared\Enums\Permission;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateMachineRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can(Permission::UPDATE_MACHINE->value) ?? false;
    }

    public function rules(): array
    {
        $factoryId = $this->user()?->factory_id;
        $machineId = $this->route('machine')?->id;

        return [
            'name'         => ['required', 'string', 'min:2', 'max:150'],
            'code'         => [
                'required',
                'string',
                'max:50',
                'regex:/^[A-Z0-9\-_]+$/i',
                // Factory-scoped unique, ignore current machine ID
                Rule::unique('machines', 'code')
                    ->where('factory_id', $factoryId)
                    ->ignore($machineId),
            ],
            'type'         => ['required', 'string', 'max:100'],
            'model'        => ['nullable', 'string', 'max:100'],
            'manufacturer' => ['nullable', 'string', 'max:150'],
            'status'       => ['required', Rule::in(['active', 'maintenance', 'retired'])],
            'installed_at' => ['nullable', 'date', 'before_or_equal:today'],
        ];
    }

    public function messages(): array
    {
        return [
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
