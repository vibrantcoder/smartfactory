<?php

declare(strict_types=1);

namespace App\Domain\Shared\Traits;

use App\Domain\Shared\Scopes\FactoryScope;

/**
 * HasFactoryScope
 *
 * Boots the FactoryScope global Eloquent scope on any model.
 * Separate from BelongsToFactory so the scope can be applied
 * to models that reference factory indirectly (e.g., via machine_id).
 *
 * Combine both traits on factory-owned models:
 *   use HasFactoryScope, BelongsToFactory;
 */
trait HasFactoryScope
{
    public static function bootHasFactoryScope(): void
    {
        static::addGlobalScope(new FactoryScope());
    }

    /**
     * Initialise — ensures the FactoryScope is extended with macros
     * even on models that are queried before any scope fires.
     */
    public function initializeHasFactoryScope(): void
    {
        // No instance-level initialisation needed;
        // macros are registered via FactoryScope::extend().
    }
}
