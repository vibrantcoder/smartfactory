<?php

declare(strict_types=1);

namespace App\Http\Resources\Production;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * PartResource
 *
 * cycle_time_std exposed as float for OEE Performance calculations on the frontend.
 * Routing (processes) included only when eager-loaded with withProcesses().
 */
class PartResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'             => $this->id,
            'factory_id'     => $this->factory_id,
            'customer_id'    => $this->customer_id,
            'part_number'    => $this->part_number,
            'name'           => $this->name,
            'description'    => $this->description,
            'unit'           => $this->unit,
            'cycle_time_std'   => (float) $this->cycle_time_std,
            'total_cycle_time' => $this->total_cycle_time ? (float) $this->total_cycle_time : null,
            'status'           => $this->status,
            'is_active'      => $this->isActive(),

            // Count — only when loaded via withCount()
            'processes_count' => $this->whenCounted('processes'),
            'drawings_count'  => $this->whenCounted('drawings'),

            // Customer context — when eager-loaded
            'customer' => $this->whenLoaded('customer', fn() => [
                'id'     => $this->customer->id,
                'name'   => $this->customer->name,
                'code'   => $this->customer->code,
                'status' => $this->customer->status,
            ]),

            // Full ordered routing — when eager-loaded
            'processes' => $this->whenLoaded('processes', fn() =>
                $this->processes->map(fn($process) => [
                    'id'                    => $process->id,
                    'sequence_order'        => $process->sequence_order,
                    'machine_type_required' => $process->machine_type_required,
                    'standard_cycle_time'   => $process->standard_cycle_time
                        ? (float) $process->standard_cycle_time
                        : null,
                    'setup_time'            => $process->setup_time
                        ? (float) $process->setup_time
                        : null,
                    'process_type'          => $process->process_type ?? 'inhouse',
                    'effective_cycle_time'  => $process->effectiveCycleTime(),
                    'notes'                 => $process->notes,

                    'process_master' => $process->relationLoaded('processMaster') && $process->processMaster
                        ? [
                            'id'                   => $process->processMaster->id,
                            'name'                 => $process->processMaster->name,
                            'code'                 => $process->processMaster->code,
                            'machine_type_default' => $process->processMaster->machine_type_default,
                        ]
                        : null,
                ])
            ),

            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
