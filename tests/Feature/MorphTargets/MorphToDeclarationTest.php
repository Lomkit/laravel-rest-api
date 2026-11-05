<?php

namespace Lomkit\Rest\Tests\Feature\MorphTargets;

use LogicException;
use Lomkit\Rest\Exceptions\InvalidResourceException;
use Lomkit\Rest\Facades\Rest;
use Lomkit\Rest\Http\Requests\RestRequest;
use Lomkit\Rest\Http\Resource;
use Lomkit\Rest\Http\RestContext;
use Lomkit\Rest\Relations\MorphTo;
use Lomkit\Rest\Tests\Feature\MorphTargets\Stubs\DeclaredLabelController;
use Lomkit\Rest\Tests\Feature\MorphTargets\Stubs\DeclaredLabelResource;
use Lomkit\Rest\Tests\Feature\Search\Stubs\BookLabelResource;
use Lomkit\Rest\Tests\Fixtures\Library\BookResource;
use Lomkit\Rest\Tests\Fixtures\Library\LabelResource;
use Lomkit\Rest\Tests\Fixtures\Library\ShelfResource;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;

class MorphToDeclarationTest extends MorphTargetsTestCase
{
    /** @var list<class-string<Resource>|null> */
    private array $received = [];

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        Rest::resource('declared-labels', DeclaredLabelController::class)->register();
    }

    #[\Override]
    protected function tearDown(): void
    {
        DeclaredLabelResource::$declare = null;

        parent::tearDown();
    }

    #[Test]
    #[DataProvider('invalidRelations')]
    public function it_refuses_a_morph_to_declaration_on_the_first_request(MorphTo $relation, string $message): void
    {
        DeclaredLabelResource::$declare = fn (): array => [$relation];
        $this->withoutExceptionHandling();

        $this->expectExceptionObject(new InvalidResourceException($message));

        $this->getJson('/declared-labels');
    }

    /** @return array<string, array{MorphTo, string}> */
    public static function invalidRelations(): array
    {
        $resource = DeclaredLabelResource::class;

        return [
            'keyed targets' => [MorphTo::make('labelable', ['book' => BookResource::class]), "The MorphTo relation [labelable] of the resource [{$resource}] declares its targets with a resource class or a list of resource classes."],
        ];
    }

    #[Test]
    public function it_passes_no_resource_to_the_constraints_of_a_relation_with_several_targets(): void
    {
        $record = function (RestRequest $request, ?Resource $resource): bool {
            $this->received[] = $resource === null ? null : $resource::class;

            return true;
        };
        $mutate = ['mutate' => [['operation' => 'create', 'attributes' => ['text' => 'Classic']]]];

        DeclaredLabelResource::$declare = fn (): array => [MorphTo::make('labelable', [BookResource::class, ShelfResource::class])->requiredOnCreation($record)];
        $several = $this->postJson('/declared-labels/mutate', $mutate);
        DeclaredLabelResource::$declare = fn (): array => [MorphTo::make('labelable', BookResource::class)->requiredOnCreation($record)];
        $single = $this->postJson('/declared-labels/mutate', $mutate);

        $several->assertJsonValidationErrors(['mutate.0.relations.labelable' => 'The mutate.0.relations.labelable field is required.']);
        $single->assertJsonValidationErrors(['mutate.0.relations.labelable' => 'The mutate.0.relations.labelable field is required.']);
        $this->assertSame([null, BookResource::class], $this->received);
    }

    #[Test]
    public function it_gives_the_resource_of_a_relation_with_one_target_only(): void
    {
        $context = new RestContext(RestRequest::create('/api/labels/search', 'POST'), $this->app);
        $single = $context->resource(BookLabelResource::class)->relation('labelable');
        $several = $context->resource(LabelResource::class)->relation('labelable');

        $this->assertNotNull($single);
        $this->assertNotNull($several);
        $this->assertSame(BookResource::class, $single->resource()::class);
        $this->expectExceptionObject(new LogicException('The MorphTo relation [labelable] has several targets: resolve one with targets().'));

        $several->resource();
    }

    #[Test]
    public function it_keeps_a_relation_that_names_its_columns_as_a_single_target(): void
    {
        DeclaredLabelResource::$declare = fn (): array => [MorphTo::make('book', BookResource::class)];

        $response = $this->postJson('/declared-labels/search', ['search' => ['selects' => [['field' => 'id']], 'sorts' => [['field' => 'id']], 'includes' => [['relation' => 'book']]]]);
        $details = $this->getJson('/declared-labels');

        $response->assertOk()->assertJsonPath('data.*.book', [['@type' => 'book', 'id' => 1, 'title' => 'Dune'], null, ['@type' => 'book', 'id' => 2, 'title' => 'Emma'], null, null]);
        $details
            ->assertOk()
            ->assertJsonPath('data.relations.0.resource', BookResource::class)
            ->assertJsonPath('data.relations.0.types', ['book' => BookResource::class]);
    }
}
