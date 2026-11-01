<?php

namespace Lomkit\Rest\Tests\Concerns;

use Illuminate\Testing\TestResponse;

trait AssertsExactErrors
{
    /** @param array<string, list<string>> $errors */
    private function assertExactErrors(TestResponse $response, array $errors): void
    {
        $messages = array_merge(...array_values($errors));
        $more = count($messages) - 1;
        $message = $more === 0 ? $messages[0] : $messages[0].' (and '.$more.' more '.($more === 1 ? 'error' : 'errors').')';

        $response->assertUnprocessable()->assertExactJson(['message' => $message, 'errors' => $errors]);
    }
}
