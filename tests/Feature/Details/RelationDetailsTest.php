<?php

namespace Lomkit\Rest\Tests\Feature\Details;

use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Facades\Route;
use Lomkit\Rest\Facades\Rest;
use Lomkit\Rest\Tests\Feature\Details\Stubs\TeamPivotTicketController;
use Lomkit\Rest\Tests\Fixtures\Helpdesk\Helpdesk;
use Lomkit\Rest\Tests\Fixtures\Library\Book;
use Lomkit\Rest\Tests\Fixtures\Library\BookResource;
use Lomkit\Rest\Tests\Fixtures\Library\Library;
use Lomkit\Rest\Tests\PackageTestCase;
use PHPUnit\Framework\Attributes\Test;

class RelationDetailsTest extends PackageTestCase
{
    private const string POSITION = '{"name":"position","label":null,"type":"integer","nullable":true,"select":true,"sort":false,"filter":{"operators":["=","!=",">",">=","<","<=","between","not between","in","not in","is null","is not null"]},"create":true,"update":true,"rules":{"all":["nullable","integer"],"create":[],"update":[]},"meta":{}}';

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        Relation::morphMap([], false);
    }

    #[\Override]
    protected function fixtures(): array
    {
        return [new Helpdesk, new Library];
    }

    #[Test]
    public function it_lists_the_pivot_fields_of_many_to_many_relations(): void
    {
        $relations = $this->relations('/api/tickets');

        $this->assertSame(['BelongsTo', 'BelongsToMany', 'MorphToMany'], array_column($relations, 'name'));
        $this->assertSame('['.self::POSITION.']', json_encode($relations[1]->pivot_fields));
        $this->assertSame('['.self::POSITION.']', json_encode($relations[2]->pivot_fields));
        $this->assertObjectNotHasProperty('pivot_fields', $relations[0]);
    }

    #[Test]
    public function it_lists_no_pivot_fields_on_a_many_to_many_relation_without_them(): void
    {
        $relations = $this->relations('/api/books');

        $this->assertSame('BelongsToMany', $relations[5]->name);
        $this->assertSame('[]', json_encode($relations[5]->pivot_fields));
        $this->assertSame(
            [false, false, false, false, false],
            array_map(fn (object $relation): bool => property_exists($relation, 'pivot_fields'), array_slice($relations, 0, 5)),
        );
    }

    #[Test]
    public function it_lists_the_morph_types_and_columns_of_a_morph_to_relation(): void
    {
        $relation = $this->relations('/api/labels')[0];

        $this->assertSame(json_encode([Book::class => BookResource::class]), json_encode($relation->types));
        $this->assertSame('{"type_column":"labelable_type","key_column":"labelable_id"}', json_encode($relation->morph));
    }

    #[Test]
    public function it_describes_pivot_fields_that_cannot_be_queried_when_the_model_method_is_not_many_to_many(): void
    {
        Route::prefix('api')->group(fn () => Rest::resource('team-pivot-tickets', TeamPivotTicketController::class)->register());

        $relations = $this->relations('/api/team-pivot-tickets');

        $this->assertSame(
            '[{"name":"position","label":null,"type":"integer","nullable":true,"select":false,"sort":false,"filter":{"operators":[]},"create":true,"update":true,"rules":{"all":["nullable","integer"],"create":[],"update":[]},"meta":{}}]',
            json_encode($relations[0]->pivot_fields),
        );
    }

    /** @return list<object> */
    private function relations(string $uri): array
    {
        $response = $this->getJson($uri)->assertOk();

        return json_decode((string) $response->getContent(), flags: JSON_THROW_ON_ERROR)->data->relations;
    }
}
