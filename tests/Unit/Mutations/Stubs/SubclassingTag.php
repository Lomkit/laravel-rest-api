<?php

namespace Lomkit\Rest\Tests\Unit\Mutations\Stubs;

use Lomkit\Rest\Tests\Fixtures\Helpdesk\Tag;

class SubclassingTag extends Tag
{
    protected $table = 'tags';

    #[\Override]
    public function newFromBuilder($attributes = [], $connection = null): static
    {
        return $this instanceof SubclassedTag ? parent::newFromBuilder($attributes, $connection) : (new SubclassedTag)->newFromBuilder($attributes, $connection);
    }
}
