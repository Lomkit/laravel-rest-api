<?php

namespace Lomkit\Rest\Console\Check;

use Lomkit\Rest\Exceptions\InvalidTextSearchException;
use Lomkit\Rest\Http\Resource;
use Lomkit\Rest\Http\RestContext;
use Lomkit\Rest\Query\ExtensionRestrictions;
use Lomkit\Rest\Text\Engine\Profiles\EngineProfiles;
use Lomkit\Rest\Text\ScoutEngine;
use Lomkit\Rest\Text\TextTarget;
use Throwable;

final class EngineReach
{
    private ?bool $built = null;

    public function __construct(private readonly RestContext $context) {}

    public function problem(Resource $resource): ?string
    {
        if (! ($this->built ??= BuiltExtensions::in($this->context))) {
            return null;
        }

        $unreached = ExtensionRestrictions::of($resource)?->unreached() ?? [];
        $name = array_key_first($unreached);

        if ($name === null) {
            return null;
        }

        $class = $unreached[$name]::class;

        try {
            if (! $resource->text() instanceof ScoutEngine) {
                return null;
            }

            $profile = EngineProfiles::forTarget(TextTarget::of($resource));
        } catch (Throwable $exception) {
            return $resource::class.": the [{$name}] extension {$class} restricts its rows in SQL but does not implement RestrictsEngineQueries, and rest:check cannot resolve the text search or the Scout engine of the resource ({$exception->getMessage()}). A text search of ".$resource::class.' is refused on an engine that does not apply SQL restrictions.';
        }

        return $profile->appliesRowRestrictions() ? null : InvalidTextSearchException::unrestrictedEngine($resource::class, $name, $class, $profile->name())->getMessage();
    }
}
