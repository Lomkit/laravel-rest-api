<?php

namespace Lomkit\Rest\Text\Engine;

use Lomkit\Rest\Exceptions\InvalidTextSearchException;
use Lomkit\Rest\Http\Resource;
use Lomkit\Rest\Text\Engine\Profiles\EngineProfile;
use Lomkit\Rest\Text\Engine\Profiles\ListsWithoutText;

/** @internal */
final readonly class TieBreaker
{
    private function __construct(public string $attribute) {}

    /** @param class-string<Resource> $resource */
    public static function declared(string $attribute, string $resource, EngineProfile $profile, bool $lists): self
    {
        if (! $lists) {
            throw InvalidTextSearchException::unlistedTieBreaker($resource, $attribute);
        }

        IndexAttributeRules::ensureName($attribute, IndexDeclarer::resource($resource));
        DeclaredAttributes::ensureSortable($attribute, $resource, $profile);

        if ($profile instanceof ListsWithoutText && $profile->listSortKeys() < 1) {
            throw InvalidTextSearchException::unsortedTieBreaker($resource, $attribute, $profile->name());
        }

        return new self($attribute);
    }
}
