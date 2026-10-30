<?php

namespace Lomkit\Rest\Tests\Feature\Mutate\Stubs;

use Illuminate\Database\Eloquent\Attributes\Connection;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Attributes\Unguarded;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Model;
use Lomkit\Rest\Tests\Fixtures\Library\OpenLibraryPolicy;

#[Connection('archive')]
#[Table('shelves')]
#[Unguarded]
#[UsePolicy(OpenLibraryPolicy::class)]
class ArchivedShelf extends Model {}
