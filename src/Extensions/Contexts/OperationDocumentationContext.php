<?php

namespace Lomkit\Rest\Extensions\Contexts;

use Dedoc\Scramble\Support\Generator\Types\Type;
use Lomkit\Rest\Actions\Action;
use Lomkit\Rest\Extensions\Extension;
use Lomkit\Rest\Http\Resource;
use Lomkit\Rest\Http\Routing\Endpoint;
use Lomkit\Rest\Metrics\Metric;
use Lomkit\Rest\Scramble\ExtensionResponses;

final readonly class OperationDocumentationContext
{
    /**
     * @internal
     *
     * @param  class-string<Extension>  $class
     * @param  class-string<Resource>  $resource
     */
    public function __construct(
        private string $extension,
        private string $class,
        public string $resource,
        public Endpoint $endpoint,
        public Action|Metric|null $declaration,
        public bool $authenticates,
        private ExtensionResponses $responses,
    ) {}

    public function response(int $status, string $name, string $description, Type $body): void
    {
        $this->responses->add($this->extension, $this->class, $status, $name, $description, $body);
    }

    public function documents(int $status): bool
    {
        return $this->responses->documents($status);
    }
}
