<?php

namespace Lomkit\Rest\Tests\Feature\Scaffolding;

use App\Rest\Responses\ScaffoldedResponse;
use Lomkit\Rest\Http\Response;
use Lomkit\Rest\Tests\Fixtures\Catalog\Product;
use Lomkit\Rest\Tests\ScaffoldingTestCase;
use PHPUnit\Framework\Attributes\Test;
use ReflectionMethod;

class MakeResponseCommandTest extends ScaffoldingTestCase
{
    #[Test]
    public function it_writes_a_response_class_whose_map_returns_the_response_model(): void
    {
        $this->artisan('rest:response', ['name' => 'ScaffoldedResponse'])
            ->expectsOutputToContain('Response [app/Rest/Responses/ScaffoldedResponse.php] created successfully.')
            ->assertSuccessful();

        $this->loadGeneratedClass(ScaffoldedResponse::class);
        $response = new ScaffoldedResponse;
        $responseModel = ['name' => 'Desk lamp', 'price' => 4200];

        $this->assertInstanceOf(Response::class, $response);
        $this->assertSame($responseModel, (new ReflectionMethod($response, 'map'))->invoke($response, new Product, $responseModel));
    }
}
