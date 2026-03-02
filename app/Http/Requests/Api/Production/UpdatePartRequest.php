<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\Production;

use App\Domain\Shared\Enums\Permission;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdatePartRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can(Permission::UPDATE_PART->value) ?? false;
    }

    public function rules(): array
    {
        // Use the part model's own factory_id — correct even for super-admin
        $factoryId = $this->route('part')?->factory_id ?? $this->user()?->factory_id;
        $partId    = $this->route('part')?->id;

        return [
            'customer_id'    => [
                'required',
                'integer',
                Rule::exists('customers', 'id')->where('factory_id', $factoryId),
            ],
            'part_number'    => [
                'required',
                'string',
                'max:50',
                'regex:/^[A-Z0-9\-_\.]+$/i',
                // Factory-scoped unique — ignore self
                Rule::unique('parts', 'part_number')
                    ->where('factory_id', $factoryId)
                    ->ignore($partId),
            ],
            'name'           => ['required', 'string', 'max:150'],
            'description'    => ['nullable', 'string', 'max:1000'],
            'unit'           => ['required', 'string', Rule::in(['pcs', 'kg', 'm', 'mm', 'set', 'lot'])],
            'cycle_time_std' => ['nullable', 'numeric', 'min:0', 'max:99999.99'],
            'status'         => ['required', Rule::in(['active', 'discontinued'])],
        ];
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('part_number')) {
            $this->merge(['part_number' => strtoupper($this->input('part_number'))]);
        }
    }
}
