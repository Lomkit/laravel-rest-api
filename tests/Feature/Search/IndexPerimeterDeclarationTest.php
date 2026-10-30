<?php

namespace Lomkit\Rest\Tests\Feature\Search;

use Illuminate\Contracts\Database\Eloquent\Builder;
use Laravel\Scout\Builder as ScoutQuery;
use Lomkit\Rest\Authorization\Check;
use Lomkit\Rest\Exceptions\InvalidResourceException;
use Lomkit\Rest\Http\Requests\RestRequest;
use Lomkit\Rest\Http\Resource;
use Lomkit\Rest\Http\RestContext;
use Lomkit\Rest\Query\ScoutBuilder;
use Lomkit\Rest\Tests\Fixtures\Workspace\Note;
use Lomkit\Rest\Tests\Fixtures\Workspace\SqlOnlyTeamNoteResource;
use Lomkit\Rest\Tests\Fixtures\Workspace\TeamNoteResource;
use Lomkit\Rest\Tests\Fixtures\Workspace\Workspace;
use Lomkit\Rest\Tests\PackageTestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;

class IndexPerimeterDeclarationTest extends PackageTestCase
{
    protected function fixtures(): array
    {
        return [new Workspace];
    }

    #[Test]
    #[DataProvider('declaredIndexPerimeters')]
    public function it_runs_a_text_search_when_search_scout_query_is_declared_with_search_query(Resource $resource): void
    {
        $query = $this->scoutBuilder($resource)->search(['text' => ['value' => 'roadmap']]);

        $this->assertSame('roadmap', $query->query);
    }

    /** @return array<string, array{Resource}> */
    public static function declaredIndexPerimeters(): array
    {
        return [
            'neither hook overridden' => [new class extends Resource
            {
                public static string $model = Note::class;
            }],
            'both hooks in the same class' => [new TeamNoteResource],
            'searchScoutQuery() in a subclass of the class of searchQuery()' => [new class extends SqlOnlyTeamNoteResource
            {
                /**
                 * @param  ScoutQuery<Note>  $query
                 * @return ScoutQuery<Note>
                 */
                public function searchScoutQuery(RestRequest $request, ScoutQuery $query): ScoutQuery
                {
                    return $query->where('team', 'red');
                }
            }],
        ];
    }

    #[Test]
    #[DataProvider('missingIndexPerimeters')]
    public function it_refuses_a_text_search_when_search_query_is_declared_below_search_scout_query(Resource $resource): void
    {
        $this->expectExceptionObject(InvalidResourceException::missingIndexPerimeter($resource::class));

        $this->scoutBuilder($resource)->search(['text' => ['value' => 'roadmap']]);
    }

    /** @return array<string, array{Resource}> */
    public static function missingIndexPerimeters(): array
    {
        return [
            'searchQuery() alone' => [new SqlOnlyTeamNoteResource],
            'searchQuery() in a subclass of the class of searchScoutQuery()' => [new class extends TeamNoteResource
            {
                public function searchQuery(RestRequest $request, Builder $query): Builder
                {
                    return parent::searchQuery($request, $query)->where('pinned', true);
                }
            }],
        ];
    }

    #[Test]
    public function it_names_the_resource_and_the_hook_to_declare(): void
    {
        $this->expectException(InvalidResourceException::class);
        $this->expectExceptionMessage(
            'The resource [Lomkit\Rest\Tests\Fixtures\Workspace\SqlOnlyTeamNoteResource] cannot run a text search: '
            .'searchScoutQuery() is not declared in the class that declares searchQuery() or in a subclass of it. '
            .'Declare searchScoutQuery() there with the same constraints as searchQuery(), as where() conditions on indexed, filterable attributes.'
        );

        $this->scoutBuilder(new SqlOnlyTeamNoteResource)->search(['text' => ['value' => 'roadmap']]);
    }

    private function scoutBuilder(Resource $resource): ScoutBuilder
    {
        $context = new RestContext(new RestRequest, $this->app);
        $context->authorizer()->plan([Check::onClass($resource->withinContext($context), 'viewAny')]);

        return new ScoutBuilder($resource);
    }
}
