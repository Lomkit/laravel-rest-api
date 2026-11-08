<?php

namespace Lomkit\Rest\Tests\Feature\Search\Stubs\Festival;

use Illuminate\Database\Eloquent\Attributes\Unguarded;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Lomkit\Rest\Tests\Fixtures\Publishing\OpenPublishingPolicy;

#[Unguarded]
#[UsePolicy(OpenPublishingPolicy::class)]
class Stage extends Model
{
    public $incrementing = false;

    public $timestamps = false;

    protected $primaryKey = 'code';

    protected $keyType = 'string';

    public function notes(): MorphMany
    {
        return $this->morphMany(Note::class, 'notable');
    }
}
