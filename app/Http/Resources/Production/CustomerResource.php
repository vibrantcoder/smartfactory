<?php

declare(strict_types=1);

namespace App\Http\Resources\Production;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * CustomerResource
 *
 * Counts included only when loaded via withCount().
 * Parts included only when eager-loaded.
 */
class CustomerResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'             => $this->id,
            'factory_id'     => $this->factory_id,
            'name'           => $this->name,
            'code'           => $this->code,
            'contact_person' => $this->contact_person,
            'email'          => $this->email,
            'phone'          => $this->phone,
            'address'        => $this->address,
            'status'         => $this->status,
            'is_active'      => $this->isActive(),

            // Counts — only when loaded via withCount()
            'parts_count'        => $this->whenCounted('parts'),
            'active_parts_count' => $this->whenCounted('active_parts_count'),

            // Parts list — only when eager-loaded
            'parts' => $this->whenLoaded('parts', fn() =>
                PartResource::collection($this->parts)
            ),

            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
