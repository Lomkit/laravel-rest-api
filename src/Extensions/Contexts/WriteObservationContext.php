<?php

namespace Lomkit\Rest\Extensions\Contexts;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;
use Lomkit\Rest\Actions\Action;
use Lomkit\Rest\Actions\ActionFields;
use Lomkit\Rest\Http\Resource;
use Lomkit\Rest\Http\Routing\Endpoint;
use Lomkit\Rest\Mutations\WrittenRow;

final readonly class WriteObservationContext
{
    /**
     * @internal
     *
     * @param  list<array<string, mixed>>|null  $operations
     * @param  list<WrittenRow>|null  $written
     * @param  list<Model>|null  $rows
     */
    public function __construct(
        public Resource $resource,
        public Endpoint $endpoint,
        public ?Authenticatable $user,
        public ?array $operations = null,
        public ?array $written = null,
        public ?array $rows = null,
        public ?Action $action = null,
        public ?ActionFields $fields = null,
        public ?int $impacted = null,
    ) {}
}
