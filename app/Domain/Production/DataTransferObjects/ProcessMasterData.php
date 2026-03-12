<?php

declare(strict_types=1);

namespace App\Domain\Production\DataTransferObjects;

use App\Http\Requests\Api\Production\CreateProcessMasterRequest;
use App\Http\Requests\Api\Production\UpdateProcessMasterRequest;

/**
 * ProcessMasterData
 *
 * Immutable DTO for creating and updating ProcessMaster records.
 * Not factory-scoped — ProcessMaster is a global reference table.
 */
readonly class ProcessMasterData
{
    public function __construct(
        public string  $name,
        public string  $code,
        public ?float  $standardTime,
        public ?string $machineTypeDefault,
        public string  $processType,
        public ?string $description,
        public bool    $isActive,
    ) {}

    public static function fromCreateRequest(CreateProcessMasterRequest $request): self
    {
        return new self(
            name:               $request->validated('name'),
            code:               strtoupper($request->validated('code')),
            standardTime:       $request->validated('standard_time') !== null
                                    ? (float) $request->validated('standard_time')
                                    : null,
            machineTypeDefault: $request->validated('machine_type_default'),
            processType:        $request->validated('process_type') ?? 'inhouse',
            description:        $request->validated('description'),
            isActive:           true,
        );
    }

    public static function fromUpdateRequest(UpdateProcessMasterRequest $request): self
    {
        return new self(
            name:               $request->validated('name'),
            code:               strtoupper($request->validated('code')),
            standardTime:       $request->validated('standard_time') !== null
                                    ? (float) $request->validated('standard_time')
                                    : null,
            machineTypeDefault: $request->validated('machine_type_default'),
            processType:        $request->validated('process_type') ?? 'inhouse',
            description:        $request->validated('description'),
            isActive:           (bool) $request->validated('is_active', true),
        );
    }

    public function toArray(): array
    {
        return [
            'name'                 => $this->name,
            'code'                 => $this->code,
            'standard_time'        => $this->standardTime ?? 0, // NOT NULL column — default to 0
            'machine_type_default' => $this->machineTypeDefault,
            'process_type'         => $this->processType,
            'description'          => $this->description,
            'is_active'            => $this->isActive,
        ];
    }
}
