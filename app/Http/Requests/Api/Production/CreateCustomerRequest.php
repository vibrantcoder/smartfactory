<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\Production;

use App\Domain\Shared\Enums\Permission;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CreateCustomerRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can(Permission::CREATE_CUSTOMER->value) ?? false;
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
            'name'           => ['required', 'string', 'max:120'],
            'code'           => [
                'required',
                'string',
                'max:30',
                'regex:/^[A-Z0-9\-_]+$/i',
                // Factory-scoped unique — targets uq_customers_factory_code
                Rule::unique('customers', 'code')->where('factory_id', $factoryId),
            ],
            'contact_person' => ['nullable', 'string', 'max:100'],
            'email'          => ['nullable', 'email', 'max:100'],
            'phone'          => ['nullable', 'string', 'max:30'],
            'address'        => ['nullable', 'string', 'max:255'],
        ];
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('code')) {
            $this->merge(['code' => strtoupper($this->input('code'))]);
        }
    }
}
