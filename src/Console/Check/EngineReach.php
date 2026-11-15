<?php

namespace Lomkit\Rest\Console\Check;

use Lomkit\Rest\Exceptions\InvalidTextSearchException;
use Lomkit\Rest\Http\Resource;
use Lomkit\Rest\Http\RestContext;
use Lomkit\Rest\Query\ExtensionRestrictions;
use Lomkit\Rest\Text\Engine\Profiles\AppliesRowRestrictions;
use Lomkit\Rest\Text\Engine\Profiles\EngineProfile;
use Lomkit\Rest\Text\Engine\Profiles\EngineProfiles;
use Lomkit\Rest\Text\EngineTextSearch;
use Lomkit\Rest\Text\ScoutEngine;
use Lomkit\Rest\Text\TextQuery;
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
            $profile = $this->profile($resource);
        } catch (Throwable $exception) {
            return InvalidTextSearchException::unresolvedEngine($resource::class, $name, $class, $exception)->getMessage();
        }

        return $profile === null || $profile instanceof AppliesRowRestrictions ? null : InvalidTextSearchException::unrestrictedEngine($resource::class, $name, $class, $profile->name())->getMessage();
    }

    private function profile(Resource $resource): ?EngineProfile
    {
        $text = $resource->text();

        return match (true) {
            $text instanceof ScoutEngine => EngineProfiles::forTarget(TextTarget::of($resource)),
            $text instanceof EngineTextSearch => $text->query(new TextQuery('rest-check'), TextTarget::of($resource))->profile(),
            default => null,
        };
    }
}
