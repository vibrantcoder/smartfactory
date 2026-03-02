<?php

declare(strict_types=1);

namespace App\Http\Resources\Factory;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * FactoryResource
 *
 * Controls the exact JSON shape returned to API consumers.
 * Internal column names never leak directly.
 *
 * CONDITIONAL FIELDS:
 *   settings        → only when loaded (whenLoaded)
 *   machine_count   → only when appended via withCount()
 */
class FactoryResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'          => $this->id,
            'name'        => $this->name,
            'code'        => $this->code,
            'location'    => $this->location,
            'timezone'    => $this->timezone,
            'status'      => $this->status,
            'is_active'   => $this->isActive(),

            // Counts — present only when withCount() was called
            'machine_count'        => $this->whenCounted('machines'),
            'active_machine_count' => $this->whenCounted('active_machine_count'),
            'user_count'           => $this->whenCounted('users'),

            // Nested settings — present only when eager-loaded
            'settings' => $this->whenLoaded('settings', fn() => [
                'oee_target_pct'          => $this->settings->oee_target_pct,
                'availability_target_pct' => $this->settings->availability_target_pct,
                'performance_target_pct'  => $this->settings->performance_target_pct,
                'quality_target_pct'      => $this->settings->quality_target_pct,
                'log_interval_seconds'    => $this->settings->log_interval_seconds,
                'downtime_threshold_min'  => $this->settings->downtime_threshold_min,
            ]),

            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
