<?php

namespace Lomkit\Rest\Tests\Feature\Documentation;

use Illuminate\Support\Facades\Route;
use Lomkit\Rest\Facades\Rest;
use Lomkit\Rest\Tests\Feature\Documentation\Stubs\FixedNoteController;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;

class ErrorResponsesTest extends DocumentationTestCase
{
    #[Test]
    public function it_defines_the_error_responses_once_for_every_operation(): void
    {
        $message = ['type' => 'object', 'properties' => ['message' => ['type' => 'string']], 'required' => ['message']];
        $denial = [
            'type' => 'object',
            'properties' => ['path' => ['type' => 'string'], 'ability' => ['type' => 'string'], 'key' => ['type' => ['integer', 'string']], 'message' => ['type' => 'string']],
            'required' => ['path', 'ability', 'message'],
        ];
        $forbidden = [
            'type' => 'object',
            'properties' => [
                'message' => ['type' => 'string'],
                'denials' => ['type' => 'array', 'description' => 'Present when the application renders the denials with Lomkit\Rest\Exceptions\DenialsRenderer', 'items' => $denial],
            ],
            'required' => ['message'],
        ];
        $conflict = [
            'type' => 'object',
            'properties' => [
                'message' => ['type' => 'string'],
                'conflicts' => [
                    'type' => 'array',
                    'description' => 'The update entries whose `version` is not the version of their row, and the `key` of the sync entries whose list leaves out a child, with that child: nothing was written',
                    'items' => [
                        'type' => 'object',
                        'properties' => ['path' => ['type' => 'string'], 'key' => ['type' => ['integer', 'string']], 'version' => ['description' => 'On an update entry: the version of the row as the request found it, written as the search returns it; null when the row is gone']],
                        'required' => ['path', 'key'],
                    ],
                ],
            ],
            'required' => ['message', 'conflicts'],
        ];
        $validation = [
            'type' => 'object',
            'properties' => ['message' => ['type' => 'string'], 'errors' => ['type' => 'object', 'additionalProperties' => ['type' => 'array', 'items' => ['type' => 'string']]]],
            'required' => ['message', 'errors'],
        ];
        $confirmation = [
            'type' => 'object',
            'properties' => [
                'message' => ['type' => 'string'],
                'confirmation' => [
                    'type' => 'object',
                    'properties' => [
                        'token' => ['type' => 'string', 'description' => 'A signed token, valid for this user, this endpoint and this body (and for an action, this number of rows) until expires_at'],
                        'expires_at' => ['type' => 'string', 'format' => 'date-time'],
                        'data' => ['type' => 'object', 'description' => 'The data that the action or the resource gives to its confirmation'],
                    ],
                    'required' => ['token', 'expires_at', 'data'],
                    'additionalProperties' => false,
                ],
            ],
            'required' => ['message', 'confirmation'],
        ];

        $this->assertSame([
            'RestConfirmationRequired' => ['description' => 'Confirmation required: send the same body again with the token in confirmation', 'content' => ['application/json' => ['schema' => $confirmation]]],
            'RestConflict' => ['description' => 'Conflict', 'content' => ['application/json' => ['schema' => $conflict]]],
            'RestForbidden' => ['description' => 'Forbidden', 'content' => ['application/json' => ['schema' => $forbidden]]],
            'RestNotFound' => ['description' => 'Not found', 'content' => ['application/json' => ['schema' => $message]]],
            'RestUnauthenticated' => ['description' => 'Unauthenticated', 'content' => ['application/json' => ['schema' => $message]]],
            'RestValidationFailed' => ['description' => 'Validation error', 'content' => ['application/json' => ['schema' => $validation]]],
        ], $this->document()['components']['responses']);
    }

    /** @return array<string, array{string, string, list<int>, bool}> */
    public static function operations(): array
    {
        return [
            'details behind auth' => ['/shows', 'get', [200, 401, 403, 404, 422], true],
            'search behind auth' => ['/shows/search', 'post', [200, 401, 403, 404, 422], true],
            'an action behind auth' => ['/shows/actions/reschedule-shows', 'post', [200, 401, 403, 404, 422], true],
            'an action that asks for a confirmation' => ['/shows/actions/cancel-shows', 'post', [200, 401, 403, 404, 422, 428], true],
            'a metric without the auth of its resource' => ['/shows/metrics/shows-by-status', 'post', [200, 403, 404, 422], false],
            'batch progress' => ['/shows/actions/{action}/batches/{batch}', 'get', [200, 401, 403, 404], true],
            'force delete that asks for a confirmation' => ['/shows/force', 'delete', [200, 401, 403, 404, 422, 428], true],
            'delete without a confirmation' => ['/shows', 'delete', [200, 401, 403, 404, 422], true],
            'details of a resource registered after one behind auth' => ['/notes', 'get', [200, 403, 404, 422], false],
            'mutate of a resource registered after one behind auth' => ['/notes/mutate', 'post', [200, 403, 404, 409, 422], false],
            'mutate of a versioned resource that asks for a confirmation' => ['/shows/mutate', 'post', [200, 401, 403, 404, 409, 422, 428], true],
            'restore' => ['/shows/restore', 'post', [200, 401, 403, 404, 422], true],
        ];
    }

    /** @param list<int> $statuses */
    #[Test]
    #[DataProvider('operations')]
    public function it_lists_the_error_responses_of_each_operation(string $path, string $method, array $statuses, bool $authenticated): void
    {
        $operation = $this->operation($path, $method);
        $names = [401 => 'RestUnauthenticated', 403 => 'RestForbidden', 404 => 'RestNotFound', 409 => 'RestConflict', 422 => 'RestValidationFailed', 428 => 'RestConfirmationRequired'];

        $this->assertSame($statuses, array_keys($operation['responses']));

        foreach (array_slice($statuses, 1) as $status) {
            $this->assertSame(['$ref' => '#/components/responses/'.$names[$status]], $operation['responses'][$status]);
        }

        $authenticated
            ? $this->assertArrayNotHasKey('security', $operation)
            : $this->assertSame([], $operation['security']);
    }

    #[Test]
    public function it_lists_no_conflict_for_a_mutation_that_reaches_no_versioned_update(): void
    {
        Route::prefix('api')->group(fn () => Rest::resource('fixed-notes', FixedNoteController::class)->register());

        $this->assertSame([200, 403, 404, 422], array_keys($this->operation('/fixed-notes/mutate', 'post')['responses']));
    }
}
