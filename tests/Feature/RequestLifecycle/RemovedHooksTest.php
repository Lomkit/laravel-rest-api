<?php

namespace Lomkit\Rest\Tests\Feature\RequestLifecycle;

use Lomkit\Rest\Exceptions\InvalidInstructionException;
use Lomkit\Rest\Exceptions\InvalidResourceException;
use Lomkit\Rest\Facades\Rest;
use Lomkit\Rest\Http\Requests\RestRequest;
use Lomkit\Rest\Http\Resource;
use Lomkit\Rest\Http\RestContext;
use Lomkit\Rest\Tests\Feature\RequestLifecycle\Stubs\ScoutFieldsPostController;
use Lomkit\Rest\Tests\Feature\RequestLifecycle\Stubs\ScoutFieldsPostResource;
use Lomkit\Rest\Tests\Feature\RequestLifecycle\Stubs\ScoutHandledInstruction;
use Lomkit\Rest\Tests\Feature\RequestLifecycle\Stubs\ScoutHandledInstructionPostResource;
use Lomkit\Rest\Tests\Feature\RequestLifecycle\Stubs\ScoutInstructionsPostResource;
use Lomkit\Rest\Tests\Feature\RequestLifecycle\Stubs\ScoutQueryPostResource;
use Lomkit\Rest\Tests\Fixtures\Publishing\Post;
use Lomkit\Rest\Tests\Fixtures\Publishing\Publishing;
use Lomkit\Rest\Tests\PackageTestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;

class RemovedHooksTest extends PackageTestCase
{
    #[\Override]
    protected function fixtures(): array
    {
        return [new Publishing];
    }

    /** @param class-string<Resource> $resource */
    #[Test]
    #[DataProvider('resourceHooks')]
    public function it_refuses_a_resource_that_still_declares_a_removed_hook(string $resource, string $message): void
    {
        $this->expectExceptionObject(new InvalidResourceException("The resource [{$resource}] declares {$message}"));

        $this->context()
            ->resource($resource)
            ->getFields($this->context()->request);
    }

    /** @return array<string, array{class-string<Resource>, string}> */
    public static function resourceHooks(): array
    {
        return [
            'the index fields' => [ScoutFieldsPostResource::class, 'scoutFields(), which the package no longer calls. Declare the index attributes with ScoutEngine::make()->filterable() and ->sortable() in text().'],
            'the index instructions' => [ScoutInstructionsPostResource::class, 'scoutInstructions(), which the package no longer calls. Declare these instructions in instructions() and implement AppliesToEngine on them.'],
            'the index perimeter' => [ScoutQueryPostResource::class, 'searchScoutQuery(), which the package no longer calls. Declare the index perimeter with ScoutEngine::make()->perimeter() in text().'],
        ];
    }

    #[Test]
    public function it_refuses_an_instruction_that_still_declares_a_removed_hook(): void
    {
        $context = $this->context();

        $this->expectExceptionObject(new InvalidInstructionException(ScoutHandledInstruction::class.' declares handleScout(), which the package no longer calls. Implement AppliesToEngine and move the method to handleEngine(array $fields, EngineQuery $query).'));

        $context->resource(ScoutHandledInstructionPostResource::class)->getInstructions($context->request);
    }

    #[Test]
    public function it_refuses_a_request_to_a_resource_that_still_declares_a_removed_hook(): void
    {
        Rest::resource('scout-fields-posts', ScoutFieldsPostController::class)->register();
        Post::factory()->create();
        $this->withoutExceptionHandling();

        $this->expectExceptionObject(new InvalidResourceException('The resource ['.ScoutFieldsPostResource::class.'] declares '.self::resourceHooks()['the index fields'][1]));

        $this->postJson('/scout-fields-posts/search');
    }

    private function context(): RestContext
    {
        return new RestContext(RestRequest::create('/api/posts/search', 'POST'), $this->app);
    }
}
