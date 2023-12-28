<?php

namespace Lomkit\Rest\Tests\Support\Models;

use Illuminate\Database\Eloquent\Model as BaseModel;

class BelongsToManyRelation extends BaseModel
{
    public function models()
    {
        return $this->belongsToMany(\Lomkit\Rest\Tests\Support\Models\Model::class);
    }

    public function model()
    {
        return $this->belongsTo(Model::class);
    }
}
