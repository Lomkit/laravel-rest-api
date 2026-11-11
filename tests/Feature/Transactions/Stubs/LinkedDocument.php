<?php

namespace Lomkit\Rest\Tests\Feature\Transactions\Stubs;

use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Lomkit\Rest\Tests\Feature\Deletion\Stubs\SecondaryDocument;

class LinkedDocument extends SecondaryDocument
{
    public function links(): BelongsToMany
    {
        return $this->belongsToMany(self::class, 'document_links', 'document_id', 'linked_id');
    }
}
