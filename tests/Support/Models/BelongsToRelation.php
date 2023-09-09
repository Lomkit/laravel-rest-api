<?php

namespace Lomkit\Rest\Tests\Support\Models;

use Illuminate\Database\Eloquent\Model as BaseModel;

class BelongsToRelation extends BaseModel
{
    public function models()
    {
        return $this->hasMany(Model::class);
    }
}
