<?php

namespace Lomkit\Rest\Contracts;

use Illuminate\Database\Eloquent\Model;
use Lomkit\Rest\Http\Resource;
use Lomkit\Rest\Relations\MorphColumns;
use Lomkit\Rest\Relations\MorphTarget;
use Lomkit\Rest\Relations\MorphTargets;

interface PolymorphicRelation
{
    public function targets(): MorphTargets;

    public function columns(): MorphColumns;

    public function resourceOf(MorphTarget $target): Resource;

    public function resourceFor(Model $model): ?Resource;
}
