<?php

namespace Lomkit\Rest\Events;

use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Lomkit\Rest\Actions\Action;
use Lomkit\Rest\Actions\ActionFields;
use Lomkit\Rest\Http\Resource;

final readonly class ActionOperated implements ShouldDispatchAfterCommit
{
    public function __construct(
        public Resource $resource,
        public Action $action,
        public ActionFields $fields,
        public int $impacted,
        public ?string $batch,
    ) {}
}
