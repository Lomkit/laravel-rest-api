<?php

namespace Lomkit\Rest\Http;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Lomkit\Rest\Actions\Action;
use Lomkit\Rest\Actions\ActionFields;
use Lomkit\Rest\Extensions\Contexts\WriteGuardContext;
use Lomkit\Rest\Extensions\Kinds\GuardsWrites;
use Lomkit\Rest\Http\Routing\Endpoint;
use Lomkit\Rest\Mutations\MutationNode;
use Lomkit\Rest\Mutations\ReachedResources;
use Lomkit\Rest\Mutations\WriteChecks;

/** @internal */
final readonly class WriteGuard
{
    private function __construct(
        private Resource $resource,
    ) {}

    public static function of(Resource $resource): ?self
    {
        return $resource->context()->extensions()->hasKind(GuardsWrites::class) ? new self($resource) : null;
    }

    public function reach(): ReachedResources
    {
        return new ReachedResources;
    }

    public function mutation(WriteChecks $checks): void
    {
        foreach ($checks->reached?->all() ?? [] as [$resource, $entries, $rows]) {
            $this->check($resource, Endpoint::Mutate, $entries, $rows);
        }
    }

    /** @param Collection<int, Model> $rows */
    public function rows(Endpoint $endpoint, Collection $rows): void
    {
        $this->check($this->resource, $endpoint, null, array_values($rows->all()));
    }

    /** @param list<Model> $rows */
    public function action(Action $action, ActionFields $fields, array $rows): void
    {
        $this->check($this->resource, Endpoint::Operate, null, $rows, $action, $fields);
    }

    /**
     * @param  list<MutationNode>|null  $entries
     * @param  list<Model>  $rows
     */
    private function check(Resource $resource, Endpoint $endpoint, ?array $entries, array $rows, ?Action $action = null, ?ActionFields $fields = null): void
    {
        $context = $this->resource->context();
        $registry = $context->extensions();
        $extensions = $registry->applicable($resource::class, GuardsWrites::class);

        if ($extensions === []) {
            return;
        }

        $user = $context->actingUser()->user;

        foreach ($extensions as $name => $extension) {
            $extension->guardWrite(new WriteGuardContext($name, $registry->classOf($name), $resource, $endpoint, $user, $entries, RowCopies::of($rows), $action === null ? null : clone $action, $fields));
        }
    }
}
