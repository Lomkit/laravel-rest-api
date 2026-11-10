<?php

namespace Lomkit\Rest\Tests\Feature\Details;

use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Facades\Route;
use Lomkit\Rest\Facades\Rest;
use Lomkit\Rest\Tests\Feature\Details\Stubs\DeletableTicketsTeamController;
use Lomkit\Rest\Tests\Feature\Details\Stubs\TeamPivotTicketController;
use Lomkit\Rest\Tests\Feature\Mutate\Stubs\KeptTeamController;
use Lomkit\Rest\Tests\Feature\Mutate\Stubs\KeptTicketController;
use Lomkit\Rest\Tests\Fixtures\Helpdesk\Helpdesk;
use Lomkit\Rest\Tests\Fixtures\Library\Book;
use Lomkit\Rest\Tests\Fixtures\Library\BookResource;
use Lomkit\Rest\Tests\Fixtures\Library\Library;
use Lomkit\Rest\Tests\Fixtures\Library\Shelf;
use Lomkit\Rest\Tests\Fixtures\Library\ShelfResource;
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

        $this->assertSame(json_encode([Book::class => BookResource::class, Shelf::class => ShelfResource::class]), json_encode($relation->types));
        $this->assertFalse(property_exists($relation, 'resource'));
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

    #[Test]
    public function it_lists_the_operations_that_each_relation_accepts(): void
    {
        $four = ['create', 'update', 'attach', 'detach'];

        $this->assertSame(
            ['loan' => $four, 'latestLoan' => $four, 'label' => $four, 'latestLabel' => $four, 'labels' => $four, 'shelves' => [...$four, 'sync', 'toggle']],
            $this->operations('/api/books'),
        );
        $this->assertSame(['team' => $four, 'tags' => [...$four, 'sync', 'toggle'], 'labels' => [...$four, 'sync', 'toggle']], $this->operations('/api/tickets'));
        $this->assertSame(['labelable' => $four], $this->operations('/api/labels'));
        $this->assertSame(['tickets' => $four, 'replies' => []], $this->operations('/api/teams'));
    }

    #[Test]
    public function it_lists_delete_on_a_deletable_relation(): void
    {
        Route::prefix('api')->group(fn () => Rest::resource('deletable-tickets-teams', DeletableTicketsTeamController::class)->register());

        $this->assertSame(['tickets' => ['create', 'update', 'attach', 'detach', 'delete'], 'replies' => []], $this->operations('/api/deletable-tickets-teams'));
    }

    #[Test]
    public function it_lists_the_operations_left_on_a_relation_that_withdraws_some(): void
    {
        Route::prefix('api')->group(function (): void {
            Rest::resource('kept-tickets', KeptTicketController::class)->register();
            Rest::resource('kept-teams', KeptTeamController::class)->register();
        });

        $this->assertSame(
            ['team' => ['create', 'update', 'attach'], 'tags' => ['create', 'update', 'attach', 'sync'], 'labels' => ['create', 'attach', 'detach', 'sync', 'toggle']],
            $this->operations('/api/kept-tickets'),
        );
        $this->assertSame(['tickets' => ['create', 'update', 'attach']], $this->operations('/api/kept-teams'));
    }

    /** @return array<string, list<string>> */
    private function operations(string $uri): array
    {
        return array_column($this->relations($uri), 'operations', 'relation');
    }

    /** @return list<object> */
    private function relations(string $uri): array
    {
        $response = $this->getJson("{$uri}?with=relations.operations,relations.pivot_fields")->assertOk();

        return json_decode((string) $response->getContent(), flags: JSON_THROW_ON_ERROR)->data->relations;
    }
}
