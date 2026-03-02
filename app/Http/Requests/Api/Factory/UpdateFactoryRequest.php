<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\Factory;

use App\Domain\Shared\Enums\Permission;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateFactoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can(Permission::UPDATE_FACTORY->value) ?? false;
    }

    public function rules(): array
    {
        // Route model binding resolves {factory} to the Factory model
        $factoryId = $this->route('factory')?->id;

        return [
            'name'     => ['required', 'string', 'min:2', 'max:150'],
            'code'     => [
                'required',
                'string',
                'min:3',
                'max:20',
                'regex:/^[A-Z0-9\-]+$/i',
                Rule::unique('factories', 'code')->ignore($factoryId), // global unique, ignore self
            ],
            'location' => ['nullable', 'string', 'max:255'],
            'timezone' => [
                'required',
                'string',
                Rule::in(timezone_identifiers_list()),
            ],
            'status'   => ['required', Rule::in(['active', 'inactive'])],
        ];
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('code')) {
            $this->merge(['code' => strtoupper(trim($this->input('code', '')))]);
        }
    }
}
