<?php

namespace Lomkit\Rest\Events;

use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Lomkit\Rest\Actions\Action;
use Lomkit\Rest\Actions\ActionFields;
use Lomkit\Rest\Http\Resource;

final readonly class ActionOperated implements ShouldDispatchAfterCommit
{
    /**
     * @param  class-string<Resource>  $resource
     * @param  class-string<Action>  $action
     * @param  array<string, mixed>  $fields
     */
    public function __construct(
        public string $resource,
        public string $action,
        public string $uriKey,
        public array $fields,
        public int $impacted,
        public ?string $batch,
    ) {}

    public static function of(Resource $resource, Action $action, ActionFields $fields, int $impacted, ?string $batch): self
    {
        return new self($resource::class, $action::class, $action->uriKey(), $fields->toArray(), $impacted, $batch);
    }
}
