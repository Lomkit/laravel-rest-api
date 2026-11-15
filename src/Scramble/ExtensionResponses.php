<?php

namespace Lomkit\Rest\Scramble;

use Dedoc\Scramble\Support\Generator\Operation;
use Dedoc\Scramble\Support\Generator\Reference;
use Dedoc\Scramble\Support\Generator\Response;
use Dedoc\Scramble\Support\Generator\Types\Type;
use Lomkit\Rest\Exceptions\InvalidDocumentationExtensionException;
use Lomkit\Rest\Extensions\Contexts\OperationDocumentationContext;
use Lomkit\Rest\Extensions\Kinds\DocumentsExtension;

/** @internal */
final class ExtensionResponses
{
    private const string NAME = '/^[A-Za-z][A-Za-z0-9]*\z/';

    private const string PACKAGE = 'the package';

    private bool $added = false;

    /** @param array<int, string> $owners */
    private function __construct(
        private readonly Operation $operation,
        private readonly ComponentRegistry $components,
        private readonly string $route,
        private array $owners,
    ) {}

    public static function document(Operation $operation, DocumentationContext $context): void
    {
        $resource = $context->resource;
        $extensions = $resource->context()->extensions()->applicable($resource::class, DocumentsExtension::class);

        if ($extensions === []) {
            return;
        }

        ksort($extensions);
        $responses = new self($operation, $context->components, $context->route->uri(), self::owners($operation));
        $declaration = $context->declaration();
        $authenticates = $context->authenticates();

        foreach ($extensions as $name => $extension) {
            $extension->documentOperation(new OperationDocumentationContext($name, $extension::class, $resource::class, $context->endpoint, $declaration, $authenticates, $responses));
        }

        $responses->sort();
        $context->components->ensureResponsesUnchanged();
    }

    public function add(string $extension, string $class, int $status, string $name, string $description, Type $body): void
    {
        if ($status < 400 || $status > 599) {
            throw InvalidDocumentationExtensionException::statusOutOfRange($extension, $class, $this->route, $status);
        }

        if (preg_match(self::NAME, $name) !== 1) {
            throw InvalidDocumentationExtensionException::invalidResponseName($extension, $class, $name);
        }

        if (isset($this->owners[$status])) {
            throw InvalidDocumentationExtensionException::statusTaken($extension, $class, $this->route, $status, $this->owners[$status]);
        }

        $this->operation->addResponse($this->components->response("{$extension}.{$name}", Schemas::response($status, $description, $body), $class));
        $this->owners[$status] = "the [{$extension}] extension {$class}";
        $this->added = true;
    }

    public function documents(int $status): bool
    {
        return isset($this->owners[$status]);
    }

    private function sort(): void
    {
        if (! $this->added) {
            return;
        }

        $responses = $this->operation->responses ?? [];
        usort($responses, fn (mixed $a, mixed $b): int => self::status($a) <=> self::status($b));
        $this->operation->responses = $responses;
    }

    /** @return array<int, string> */
    private static function owners(Operation $operation): array
    {
        $owners = [];

        foreach ($operation->responses ?? [] as $response) {
            $status = self::status($response);

            if ($status !== 0) {
                $owners[$status] = self::PACKAGE;
            }
        }

        return $owners;
    }

    private static function status(mixed $response): int
    {
        if ($response instanceof Reference) {
            $response = $response->resolve();
        }

        return $response instanceof Response ? (int) $response->code : 0;
    }
}
