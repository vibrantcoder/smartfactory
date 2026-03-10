<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin\WorkOrder;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateWorkOrderRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        // Derive the factory context: prefer user's fixed factory, fall back to WO's factory
        $factoryId = $this->user()->factory_id
            ?? $this->route('work_order')?->factory_id;

        $customerRule = Rule::exists('customers', 'id')->where('status', 'active');
        $partRule     = Rule::exists('parts', 'id')->where('status', 'active');

        if ($factoryId) {
            $customerRule->where('factory_id', $factoryId);
            $partRule->where('factory_id', $factoryId);
        }

        return [
            'customer_id'             => ['sometimes', 'integer', $customerRule],
            'part_id'                 => ['sometimes', 'integer', $partRule],
            'order_qty'               => ['sometimes', 'integer', 'min:1'],
            'excess_qty'              => ['sometimes', 'integer', 'min:0'],
            'expected_delivery_date'  => ['sometimes', 'date'],
            'planned_start_date'      => ['nullable', 'date'],
            'priority'                => ['sometimes', Rule::in(['low', 'medium', 'high', 'urgent'])],
            'status'                  => ['sometimes', Rule::in(['draft', 'confirmed', 'released', 'in_progress', 'completed', 'cancelled'])],
            'notes'                   => ['nullable', 'string', 'max:2000'],
        ];
    }
}
