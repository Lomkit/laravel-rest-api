<?php

namespace Lomkit\Rest\Tests\Feature\Documentation;

use Illuminate\Support\Facades\Route;
use Lomkit\Rest\Facades\Rest;
use Lomkit\Rest\Tests\Feature\Fields\Stubs\OfferedShowController;
use PHPUnit\Framework\Attributes\Test;

class OfferedCasesTest extends DocumentationTestCase
{
    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        Route::prefix('api')->group(fn () => Rest::resource('offered-shows', OfferedShowController::class));
    }

    #[Test]
    public function it_documents_the_offered_cases_of_an_action_field(): void
    {
        $document = $this->document();
        $validator = new OpenApiValidator;

        $this->assertSame([], $validator->requestErrors($document, '/offered-shows/actions/regrade-shows', 'post', ['resources' => [1], 'fields' => [['name' => 'level', 'value' => 2]]]));
        $this->assertNotSame([], $validator->requestErrors($document, '/offered-shows/actions/regrade-shows', 'post', ['resources' => [1], 'fields' => [['name' => 'level', 'value' => 3]]]));
    }

    #[Test]
    public function it_documents_the_offered_cases_of_a_pivot_field(): void
    {
        $document = $this->document();
        $validator = new OpenApiValidator;
        $attach = fn (string $role): array => ['mutate' => [['operation' => 'update', 'key' => 1, 'relations' => ['artists' => [['operation' => 'attach', 'key' => 1, 'pivot' => ['role' => $role]]]]]]];

        $this->assertSame([], $validator->requestErrors($document, '/offered-shows/mutate', 'post', $attach('guest')));
        $this->assertNotSame([], $validator->requestErrors($document, '/offered-shows/mutate', 'post', $attach('support')));
    }
}
