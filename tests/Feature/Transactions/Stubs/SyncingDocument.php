<?php

namespace Lomkit\Rest\Tests\Feature\Transactions\Stubs;

use Illuminate\Database\Eloquent\Attributes\Connection;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Attributes\Unguarded;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Lomkit\Rest\Tests\Fixtures\Archive\OpenArchivePolicy;

#[Connection('secondary')]
#[Table('documents')]
#[Unguarded]
#[UsePolicy(OpenArchivePolicy::class)]
class SyncingDocument extends NestedDocument
{
    public function detachedChildren(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id');
    }
}
