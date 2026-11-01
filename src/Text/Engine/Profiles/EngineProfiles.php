<?php

namespace Lomkit\Rest\Text\Engine\Profiles;

use Laravel\Scout\Engines\Algolia3Engine;
use Laravel\Scout\Engines\Algolia4Engine;
use Laravel\Scout\Engines\CollectionEngine;
use Laravel\Scout\Engines\DatabaseEngine;
use Laravel\Scout\Engines\MeilisearchEngine;
use Laravel\Scout\Engines\NullEngine;
use Laravel\Scout\Engines\TurbopufferEngine;
use Laravel\Scout\Engines\TypesenseEngine;
use Lomkit\Rest\Exceptions\InvalidTextSearchException;

final class EngineProfiles
{
    public static function for(mixed $engine, string $resource): EngineProfile
    {
        return match (true) {
            $engine instanceof TurbopufferEngine => new TurbopufferProfile($engine),
            $engine instanceof TypesenseEngine => new TypesenseProfile($engine),
            $engine instanceof MeilisearchEngine => new MeilisearchProfile($engine),
            $engine instanceof Algolia3Engine, $engine instanceof Algolia4Engine => new AlgoliaProfile($engine),
            $engine instanceof DatabaseEngine => new DatabaseProfile($engine),
            $engine instanceof CollectionEngine => new CollectionProfile($engine),
            $engine instanceof NullEngine => new NullProfile($engine),
            default => throw InvalidTextSearchException::unsupportedEngine($resource, get_debug_type($engine)),
        };
    }
}
