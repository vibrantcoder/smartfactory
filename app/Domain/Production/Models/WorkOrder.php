<?php

declare(strict_types=1);

namespace App\Domain\Production\Models;

use App\Domain\Shared\Models\BaseModel;
use App\Domain\Shared\Traits\BelongsToFactory;
use App\Domain\Shared\Traits\HasFactoryScope;
use App\Models\User;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * WorkOrder — Customer Manufacturing Order (ISO 9001 compliant)
 *
 * Represents a customer's order to manufacture a part.
 * Drives production planning: total_planned_qty = order_qty + excess_qty.
 *
 * STATUS WORKFLOW (ISO 9001 §8.1 Operational planning):
 *   draft → confirmed → released → in_progress → completed
 *                                ↘ cancelled
 *
 * NUMBERING: WO-YYYYMM-XXXXXX (sequential per factory per month)
 *
 * @property int         $id
 * @property string      $wo_number
 * @property int         $factory_id
 * @property int         $customer_id
 * @property int         $part_id
 * @property int         $order_qty
 * @property int         $excess_qty
 * @property int         $total_planned_qty   GENERATED ALWAYS AS (order_qty + excess_qty)
 * @property string      $expected_delivery_date
 * @property string|null $planned_start_date
 * @property string      $priority            low|medium|high|urgent
 * @property string      $status              draft|confirmed|released|in_progress|completed|cancelled
 * @property string|null $notes
 * @property int|null    $created_by
 * @property string|null $confirmed_at
 * @property string|null $released_at
 * @property string|null $completed_at
 */
class WorkOrder extends BaseModel
{
    use HasFactoryScope, BelongsToFactory;

    protected $table = 'work_orders';

    protected $fillable = [
        'wo_number',
        'factory_id',
        'customer_id',
        'part_id',
        'order_qty',
        'excess_qty',
        'expected_delivery_date',
        'planned_start_date',
        'priority',
        'status',
        'notes',
        'created_by',
        'confirmed_at',
        'released_at',
        'completed_at',
    ];

    protected function casts(): array
    {
        return [
            'order_qty'              => 'integer',
            'excess_qty'             => 'integer',
            'total_planned_qty'      => 'integer',
            'expected_delivery_date' => 'date',
            'planned_start_date'     => 'date',
            'confirmed_at'           => 'datetime',
            'released_at'            => 'datetime',
            'completed_at'           => 'datetime',
        ];
    }

    // ── Relationships ─────────────────────────────────────────

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function part(): BelongsTo
    {
        return $this->belongsTo(Part::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    // ── Status helpers ────────────────────────────────────────

    public function isDraft(): bool       { return $this->status === 'draft'; }
    public function isConfirmed(): bool   { return $this->status === 'confirmed'; }
    public function isReleased(): bool    { return $this->status === 'released'; }
    public function isInProgress(): bool  { return $this->status === 'in_progress'; }
    public function isCompleted(): bool   { return $this->status === 'completed'; }
    public function isCancelled(): bool   { return $this->status === 'cancelled'; }
    public function isImmutable(): bool   { return in_array($this->status, ['completed', 'cancelled']); }

    // ── Auto WO number generation ─────────────────────────────

    /**
     * Generate the next WO number for a given factory.
     * Format: WO-YYYYMM-XXXXXX (6-digit zero-padded sequential per factory/month)
     */
    public static function generateWoNumber(int $factoryId): string
    {
        $prefix = 'WO-' . now()->format('Ym') . '-';

        $last = static::withoutGlobalScopes()
            ->where('factory_id', $factoryId)
            ->where('wo_number', 'like', $prefix . '%')
            ->orderByDesc('wo_number')
            ->value('wo_number');

        $next = $last ? ((int) substr($last, -6)) + 1 : 1;

        return $prefix . str_pad((string) $next, 6, '0', STR_PAD_LEFT);
    }
}
