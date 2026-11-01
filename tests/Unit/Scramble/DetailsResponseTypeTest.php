<?php

namespace Lomkit\Rest\Tests\Unit\Scramble;

use Dedoc\Scramble\Support\Generator\Operation;
use Dedoc\Scramble\Support\Generator\Response;
use Lomkit\Rest\Scramble\DetailsResponseType;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class DetailsResponseTypeTest extends TestCase
{
    #[Test]
    public function it_replaces_every_response_with_the_code_200(): void
    {
        $operation = new Operation('get');
        $operation->addResponse(new Response(200));
        $operation->addResponse(new Response(422));
        $operation->addResponse(new Response('200'));

        DetailsResponseType::document($operation, 'Artwork');
        $responses = $operation->responses ?? [];

        $this->assertSame('Get Artwork details', $operation->summary);
        $this->assertCount(2, $responses);
        $this->assertInstanceOf(Response::class, $responses[0]);
        $this->assertSame(422, $responses[0]->code);
        $this->assertInstanceOf(Response::class, $responses[1]);
        $this->assertSame(200, $responses[1]->code);
        $this->assertSame('The fields, relations, scopes, actions, instructions, rules and text search of the resource', $responses[1]->description);
    }
}
