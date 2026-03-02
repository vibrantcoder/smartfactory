<?php

declare(strict_types=1);

namespace App\Domain\Production\Models;

use App\Domain\Production\QueryBuilders\CustomerQueryBuilder;
use App\Domain\Shared\Models\BaseModel;
use App\Domain\Shared\Traits\BelongsToFactory;
use App\Domain\Shared\Traits\HasFactoryScope;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Customer
 *
 * Represents a client who orders parts manufactured in this factory.
 * A customer can have many parts; each part defines its own process routing.
 *
 * @property int         $id
 * @property int         $factory_id
 * @property string      $name
 * @property string      $code          unique within factory (uq_customers_factory_code)
 * @property string|null $contact_person
 * @property string|null $email
 * @property string|null $phone
 * @property string|null $address
 * @property string      $status        active|inactive
 *
 * @method static CustomerQueryBuilder query()
 * @method static CustomerQueryBuilder active()
 * @method static CustomerQueryBuilder search(string $term)
 */
class Customer extends BaseModel
{
    use HasFactoryScope, BelongsToFactory;

    protected $table = 'customers';

    protected $fillable = [
        'factory_id',
        'name',
        'code',
        'contact_person',
        'email',
        'phone',
        'address',
        'status',
    ];

    protected function casts(): array
    {
        return [
            ...parent::casts(),
            'status' => 'string',
        ];
    }

    public function newEloquentBuilder($query): CustomerQueryBuilder
    {
        return new CustomerQueryBuilder($query);
    }

    // ── Relationships ─────────────────────────────────────────

    public function parts(): HasMany
    {
        return $this->hasMany(Part::class, 'customer_id');
    }

    public function activeParts(): HasMany
    {
        return $this->hasMany(Part::class, 'customer_id')
            ->where('status', 'active');
    }

    // ── Accessors ─────────────────────────────────────────────

    public function isActive(): bool
    {
        return $this->status === 'active';
    }
}
