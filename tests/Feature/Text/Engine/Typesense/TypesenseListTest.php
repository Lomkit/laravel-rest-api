<?php

namespace Lomkit\Rest\Tests\Feature\Text\Engine\Typesense;

use Lomkit\Rest\Tests\Feature\Text\Engine\ListsAnIndex;
use PHPUnit\Framework\Attributes\Group;

#[Group('scout')]
#[Group('typesense')]
class TypesenseListTest extends TypesenseTestCase
{
    use ListsAnIndex;
}
