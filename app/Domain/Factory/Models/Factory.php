<?php

declare(strict_types=1);

namespace App\Domain\Factory\Models;

use App\Domain\Factory\QueryBuilders\FactoryQueryBuilder;
use App\Domain\Machine\Models\Machine;
use App\Domain\Production\Models\Customer;
use App\Domain\Shared\Models\BaseModel;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * Factory
 *
 * Root tenant model. Every operational record traces back here.
 *
 * NOTE: Factory itself does NOT use HasFactoryScope (it IS the tenant).
 * It also does NOT use BelongsToFactory (no factory_id column on this table).
 *
 * @property int         $id
 * @property string      $name
 * @property string      $code
 * @property string|null $location
 * @property string      $timezone
 * @property string      $status        active|inactive
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 *
 * @method static FactoryQueryBuilder query()
 * @method static FactoryQueryBuilder active()
 */
class Factory extends BaseModel
{
    protected $table = 'factories';

    protected $fillable = [
        'name',
        'code',
        'location',
        'timezone',
        'status',
    ];

    protected function casts(): array
    {
        return [
            ...parent::casts(),
            'status' => 'string',
        ];
    }

    // ── Custom QueryBuilder ───────────────────────────────────

    public function newEloquentBuilder($query): FactoryQueryBuilder
    {
        return new FactoryQueryBuilder($query);
    }

    // ── Relationships ─────────────────────────────────────────

    public function settings(): HasOne
    {
        return $this->hasOne(FactorySettings::class, 'factory_id');
    }

    public function machines(): HasMany
    {
        return $this->hasMany(Machine::class, 'factory_id');
    }

    public function customers(): HasMany
    {
        return $this->hasMany(Customer::class, 'factory_id');
    }

    public function users(): HasMany
    {
        return $this->hasMany(\App\Models\User::class, 'factory_id');
    }

    public function shifts(): HasMany
    {
        return $this->hasMany(\App\Domain\Production\Models\Shift::class, 'factory_id');
    }

    // ── Accessors ─────────────────────────────────────────────

    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    public function getTimezoneAttribute(?string $value): string
    {
        // Ensure we always have a valid timezone, fallback to UTC
        return in_array($value, timezone_identifiers_list(), true) ? $value : 'UTC';
    }
}
