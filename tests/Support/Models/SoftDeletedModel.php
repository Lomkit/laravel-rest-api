<?php

namespace Lomkit\Rest\Tests\Support\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Lomkit\Rest\Tests\Support\Database\Factories\SoftDeletedModelFactory;

class SoftDeletedModel extends Model
{
    use SoftDeletes;
    use HasFactory;

    protected static function newFactory()
    {
        return SoftDeletedModelFactory::new();
    }

    public function belongsToManyRelation()
    {
        return $this->belongsToMany(BelongsToManyRelation::class, 'belongs_to_many_relation_soft_deleted_model', 'soft_deleted_model_id', 'belongs_to_many_relation_id')
            ->as('belongs_to_many_pivot')
            ->withPivot('created_at', 'updated_at', 'number');
    }
}
