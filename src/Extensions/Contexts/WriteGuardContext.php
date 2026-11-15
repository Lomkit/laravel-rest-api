<?php

namespace Lomkit\Rest\Extensions\Contexts;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;
use Lomkit\Rest\Actions\Action;
use Lomkit\Rest\Actions\ActionFields;
use Lomkit\Rest\Exceptions\InvalidWriteExtensionException;
use Lomkit\Rest\Exceptions\WriteRefused;
use Lomkit\Rest\Extensions\Extension;
use Lomkit\Rest\Http\Resource;
use Lomkit\Rest\Http\Routing\Endpoint;
use Lomkit\Rest\Mutations\MutationNode;
use Symfony\Component\HttpFoundation\Response;

final readonly class WriteGuardContext
{
    private const array RESERVED_STATUSES = [Response::HTTP_CONFLICT, Response::HTTP_UNPROCESSABLE_ENTITY, Response::HTTP_PRECONDITION_REQUIRED];

    /**
     * @internal
     *
     * @param  class-string<Extension>  $class
     * @param  list<MutationNode>|null  $entries
     * @param  list<Model>  $rows
     */
    public function __construct(
        private string $extension,
        private string $class,
        public Resource $resource,
        public Endpoint $endpoint,
        public ?Authenticatable $user,
        public ?array $entries = null,
        public array $rows = [],
        public ?Action $action = null,
        public ?ActionFields $fields = null,
    ) {}

    /** @param array<string, string> $headers */
    public function refuse(string $message, int $status = 403, array $headers = []): never
    {
        if (in_array($status, self::RESERVED_STATUSES, true)) {
            throw InvalidWriteExtensionException::reservedRefusalStatus($this->extension, $this->class, $this->resource::class, $this->endpoint, $status);
        }

        if ($status !== Response::HTTP_SERVICE_UNAVAILABLE && ($status < 400 || $status > 499)) {
            throw InvalidWriteExtensionException::refusalStatus($this->extension, $this->class, $this->resource::class, $this->endpoint, $status);
        }

        throw new WriteRefused($this->extension, $message, $status, $headers);
    }
}
