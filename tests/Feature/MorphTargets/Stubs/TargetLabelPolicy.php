<?php

namespace Lomkit\Rest\Tests\Feature\MorphTargets\Stubs;

use Illuminate\Auth\Access\Response;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;
use Lomkit\Rest\Tests\Fixtures\Library\Book;
use Lomkit\Rest\Tests\Fixtures\Library\Label;
use Lomkit\Rest\Tests\Fixtures\Library\OpenLibraryPolicy;
use Lomkit\Rest\Tests\Fixtures\Library\Shelf;

class TargetLabelPolicy extends OpenLibraryPolicy
{
    /** @var list<string> */
    public static array $calls = [];

    /** @var list<string> */
    public static array $denied = [];

    public function attachBook(?Authenticatable $user, Label $label, Book $book): Response
    {
        return $this->record('attachBook', $book);
    }

    public function attachShelf(?Authenticatable $user, Label $label, Shelf $shelf): Response
    {
        return $this->record('attachShelf', $shelf);
    }

    public function detachShelf(?Authenticatable $user, Label $label, Shelf $shelf): Response
    {
        return $this->record('detachShelf', $shelf);
    }

    private function record(string $ability, Model $target): Response
    {
        self::$calls[] = $ability.' '.$target->getKey();

        return in_array($ability, self::$denied, true) ? Response::deny('This target is locked.') : Response::allow();
    }
}
