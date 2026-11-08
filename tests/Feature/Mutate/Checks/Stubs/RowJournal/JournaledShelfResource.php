<?php

namespace Lomkit\Rest\Tests\Feature\Mutate\Checks\Stubs\RowJournal;

use Lomkit\Rest\Tests\Feature\Mutate\Checks\Stubs\ChecksShelfResource;

class JournaledShelfResource extends ChecksShelfResource
{
    use JournalsRows;
}
