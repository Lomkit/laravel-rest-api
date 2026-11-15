<?php

namespace Lomkit\Rest\Http;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Lomkit\Rest\Actions\Action;
use Lomkit\Rest\Actions\ActionFields;
use Lomkit\Rest\Extensions\Contexts\WriteGuardContext;
use Lomkit\Rest\Extensions\Extension;
use Lomkit\Rest\Extensions\Kinds\GuardsWrites;
use Lomkit\Rest\Http\Routing\Endpoint;
use Lomkit\Rest\Mutations\MutationNode;

/** @internal */
final readonly class WriteGuard
{
    /** @param non-empty-array<string, Extension&GuardsWrites> $extensions */
    private function __construct(
        private Resource $resource,
        private array $extensions,
    ) {}

    public static function of(Resource $resource): ?self
    {
        $extensions = $resource->context()->extensions()->applicable($resource::class, GuardsWrites::class);

        if ($extensions === []) {
            return null;
        }

        ksort($extensions);

        return new self($resource, $extensions);
    }

    /**
     * @param  list<MutationNode>  $entries
     * @param  list<Model>  $rows
     */
    public function mutation(array $entries, array $rows): void
    {
        $this->check(Endpoint::Mutate, $entries, $rows);
    }

    /** @param Collection<int, Model> $rows */
    public function rows(Endpoint $endpoint, Collection $rows): void
    {
        $this->check($endpoint, null, array_values($rows->all()));
    }

    /** @param list<Model>|null $rows */
    public function action(Action $action, ActionFields $fields, ?array $rows): void
    {
        $this->check(Endpoint::Operate, null, $rows, $action, $fields);
    }

    /**
     * @param  list<MutationNode>|null  $entries
     * @param  list<Model>|null  $rows
     */
    private function check(Endpoint $endpoint, ?array $entries, ?array $rows, ?Action $action = null, ?ActionFields $fields = null): void
    {
        $user = $this->resource->context()->actingUser()->user;

        foreach ($this->extensions as $name => $extension) {
            $extension->guardWrite(new WriteGuardContext($name, $extension::class, $this->resource, $endpoint, $user, $entries, $rows === null ? null : RowCopies::of($rows), $action, $fields));
        }
    }
}
