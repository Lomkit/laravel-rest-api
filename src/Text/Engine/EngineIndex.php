<?php

namespace Lomkit\Rest\Text\Engine;

use Lomkit\Rest\Http\Resource;
use Lomkit\Rest\Text\Engine\Profiles\EngineProfile;
use Lomkit\Rest\Text\TextCapabilities;
use Lomkit\Rest\Text\TextMode;

final readonly class EngineIndex
{
    /** @param class-string<Resource> $resource */
    public function __construct(
        public string $resource,
        public ScoutModel $model,
        public EngineProfile $profile,
        public IndexAttributes $attributes,
    ) {}

    public function capabilities(): TextCapabilities
    {
        return new TextCapabilities([], [TextMode::Words], true, $this->model->softDeletes(), engine: $this->attributes->grammar($this->profile));
    }
}
