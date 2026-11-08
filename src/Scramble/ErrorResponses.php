<?php

namespace Lomkit\Rest\Scramble;

use Dedoc\Scramble\Support\Generator\Combined\AnyOf;
use Dedoc\Scramble\Support\Generator\Operation;
use Dedoc\Scramble\Support\Generator\Reference;
use Dedoc\Scramble\Support\Generator\Response;
use Dedoc\Scramble\Support\Generator\Schema;
use Dedoc\Scramble\Support\Generator\Types\IntegerType;
use Dedoc\Scramble\Support\Generator\Types\MixedType;
use Dedoc\Scramble\Support\Generator\Types\ObjectType;
use Dedoc\Scramble\Support\Generator\Types\StringType;
use Dedoc\Scramble\Support\Generator\Types\Type;
use Lomkit\Rest\Actions\Action;
use Lomkit\Rest\Http\Routing\Endpoint;
use Lomkit\Rest\Scramble\Mutate\VersionReach;

final readonly class ErrorResponses
{
    public function __construct(
        private ComponentRegistry $components,
    ) {}

    public function attach(Operation $operation, DocumentationContext $context): void
    {
        $authenticated = $context->authenticates();

        if ($authenticated) {
            $operation->addResponse($this->shared('RestUnauthenticated', 401, 'Unauthenticated', self::message()));
        }

        $operation->addResponse($this->shared('RestForbidden', 403, 'Forbidden', self::forbidden()));
        $operation->addResponse($this->shared('RestNotFound', 404, 'Not found', self::message()));

        if ($context->endpoint === Endpoint::Mutate && VersionReach::of($context->resource)) {
            $operation->addResponse($this->shared('RestConflict', 409, 'Conflict', self::conflict()));
        }

        if ($context->endpoint !== Endpoint::Batch) {
            $operation->addResponse($this->shared('RestValidationFailed', 422, 'Validation error', self::validation()));
        }

        if (self::confirms($context)) {
            $operation->addResponse($this->shared('RestConfirmationRequired', 428, 'Confirmation required: send the same body again with the token in confirmation', ConfirmationSchema::answer()));
        }

        if (! $authenticated) {
            $operation->security = [];
        }
    }

    private static function confirms(DocumentationContext $context): bool
    {
        $action = $context->declaration();

        if ($action instanceof Action) {
            return $action->requiresConfirmation();
        }

        return $context->resource->confirms($context->endpoint);
    }

    private function shared(string $name, int $status, string $description, ObjectType $body): Reference
    {
        $schema = new Schema;
        $schema->type = $body;

        $response = Response::make($status)
            ->setDescription($description)
            ->setContent('application/json', $schema);

        return $this->components->response($name, $response, self::class);
    }

    private static function message(): ObjectType
    {
        return Schemas::required(['message' => new StringType]);
    }

    private static function forbidden(): ObjectType
    {
        $denial = Schemas::required([
            'path' => new StringType,
            'ability' => new StringType,
            'key' => self::key(),
            'message' => new StringType,
        ], ['key']);

        return Schemas::required([
            'message' => new StringType,
            'denials' => Schemas::list($denial, 'Present when the application renders the denials with Lomkit\Rest\Exceptions\DenialsRenderer'),
        ], ['denials']);
    }

    private static function conflict(): ObjectType
    {
        $conflict = Schemas::required([
            'path' => new StringType,
            'key' => self::key(),
            'version' => (new MixedType)->setDescription('The version of the row as the request found it, written as the search returns it; null when the row is gone'),
        ]);

        return Schemas::required([
            'message' => new StringType,
            'conflicts' => Schemas::list($conflict, 'The update entries whose `version` is not the version of their row: nothing was written'),
        ]);
    }

    private static function validation(): ObjectType
    {
        $errors = new ObjectType;
        $errors->additionalProperties(Schemas::list(new StringType));

        return Schemas::required(['message' => new StringType, 'errors' => $errors]);
    }

    private static function key(): Type
    {
        $key = new AnyOf;
        $key->setItems([new IntegerType, new StringType]);

        return $key;
    }
}
