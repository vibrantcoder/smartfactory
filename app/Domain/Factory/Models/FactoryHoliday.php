<?php

declare(strict_types=1);

namespace App\Domain\Factory\Models;

use App\Domain\Shared\Models\BaseModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * FactoryHoliday
 *
 * Stores per-factory holidays that appear as red days in the production calendar.
 *
 * @property int    $id
 * @property int    $factory_id
 * @property string $holiday_date  Y-m-d
 * @property string $name          e.g. "Republic Day"
 */
class FactoryHoliday extends BaseModel
{
    protected $table = 'factory_holidays';

    protected $fillable = [
        'factory_id',
        'holiday_date',
        'name',
    ];

    protected function casts(): array
    {
        return [
            ...parent::casts(),
            'holiday_date' => 'date:Y-m-d',
        ];
    }

    public function factory(): BelongsTo
    {
        return $this->belongsTo(Factory::class, 'factory_id');
    }
}
