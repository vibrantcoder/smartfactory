<?php

declare(strict_types=1);

namespace App\Http\Resources\Production;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * ProcessMasterResource
 *
 * standard_time exposed as float so the frontend routing builder can use it
 * directly for live cycle time calculation without type coercion.
 */
class ProcessMasterResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'                   => $this->id,
            'name'                 => $this->name,
            'code'                 => $this->code,
            'standard_time'        => $this->standard_time !== null
                                          ? (float) $this->standard_time
                                          : null,
            'machine_type_default' => $this->machine_type_default,
            'description'          => $this->description,
            'is_active'            => (bool) $this->is_active,

            // Usage count — only when loaded via withCount()
            'part_processes_count' => $this->whenCounted('partProcesses'),

            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
