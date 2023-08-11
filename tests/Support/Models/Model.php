<?php

namespace Lomkit\Rest\Tests\Support\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model as BaseModel;

class Model extends BaseModel
{
    protected $fillable = [
        'id'
    ];

    public function belongsToRelation() {
        return $this->belongsTo(BelongsToRelation::class);
    }

    public function hasOneThroughRelation() {
        return $this->hasOneThrough(HasOneThroughRelation::class, HasOneRelation::class);
    }

    public function hasOneRelation() {
        return $this->hasOne(HasOneRelation::class);
    }

    public function hasManyRelation() {
        return $this->hasMany(HasManyRelation::class);
    }

    public function hasManyThroughRelation() {
        return $this->hasManyThrough(HasManyThroughRelation::class, HasManyRelation::class);
    }

    public function belongsToManyRelation() {
        return $this->belongsToMany(BelongsToManyRelation::class)
            ->as('belongs_to_many_pivot')
            ->withPivot('created_at', 'updated_at');
    }

    public function scopeNumbered(Builder $query, int $number = 0): void
    {
        $query->where('number', '>', $number);
    }
}