<?php

namespace Lomkit\Rest\Scramble;

use Dedoc\Scramble\Support\Generator\Combined\AnyOf;
use Dedoc\Scramble\Support\Generator\Operation;
use Dedoc\Scramble\Support\Generator\Reference;
use Dedoc\Scramble\Support\Generator\Response;
use Dedoc\Scramble\Support\Generator\Schema;
use Dedoc\Scramble\Support\Generator\Types\IntegerType;
use Dedoc\Scramble\Support\Generator\Types\ObjectType;
use Dedoc\Scramble\Support\Generator\Types\StringType;
use Dedoc\Scramble\Support\Generator\Types\Type;
use Lomkit\Rest\Actions\Action;
use Lomkit\Rest\Http\Routing\Endpoint;

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

        if (! in_array($context->endpoint, [Endpoint::Details, Endpoint::Batch], true)) {
            $operation->addResponse($this->shared('RestValidationFailed', 422, 'Validation error', self::validation()));
        }

        $action = $context->declaration();

        if ($action instanceof Action && $action->requiresConfirmation()) {
            $operation->addResponse($this->shared('RestConfirmationRequired', 428, 'Confirmation required: send the same body again with the token in confirmation', self::confirmation()));
        }

        if (! $authenticated) {
            $operation->security = [];
        }
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

    private static function validation(): ObjectType
    {
        $errors = new ObjectType;
        $errors->additionalProperties(Schemas::list(new StringType));

        return Schemas::required(['message' => new StringType, 'errors' => $errors]);
    }

    private static function confirmation(): ObjectType
    {
        return Schemas::required([
            'message' => new StringType,
            'confirmation' => Schemas::closed([
                'token' => (new StringType)->setDescription('A signed token, valid for this user, this action, this body and this number of rows until expires_at'),
                'expires_at' => (new StringType)->format('date-time'),
                'data' => (new ObjectType)->setDescription('The data that the action gives to its confirmation'),
            ], ['token', 'expires_at', 'data']),
        ]);
    }

    private static function key(): Type
    {
        $key = new AnyOf;
        $key->setItems([new IntegerType, new StringType]);

        return $key;
    }
}
