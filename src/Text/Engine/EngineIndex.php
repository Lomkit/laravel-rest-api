<?php

namespace Lomkit\Rest\Text\Engine;

use Lomkit\Rest\Exceptions\InvalidTextSearchException;
use Lomkit\Rest\Http\Resource;
use Lomkit\Rest\Text\Engine\Profiles\EngineProfile;
use Lomkit\Rest\Text\Engine\Profiles\ListsWithoutText;
use Lomkit\Rest\Text\TextCapabilities;
use Lomkit\Rest\Text\TextMode;

final readonly class EngineIndex
{
    /** @param class-string<Resource>|null $resource */
    public function __construct(
        public ScoutModel $model,
        public EngineProfile $profile,
        public IndexAttributes $attributes,
        public ?string $resource = null,
        public bool $lists = false,
        public ?TieBreaker $tieBreaker = null,
    ) {}

    public function capabilities(): TextCapabilities
    {
        return new TextCapabilities([], [TextMode::Words], true, $this->model->softDeletes(), engine: $this->attributes->grammar($this->profile, $this->lists ? $this->listOrder() : null));
    }

    public function searchedClass(): string
    {
        return $this->resource ?? $this->model->model()::class;
    }

    /** @internal */
    public function listOrder(): ListOrder
    {
        $profile = $this->profile;

        return new ListOrder($profile instanceof ListsWithoutText ? $profile : throw InvalidTextSearchException::unlistedEngine($this->searchedClass(), $profile->name()), $this->tieBreaker);
    }
}
