<?php

declare(strict_types=1);

namespace App\Domain\Factory\DataTransferObjects;

use App\Http\Requests\Api\Factory\CreateFactoryRequest;
use App\Http\Requests\Api\Factory\UpdateFactoryRequest;

/**
 * FactoryData — immutable value object for factory write operations.
 *
 * Crosses the boundary: HTTP Request → Service → Repository.
 * Never exposes Eloquent models beyond the repository layer.
 */
final readonly class FactoryData
{
    public function __construct(
        public string  $name,
        public string  $code,
        public ?string $location,
        public string  $timezone,
        public string  $status,
    ) {}

    public static function fromCreateRequest(CreateFactoryRequest $request): self
    {
        return new self(
            name:     $request->validated('name'),
            code:     strtoupper(trim($request->validated('code'))),
            location: $request->validated('location'),
            timezone: $request->validated('timezone', 'UTC'),
            status:   'active',
        );
    }

    public static function fromUpdateRequest(UpdateFactoryRequest $request): self
    {
        return new self(
            name:     $request->validated('name'),
            code:     strtoupper(trim($request->validated('code'))),
            location: $request->validated('location'),
            timezone: $request->validated('timezone', 'UTC'),
            status:   $request->validated('status', 'active'),
        );
    }

    public function toArray(): array
    {
        return [
            'name'     => $this->name,
            'code'     => $this->code,
            'location' => $this->location,
            'timezone' => $this->timezone,
            'status'   => $this->status,
        ];
    }
}
