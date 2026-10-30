<?php

namespace Lomkit\Rest\Tests\Feature\Query\Stubs;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Folder extends Model
{
    use HasUuids;

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id');
    }
}
