<?php

declare(strict_types=1);

namespace App\Domain\Shared\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * BaseModel
 *
 * Shared Eloquent base for all domain models.
 * Sets consistent casting, guards ID from mass-assignment,
 * and provides extension hooks.
 */
abstract class BaseModel extends Model
{
    /**
     * Prevent mass-assignment of the primary key.
     * Child models declare $fillable explicitly (no $guarded = []).
     */
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }
}
