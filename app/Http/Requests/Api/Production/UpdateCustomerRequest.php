<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\Production;

use App\Domain\Shared\Enums\Permission;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateCustomerRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can(Permission::UPDATE_CUSTOMER->value) ?? false;
    }

    public function rules(): array
    {
        // Use the customer model's own factory_id — correct even for super-admin
        $factoryId  = $this->route('customer')?->factory_id ?? $this->user()?->factory_id;
        $customerId = $this->route('customer')?->id;

        return [
            'name'           => ['required', 'string', 'max:120'],
            'code'           => [
                'required',
                'string',
                'max:30',
                'regex:/^[A-Z0-9\-_]+$/i',
                // Factory-scoped unique — ignore self
                Rule::unique('customers', 'code')
                    ->where('factory_id', $factoryId)
                    ->ignore($customerId),
            ],
            'contact_person' => ['nullable', 'string', 'max:100'],
            'email'          => ['nullable', 'email', 'max:100'],
            'phone'          => ['nullable', 'string', 'max:30'],
            'address'        => ['nullable', 'string', 'max:255'],
            'status'         => ['required', Rule::in(['active', 'inactive'])],
        ];
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('code')) {
            $this->merge(['code' => strtoupper($this->input('code'))]);
        }
    }
}
