<?php

namespace Lomkit\Rest\Text\Engine;

use Lomkit\Rest\Exceptions\InvalidTextSearchException;
use Lomkit\Rest\Http\Resource;
use Lomkit\Rest\Text\Engine\Profiles\ComputesTotals;
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

    /**
     * @internal
     *
     * @return list<string>
     */
    public function ownedOptions(): array
    {
        $profile = $this->profile;

        return $profile instanceof ComputesTotals && $this->attributes->declaresTotals() ? [...$profile->ownedOptions(), ...$profile->totalsOptions()] : $profile->ownedOptions();
    }

    /** @internal */
    public function engine(): object
    {
        $engine = $this->model->engine();

        return is_object($engine) ? $engine : throw InvalidTextSearchException::engineNotAnObject($this->searchedClass(), $this->model->model()::class, get_debug_type($engine));
    }

    /** @internal */
    public function listOrder(): ListOrder
    {
        $profile = $this->profile;

        return new ListOrder($profile instanceof ListsWithoutText ? $profile : throw InvalidTextSearchException::unlistedEngine($this->searchedClass(), $profile->name()), $this->tieBreaker);
    }
}
