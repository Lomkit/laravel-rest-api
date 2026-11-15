<?php

namespace Lomkit\Rest\Http;

use Closure;
use Illuminate\Contracts\Auth\Authenticatable;
use Lomkit\Rest\Extensions\Contexts\ReadObservationContext;
use Lomkit\Rest\Extensions\Extension;
use Lomkit\Rest\Extensions\Kinds\ObservesReads;
use Lomkit\Rest\Http\Routing\Endpoint;
use Lomkit\Rest\Metrics\Metric;
use Lomkit\Rest\Search\Plan\Columns\StrictColumns;
use Lomkit\Rest\Search\Run\SearchedPage;

/** @internal */
final readonly class ReadObservers
{
    /** @param non-empty-array<string, Extension&ObservesReads> $extensions */
    private function __construct(
        private Resource $resource,
        private array $extensions,
    ) {}

    public static function of(Resource $resource): ?self
    {
        $extensions = $resource->context()->extensions()->applicable($resource::class, ObservesReads::class);

        if ($extensions === []) {
            return null;
        }

        return new self($resource, $extensions);
    }

    public function searched(SearchedPage $page): void
    {
        $rows = $page->rows;

        StrictColumns::of($page->plan)->run(fn () => $this->notify(fn (?Authenticatable $user): ReadObservationContext => new ReadObservationContext(
            $this->resource, Endpoint::Search, $user, RowCopies::of($rows->items()), $rows->total(), $rows->currentPage(), $rows->perPage(),
        )));
    }

    public function metric(Metric $metric, string $json): void
    {
        $result = self::decoded($json);

        $this->notify(fn (?Authenticatable $user): ReadObservationContext => new ReadObservationContext(
            $this->resource, Endpoint::Metric, $user, metric: $metric, result: $result,
        ));
    }

    /** @param array<string, mixed> $details */
    public function details(array $details): void
    {
        $decoded = self::decoded(json_encode($details, JSON_PRESERVE_ZERO_FRACTION | JSON_THROW_ON_ERROR));

        $this->notify(fn (?Authenticatable $user): ReadObservationContext => new ReadObservationContext(
            $this->resource, Endpoint::Details, $user, details: $decoded,
        ));
    }

    /** @param Closure(?Authenticatable): ReadObservationContext $context */
    private function notify(Closure $context): void
    {
        $user = $this->resource->context()->actingUser()->user;

        foreach ($this->extensions as $extension) {
            $extension->observeRead($context($user));
        }
    }

    /** @return array<array-key, mixed> */
    private static function decoded(string $json): array
    {
        $decoded = json_decode($json, true, 512, JSON_THROW_ON_ERROR);

        return is_array($decoded) ? $decoded : [];
    }
}
