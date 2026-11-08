<?php

namespace Lomkit\Rest\Tests\Feature\Mutate\Checks\Stubs\RowJournal;

use Lomkit\Rest\Tests\Feature\Mutate\Checks\Stubs\ChecksLoanResource;

class JournaledLoanResource extends ChecksLoanResource
{
    use JournalsRows;
}
