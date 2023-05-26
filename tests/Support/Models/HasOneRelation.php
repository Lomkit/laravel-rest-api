<?php

namespace Lomkit\Rest\Tests\Support\Models;

class HasOneRelation extends Model
{
    public function model() {
        return $this->belongsTo(Model::class);
    }
}