<?php

namespace Lomkit\Rest\Tests\Feature\Mutate\Checks\Stubs\RowJournal;

use Lomkit\Rest\Tests\Feature\Mutate\Checks\Stubs\ChecksBookResource;

class JournaledBookResource extends ChecksBookResource
{
    use JournalsRows;
}
