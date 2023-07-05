<?php

namespace Lomkit\Rest\Tests\Support\Models;

use Illuminate\Database\Eloquent\Model;

class BelongsToManyRelation extends Model
{
    public function models() {
        return $this->belongsToMany(\Lomkit\Rest\Tests\Support\Models\Model::class);
    }
}