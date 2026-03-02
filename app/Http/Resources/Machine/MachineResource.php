<?php

declare(strict_types=1);

namespace App\Http\Resources\Machine;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * MachineResource
 *
 * SECURITY: device_token is NEVER included.
 * OEE data included only when the relation is loaded.
 */
class MachineResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'           => $this->id,
            'factory_id'   => $this->factory_id,
            'name'         => $this->name,
            'code'         => $this->code,
            'type'         => $this->type,
            'model'        => $this->model,
            'manufacturer' => $this->manufacturer,
            'status'       => $this->status,
            'is_active'    => $this->isActive(),
            'installed_at' => $this->installed_at?->toDateString(),

            // Factory context — when eager-loaded
            'factory' => $this->whenLoaded('factory', fn() => [
                'id'       => $this->factory->id,
                'name'     => $this->factory->name,
                'code'     => $this->factory->code,
                'timezone' => $this->factory->timezone,
            ]),

            // Latest OEE snapshot — when eager-loaded
            'oee' => $this->whenLoaded('latestOee', fn() => $this->latestOee ? [
                'date'             => $this->latestOee->oee_date,
                'oee_pct'          => $this->latestOee->oee_pct,
                'availability_pct' => $this->latestOee->availability_pct,
                'performance_pct'  => $this->latestOee->performance_pct,
                'quality_pct'      => $this->latestOee->quality_pct,
            ] : null),

            // Active downtime — when eager-loaded
            'active_downtime' => $this->whenLoaded('activeDowntime', fn() => $this->activeDowntime ? [
                'id'           => $this->activeDowntime->id,
                'started_at'   => $this->activeDowntime->started_at?->toIso8601String(),
                'elapsed_min'  => (int) now()->diffInMinutes($this->activeDowntime->started_at),
                'category'     => $this->activeDowntime->category,
            ] : null),

            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
