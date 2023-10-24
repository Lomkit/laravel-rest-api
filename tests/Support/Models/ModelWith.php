<?php

namespace Lomkit\Rest\Tests\Support\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model as BaseModel;
use Lomkit\Rest\Tests\Support\Database\Factories\ModelFactory;

class ModelWith extends BaseModel
{
    use HasFactory;

    protected static function newFactory()
    {
        return new ModelFactory();
    }

    protected $with = [
        'belongsToRelation'
    ];

    protected $fillable = [
        'id',
    ];

    public function belongsToRelation()
    {
        return $this->belongsTo(BelongsToRelation::class);
    }
}
