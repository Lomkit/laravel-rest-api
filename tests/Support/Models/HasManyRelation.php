<?php

namespace Lomkit\Rest\Tests\Support\Models;

use Illuminate\Database\Eloquent\Model;

class HasManyRelation extends Model
{
    public function model() {
        return $this->belongsTo(\Lomkit\Rest\Tests\Support\Models\Model::class);
    }
}