<?php

namespace Lomkit\Rest\Tests\Feature\Transactions\Stubs;

use Illuminate\Database\Eloquent\Attributes\Connection;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Attributes\Unguarded;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Lomkit\Rest\Tests\Fixtures\Archive\OpenArchivePolicy;

#[Connection('secondary')]
#[Table('documents')]
#[Unguarded]
#[UsePolicy(OpenArchivePolicy::class)]
class LinkedDocument extends Model
{
    public function links(): BelongsToMany
    {
        return $this->belongsToMany(self::class, 'document_links', 'document_id', 'linked_id');
    }
}
