<?php

namespace Lomkit\Rest\Tests\Feature\Documentation;

use Lomkit\Rest\Tests\Fixtures\Accounts\User;
use PHPUnit\Framework\Attributes\Test;

class ContractTest extends DocumentationTestCase
{
    #[Test]
    public function it_answers_each_details_operation_as_the_document_describes(): void
    {
        $this->actingAs(new User(['id' => 1, 'name' => 'Ada']));
        $document = $this->document();
        $validator = new OpenApiValidator;
        $checked = [];

        foreach ($document['paths'] as $path => $operations) {
            if (! str_ends_with($operations['get']['operationId'] ?? '', '.details')) {
                continue;
            }

            $response = $this->getJson('api'.$path);

            $response->assertOk();
            $this->assertSame([], $validator->responseErrors($document, $path, 'get', 200, (string) $response->getContent()), $path);
            $checked[] = $path;
        }

        $this->assertSame(['/notes', '/shows', '/stages'], $checked);
    }

    #[Test]
    public function it_reports_a_response_that_the_document_does_not_describe(): void
    {
        $errors = (new OpenApiValidator)->responseErrors($this->document(), '/stages', 'get', 200, '{"data": {"fields": "code"}}');

        $this->assertSame(['/data'], array_keys($errors));
    }
}
