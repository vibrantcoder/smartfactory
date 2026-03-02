<?php

declare(strict_types=1);

namespace App\Domain\Production\DataTransferObjects;

use App\Http\Requests\Api\Production\CreateCustomerRequest;
use App\Http\Requests\Api\Production\UpdateCustomerRequest;

/**
 * CustomerData
 *
 * Immutable DTO that crosses the HTTP → Service → Repository boundary.
 * factory_id is NEVER included here — it is injected by the repository
 * from the authenticated user's factory context.
 */
readonly class CustomerData
{
    public function __construct(
        public string  $name,
        public string  $code,
        public ?string $contactPerson,
        public ?string $email,
        public ?string $phone,
        public ?string $address,
        public string  $status,
    ) {}

    public static function fromCreateRequest(CreateCustomerRequest $request): self
    {
        return new self(
            name:          $request->validated('name'),
            code:          strtoupper($request->validated('code')),
            contactPerson: $request->validated('contact_person'),
            email:         $request->validated('email'),
            phone:         $request->validated('phone'),
            address:       $request->validated('address'),
            status:        'active',
        );
    }

    public static function fromUpdateRequest(UpdateCustomerRequest $request): self
    {
        return new self(
            name:          $request->validated('name'),
            code:          strtoupper($request->validated('code')),
            contactPerson: $request->validated('contact_person'),
            email:         $request->validated('email'),
            phone:         $request->validated('phone'),
            address:       $request->validated('address'),
            status:        $request->validated('status', 'active'),
        );
    }

    public function toArray(): array
    {
        return [
            'name'           => $this->name,
            'code'           => $this->code,
            'contact_person' => $this->contactPerson,
            'email'          => $this->email,
            'phone'          => $this->phone,
            'address'        => $this->address,
            'status'         => $this->status,
        ];
    }
}
