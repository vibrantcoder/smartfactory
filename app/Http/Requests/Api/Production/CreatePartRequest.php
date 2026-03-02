<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\Production;

use App\Domain\Shared\Enums\Permission;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CreatePartRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can(Permission::CREATE_PART->value) ?? false;
    }

    public function rules(): array
    {
        // Factory user: use their own factory_id.
        // Super-admin (factory_id=null): factory_id must come from request body.
        $factoryId = $this->user()?->factory_id ?? $this->integer('factory_id') ?: null;

        return [
            // Required only for super-admin (factory_id=null on user)
            'factory_id'     => $this->user()?->factory_id === null
                ? ['required', 'integer', Rule::exists('factories', 'id')]
                : [],
            'customer_id'    => [
                'required',
                'integer',
                // Customer must belong to the same factory
                $factoryId
                    ? Rule::exists('customers', 'id')->where('factory_id', $factoryId)
                    : Rule::exists('customers', 'id'),
            ],
            'part_number'    => [
                'required',
                'string',
                'max:50',
                'regex:/^[A-Z0-9\-_\.]+$/i',
                // Factory-scoped unique — targets uq_parts_factory_number
                Rule::unique('parts', 'part_number')->where('factory_id', $factoryId),
            ],
            'name'           => ['required', 'string', 'max:150'],
            'description'    => ['nullable', 'string', 'max:1000'],
            'unit'           => ['required', 'string', Rule::in(['pcs', 'kg', 'm', 'mm', 'set', 'lot'])],
            'cycle_time_std' => ['nullable', 'numeric', 'min:0', 'max:99999.99'],
        ];
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('part_number')) {
            $this->merge(['part_number' => strtoupper($this->input('part_number'))]);
        }
    }
}
