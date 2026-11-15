<?php

namespace Lomkit\Rest\Http;

use Closure;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Model;
use Lomkit\Rest\Extensions\Contexts\ReadObservationContext;
use Lomkit\Rest\Extensions\Extension;
use Lomkit\Rest\Extensions\Kinds\ObservesReads;
use Lomkit\Rest\Http\Routing\Endpoint;
use Lomkit\Rest\Metrics\Metric;
use Lomkit\Rest\Search\Plan\Columns\StrictColumns;

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

        ksort($extensions);

        return new self($resource, $extensions);
    }

    /** @param LengthAwarePaginator<int, Model> $page */
    public function searched(LengthAwarePaginator $page, StrictColumns $strict): void
    {
        $strict->run(fn () => $this->notify(fn (?Authenticatable $user): ReadObservationContext => new ReadObservationContext(
            $this->resource, Endpoint::Search, $user, RowCopies::of($page->items()), $page->total(), $page->currentPage(), $page->perPage(),
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
