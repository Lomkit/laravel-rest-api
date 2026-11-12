<?php

namespace Lomkit\Rest\Tests\Feature\Transactions\Stubs;

use Illuminate\Database\Eloquent\Relations\HasMany;

class SyncingDocument extends NestedDocument
{
    public function detachedChildren(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id');
    }
}
