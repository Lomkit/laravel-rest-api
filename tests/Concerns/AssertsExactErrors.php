<?php

namespace Lomkit\Rest\Tests\Concerns;

use Illuminate\Testing\TestResponse;

trait AssertsExactErrors
{
    /** @param array<string, list<string>> $errors */
    private function assertExactErrors(TestResponse $response, array $errors): void
    {
        $first = array_values($errors)[0][0];
        $more = count($errors) - 1;
        $message = $more === 0 ? $first : $first.' (and '.$more.' more '.($more === 1 ? 'error' : 'errors').')';

        $response->assertUnprocessable()->assertExactJson(['message' => $message, 'errors' => $errors]);
    }
}
