<?php

namespace Lomkit\Rest\Validation;

use Closure;
use Lomkit\Rest\Http\Resource;
use Lomkit\Rest\Rules\OneOf;
use Lomkit\Rest\Validation\Search\SearchGrammar;

final class Vocabulary
{
    /** @var array<string, OneOf> */
    private array $lists = [];

    /** @param Closure(): list<string> $names */
    public function of(Resource $resource, SearchGrammar $mode, string $list, Closure $names): OneOf
    {
        return $this->lists[$resource::class."\0".$mode->backend()->name."\0".$list] ??= new OneOf($names());
    }
}
