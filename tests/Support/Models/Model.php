<?php

namespace Lomkit\Rest\Tests\Support\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model as BaseModel;
use Lomkit\Rest\Tests\Support\Database\Factories\ModelFactory;

class Model extends BaseModel
{
    use HasFactory;

    protected static function newFactory()
    {
        return new ModelFactory;
    }

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

    public function hasOneOfManyRelation() {
        return $this->hasOne(HasOneOfManyRelation::class)->ofMany();
    }

    public function hasManyThroughRelation() {
        return $this->hasManyThrough(HasManyThroughRelation::class, HasManyRelation::class);
    }

    public function belongsToManyRelation() {
        return $this->belongsToMany(BelongsToManyRelation::class)
            ->as('belongs_to_many_pivot')
            ->withPivot('created_at', 'updated_at', 'number');
    }

    public function morphOneRelation() {
        return $this->morphOne(MorphOneRelation::class, 'morph_one_relation');
    }

    public function morphOneOfManyRelation() {
        return $this->morphOne(MorphOneOfManyRelation::class, 'morph_one_of_many_relation')->ofMany();
    }

    public function morphToRelation() {
        return $this->morphTo();
    }

    public function morphManyRelation() {
        return $this->morphMany(MorphManyRelation::class, 'morph_many_relation');
    }

    public function morphToManyRelation() {
        return $this->morphToMany(MorphToManyRelation::class, 'morphable')
            ->as('morph_to_many_pivot')
            ->withPivot('created_at', 'updated_at', 'number');
    }

    public function morphedByManyRelation() {
        return $this->morphedByMany(MorphedByManyRelation::class, 'inversable')
            ->as('morphed_by_many_pivot')
            ->withPivot('created_at', 'updated_at', 'number');
    }

    public function scopeNumbered(Builder $query, int $number = 0): void
    {
        $query->where('number', '>', $number);
    }
}