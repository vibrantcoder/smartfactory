<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin\WorkOrder;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CreateWorkOrderRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        $user      = $this->user();
        $factoryId = $user->factory_id ?? $this->integer('factory_id') ?: null;

        // Base customer/part existence rules (always check they exist and are active)
        $customerRule = Rule::exists('customers', 'id')->where('status', 'active');
        $partRule     = Rule::exists('parts', 'id')->where('status', 'active');

        // If we know the factory, also enforce factory isolation
        if ($factoryId) {
            $customerRule->where('factory_id', $factoryId);
            $partRule->where('factory_id', $factoryId);
        }

        return [
            // factory_id: required for super-admin (factory_id = null on user),
            //             optional (ignored) for factory-scoped users
            'factory_id'              => [
                $user->factory_id === null ? 'required' : 'sometimes',
                'integer',
                'exists:factories,id',
            ],
            'customer_id'             => ['required', 'integer', $customerRule],
            'part_id'                 => ['required', 'integer', $partRule],
            'order_qty'               => ['required', 'integer', 'min:1'],
            'excess_qty'              => ['sometimes', 'integer', 'min:0'],
            'expected_delivery_date'  => ['required', 'date', 'after_or_equal:today'],
            'planned_start_date'      => ['nullable', 'date', 'before_or_equal:expected_delivery_date'],
            'priority'                => ['sometimes', Rule::in(['low', 'medium', 'high', 'urgent'])],
            'status'                  => ['sometimes', Rule::in(['draft', 'confirmed'])],
            'notes'                   => ['nullable', 'string', 'max:2000'],
        ];
    }

    public function messages(): array
    {
        return [
            'factory_id.required' => 'Please select a factory before creating a work order.',
        ];
    }
}
