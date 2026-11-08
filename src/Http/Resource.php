<?php

namespace Lomkit\Rest\Http;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Model;
use Lomkit\Rest\Actions\Actionable;
use Lomkit\Rest\Concerns\Authorizable;
use Lomkit\Rest\Concerns\PerformsModelOperations;
use Lomkit\Rest\Concerns\PerformsQueries;
use Lomkit\Rest\Concerns\Resource\Contextual;
use Lomkit\Rest\Concerns\Resource\DeclaresFields;
use Lomkit\Rest\Concerns\Resource\DeclaresScopes;
use Lomkit\Rest\Concerns\Resource\HasEndpointEvents;
use Lomkit\Rest\Concerns\Resource\HasResourceHooks;
use Lomkit\Rest\Concerns\Resource\Paginable;
use Lomkit\Rest\Concerns\Resource\Relationable;
use Lomkit\Rest\Concerns\Resource\Rulable;
use Lomkit\Rest\Concerns\Resource\SearchesText;
use Lomkit\Rest\Exceptions\InvalidResourceException;
use Lomkit\Rest\Http\Details\DetailsSelection;
use Lomkit\Rest\Http\Details\ResourceDetails;
use Lomkit\Rest\Instructions\Instructionable;
use Lomkit\Rest\Metrics\HasMetrics;
use Lomkit\Rest\Search\SearchContext;

class Resource implements \JsonSerializable
{
    use Actionable;
    use Authorizable;
    use Contextual;
    use DeclaresFields;
    use DeclaresScopes;
    use HasEndpointEvents;
    use HasMetrics;
    use HasResourceHooks;
    use Instructionable;
    use Paginable;
    use PerformsModelOperations;
    use PerformsQueries;
    use Relationable;
    use Rulable;
    use SearchesText;

    /** @var class-string<Model> */
    public static string $model;

    /** @var class-string<Response> */
    public static string $response = Response::class;

    public int $defaultLimit = 50;

    public int $includeLimit = 10;

    public ?int $maxTotal = null;

    /** @return class-string<Model> */
    public static function modelClass(): string
    {
        if (! isset(static::$model)) {
            throw InvalidResourceException::missingModel(static::class);
        }

        return static::$model;
    }

    public static function newModel(): Model
    {
        $model = static::modelClass();

        return new $model;
    }

    public static function newResponse(): Response
    {
        $response = static::$response;

        return new $response;
    }

    /** @return array<string, string> */
    public function defaultOrderBy(): array
    {
        return [
            static::newModel()->getKeyName() => 'desc',
        ];
    }

    public function isGatingEnabled(): bool
    {
        return true;
    }

    public function isAuthorizingEnabled(): bool
    {
        return true;
    }

    /**
     * @param  LengthAwarePaginator<int, Model>  $page
     * @return array<string, mixed>
     */
    public function searchMeta(SearchContext $search, LengthAwarePaginator $page): array
    {
        return [];
    }

    /** @return array<string, mixed> */
    public function detailsMeta(): array
    {
        return [];
    }

    public function versioned(): bool
    {
        return false;
    }

    /** @return array<string, mixed> */
    #[\Override]
    public function jsonSerialize(): mixed
    {
        return (new ResourceDetails($this, DetailsSelection::base()))->toArray();
    }
}
