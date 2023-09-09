<?php

namespace Lomkit\Rest\Tests\Support\Models;

use Illuminate\Database\Eloquent\Model as BaseModel;

class HasOneRelation extends BaseModel
{
    public function model()
    {
        return $this->belongsTo(Model::class);
    }
}
