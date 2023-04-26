<?php

namespace Lomkit\Rest\Tests\Support\Models;

use Illuminate\Database\Eloquent\Model as BaseModel;

class Model extends BaseModel
{
    public function belongsToRelation() {
        return $this->belongsTo(BelongsToRelation::class);
    }

    public function hasOneRelation() {
        return $this->hasOne(HasOneRelation::class);
    }

    public function hasManyRelation() {
        return $this->hasMany(HasManyRelation::class);
    }

    public function belongsToManyRelation() {
        return $this->belongsToMany(BelongsToRelation::class);
    }
}