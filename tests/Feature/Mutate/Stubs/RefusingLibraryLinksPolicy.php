<?php

namespace Lomkit\Rest\Tests\Feature\Mutate\Stubs;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;
use Lomkit\Rest\Tests\Fixtures\Library\OpenLibraryPolicy;

class RefusingLibraryLinksPolicy extends OpenLibraryPolicy
{
    public function attachLoan(?Authenticatable $user, Model $book, Model $loan): bool
    {
        return false;
    }

    public function detachLoan(?Authenticatable $user, Model $book, Model $loan): bool
    {
        return false;
    }

    public function attachLabel(?Authenticatable $user, Model $book, Model $label): bool
    {
        return false;
    }

    public function detachLabel(?Authenticatable $user, Model $book, Model $label): bool
    {
        return false;
    }

    public function attachBook(?Authenticatable $user, Model $loan, Model $book): bool
    {
        return false;
    }

    public function detachBook(?Authenticatable $user, Model $loan, Model $book): bool
    {
        return false;
    }
}
