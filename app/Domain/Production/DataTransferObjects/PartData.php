<?php

declare(strict_types=1);

namespace App\Domain\Production\DataTransferObjects;

use App\Http\Requests\Api\Production\CreatePartRequest;
use App\Http\Requests\Api\Production\UpdatePartRequest;

/**
 * PartData
 *
 * Immutable DTO for creating and updating Parts.
 * factory_id is NOT included — injected by the repository.
 * processes (routing) are handled separately via syncRouting().
 */
readonly class PartData
{
    public function __construct(
        public int     $customerId,
        public string  $partNumber,
        public string  $name,
        public ?string $description,
        public string  $unit,
        public float   $cycleTimeStd,
        public string  $status,
    ) {}

    public static function fromCreateRequest(CreatePartRequest $request): self
    {
        return new self(
            customerId:   (int) $request->validated('customer_id'),
            partNumber:   strtoupper($request->validated('part_number')),
            name:         $request->validated('name'),
            description:  $request->validated('description'),
            unit:         $request->validated('unit', 'pcs'),
            cycleTimeStd: (float) $request->validated('cycle_time_std', 0),
            status:       'active',
        );
    }

    public static function fromUpdateRequest(UpdatePartRequest $request): self
    {
        return new self(
            customerId:   (int) $request->validated('customer_id'),
            partNumber:   strtoupper($request->validated('part_number')),
            name:         $request->validated('name'),
            description:  $request->validated('description'),
            unit:         $request->validated('unit', 'pcs'),
            cycleTimeStd: (float) $request->validated('cycle_time_std', 0),
            status:       $request->validated('status', 'active'),
        );
    }

    public function toArray(): array
    {
        return [
            'customer_id'    => $this->customerId,
            'part_number'    => $this->partNumber,
            'name'           => $this->name,
            'description'    => $this->description,
            'unit'           => $this->unit,
            'cycle_time_std' => $this->cycleTimeStd,
            'status'         => $this->status,
        ];
    }
}
