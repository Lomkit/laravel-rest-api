<?php

namespace Lomkit\Rest\Search\Plan;

use Illuminate\Support\Str;
use Lomkit\Rest\Search\IncludeNode;
use Lomkit\Rest\Search\MorphIncludeNode;

/** @internal */
final readonly class RenderedLevel
{
    /** @param list<string> $relations */
    private function __construct(public array $relations) {}

    /** @param list<IncludeNode|MorphIncludeNode> $includes */
    public static function of(array $includes): self
    {
        $named = array_values(array_filter($includes, static fn (IncludeNode|MorphIncludeNode $include): bool => $include->alias === null));
        $aliases = array_filter(array_map(static fn (IncludeNode|MorphIncludeNode $include): ?string => $include->alias, $includes), static fn (?string $alias): bool => $alias !== null);

        return new self(array_values(array_unique([...self::names($named), ...$aliases])));
    }

    /**
     * @param  list<IncludeNode|MorphIncludeNode>  $includes
     * @return list<string>
     */
    public static function names(array $includes): array
    {
        return array_map(static fn (IncludeNode|MorphIncludeNode $include): string => Str::before($include->relation, '.'), $includes);
    }
}
