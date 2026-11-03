<?php

namespace Lomkit\Rest\Tests\Feature\Search\Stubs;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Lomkit\Rest\Tests\Fixtures\Library\Label;

class ShelvedLabel extends Label
{
    /** @return MorphTo<Model, $this> */
    public function shelf(): MorphTo
    {
        return $this->morphTo('labelable', null, null, 'code');
    }

    /** @return SiblingRelation<Label, $this> */
    public function siblings(): SiblingRelation
    {
        return new SiblingRelation((new Label)->newQuery(), $this);
    }
}
