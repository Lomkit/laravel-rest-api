<?php

namespace Lomkit\Rest\Tests\Unit\Fields;

use Lomkit\Rest\Fields\FieldPath;
use Lomkit\Rest\Http\Requests\RestRequest;
use Lomkit\Rest\Http\Resource;
use Lomkit\Rest\Http\RestContext;
use Lomkit\Rest\Relations\Relation;
use Lomkit\Rest\Tests\Feature\Relations\Stubs\AssignedTicketResource;
use Lomkit\Rest\Tests\Feature\Relations\Stubs\WeightedTicketResource;
use Lomkit\Rest\Tests\Fixtures\Helpdesk\TagResource;
use Lomkit\Rest\Tests\Fixtures\Helpdesk\TeamResource;
use Lomkit\Rest\Tests\Fixtures\Helpdesk\TicketResource;
use Lomkit\Rest\Tests\Fixtures\Library\BookResource;
use Lomkit\Rest\Tests\Fixtures\Library\LabelResource;
use Lomkit\Rest\Tests\Fixtures\Library\LoanResource;
use Lomkit\Rest\Tests\Fixtures\Publishing\AuthorResource;
use Lomkit\Rest\Tests\Fixtures\Publishing\PostResource;
use Lomkit\Rest\Tests\PackageTestCase;
use Lomkit\Rest\Tests\Unit\Fields\Stubs\LinkedTicketResource;
use Lomkit\Rest\Tests\Unit\Fields\Stubs\UnfilteredPostResource;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;

class FieldPathTest extends PackageTestCase
{
    /** @return array<string, array{class-string<Resource>, string, array{relations: list<string>, field: string, resource: class-string<Resource>, pivot: bool, column: ?string}}> */
    public static function resolvedPaths(): array
    {
        return [
            'a field' => [PostResource::class, 'title', self::path([], 'title', PostResource::class, false, 'posts.title')],
            'a field of a to-one relation' => [PostResource::class, 'author.first_name', self::path(['author'], 'first_name', AuthorResource::class, false, 'authors.first_name')],
            'a field of a to-many relation' => [AuthorResource::class, 'posts.views', self::path(['posts'], 'views', PostResource::class, false, 'posts.views')],
            'a field of a nested relation' => [PostResource::class, 'author.posts.title', self::path(['author', 'posts'], 'title', PostResource::class, false, 'posts.title')],
            'a virtual field with a filter callback' => [PostResource::class, 'author.full_name', self::path(['author'], 'full_name', AuthorResource::class, false, null)],
            'a pivot field of a belongs to many relation' => [TicketResource::class, 'tags.pivot.position', self::path(['tags'], 'position', TagResource::class, true, 'tag_ticket.position')],
            'a pivot field of a morph to many relation' => [TicketResource::class, 'labels.pivot.position', self::path(['labels'], 'position', TagResource::class, true, 'labelables.position')],
            'a pivot field of a morphed by many relation' => [TagResource::class, 'labelledTickets.pivot.position', self::path(['labelledTickets'], 'position', TicketResource::class, true, 'labelables.position')],
            'a pivot field of a nested relation' => [TeamResource::class, 'tickets.tags.pivot.position', self::path(['tickets', 'tags'], 'position', TagResource::class, true, 'tag_ticket.position')],
            'a pivot field of a relation with a pivot class' => [AssignedTicketResource::class, 'tags.pivot.weight', self::path(['tags'], 'weight', TagResource::class, true, 'tag_ticket.weight')],
            'a pivot field whose table comes from the pivot class' => [LinkedTicketResource::class, 'tags.pivot.position', self::path(['tags'], 'position', TagResource::class, true, 'tag_ticket.position')],
            'a field of a morph to relation' => [LabelResource::class, 'labelable.title', self::path(['labelable'], 'title', BookResource::class, false, 'books.title')],
            'a morph to relation in the middle of a path' => [BookResource::class, 'labels.labelable.title', self::path(['labels', 'labelable'], 'title', BookResource::class, false, 'books.title')],
            'a relation after a morph to relation' => [LabelResource::class, 'labelable.loan.borrower', self::path(['labelable', 'loan'], 'borrower', LoanResource::class, false, 'loans.borrower')],
        ];
    }

    /** @return array<string, array{class-string<Resource>, string}> */
    public static function unresolvedPaths(): array
    {
        return [
            'an empty path' => [PostResource::class, ''],
            'an empty first segment' => [PostResource::class, '.title'],
            'an empty last segment' => [PostResource::class, 'author.'],
            'an empty middle segment' => [PostResource::class, 'author..first_name'],
            'an unknown field' => [PostResource::class, 'subtitle'],
            'an unknown field of a relation' => [PostResource::class, 'author.nickname'],
            'an unknown relation' => [PostResource::class, 'writer.first_name'],
            'a relation without a field' => [PostResource::class, 'author'],
            'a json field' => [PostResource::class, 'metadata'],
            'a json field of a relation' => [PostResource::class, 'author.settings'],
            'a write-only field' => [PostResource::class, 'author.api_token'],
            'a virtual field without a filter callback' => [PostResource::class, 'author.initials'],
            'a field declared not filterable' => [UnfilteredPostResource::class, 'title'],
            'a pivot segment first' => [TicketResource::class, 'pivot.position'],
            'a pivot segment alone' => [TicketResource::class, 'pivot'],
            'a pivot segment last' => [TicketResource::class, 'tags.pivot'],
            'a pivot segment before a relation' => [TeamResource::class, 'tickets.pivot.tags.name'],
            'two pivot segments' => [TicketResource::class, 'tags.pivot.pivot.position'],
            'a pivot segment after a relation without pivot' => [TicketResource::class, 'team.pivot.name'],
            'a pivot field read without its pivot segment' => [TicketResource::class, 'tags.position'],
            'an undeclared pivot field' => [TicketResource::class, 'tags.pivot.weight'],
            'a field of the related resource under the pivot segment' => [TicketResource::class, 'tags.pivot.name'],
            'a write-only pivot field' => [WeightedTicketResource::class, 'tags.pivot.note'],
        ];
    }

    /** @param class-string<Resource> $resource */
    #[Test]
    #[DataProvider('unresolvedPaths')]
    public function it_resolves_no_path_for_a_field_that_cannot_be_filtered(string $resource, string $path): void
    {
        $this->assertNull(FieldPath::resolve($this->resource($resource), $path));
    }

    /**
     * @param  class-string<Resource>  $resource
     * @param  array{relations: list<string>, field: string, resource: class-string<Resource>, pivot: bool, column: ?string}  $expected
     */
    #[Test]
    #[DataProvider('resolvedPaths')]
    public function it_resolves_the_relations_the_field_and_the_column_of_a_path(string $resource, string $path, array $expected): void
    {
        $resolved = FieldPath::resolve($this->resource($resource), $path);

        $this->assertNotNull($resolved);
        $this->assertSame($expected, [
            'relations' => array_map(fn (Relation $relation): string => $relation->relation, $resolved->relations),
            'field' => $resolved->field->name,
            'resource' => $resolved->resource::class,
            'pivot' => $resolved->isPivot(),
            'column' => $resolved->column($resolved->resource::newModel()),
        ]);
    }

    /** @return array<string, array{class-string<Resource>, string, list<string>}> */
    public static function relationsAlongPaths(): array
    {
        return [
            'a field' => [PostResource::class, 'title', []],
            'an unknown field of a relation' => [PostResource::class, 'author.nickname', ['author']],
            'an unknown relation' => [PostResource::class, 'writer.posts.title', []],
            'a relation after an unknown relation' => [PostResource::class, 'author.writer.posts.title', ['author']],
            'a pivot field' => [TicketResource::class, 'tags.pivot.position', ['tags']],
            'a pivot field of a nested relation' => [TeamResource::class, 'tickets.tags.pivot.colour', ['tickets', 'tags']],
            'a relation after a morph to relation' => [LabelResource::class, 'labelable.loan.borrower', ['labelable', 'loan']],
        ];
    }

    /**
     * @param  class-string<Resource>  $resource
     * @param  list<string>  $relations
     */
    #[Test]
    #[DataProvider('relationsAlongPaths')]
    public function it_lists_the_relations_a_path_goes_through_as_far_as_they_resolve(string $resource, string $path, array $relations): void
    {
        $this->assertSame($relations, array_map(
            fn (Relation $relation): string => $relation->relation,
            FieldPath::relationsAlong($this->resource($resource), $path),
        ));
    }

    #[Test]
    public function it_reads_the_fields_of_the_resource_context(): void
    {
        $context = new RestContext(RestRequest::create('/api/posts/search', 'POST'), $this->app);
        $resolved = FieldPath::resolve($context->resource(PostResource::class), 'author.first_name');

        $this->assertNotNull($resolved);
        $this->assertSame($context, $resolved->resource->context());
        $this->assertSame($context, $resolved->relations[0]->resource()->context());
    }

    #[Test]
    public function it_qualifies_a_field_with_the_table_of_the_model_it_is_given(): void
    {
        $resolved = FieldPath::resolve($this->resource(PostResource::class), 'author.first_name');

        $this->assertNotNull($resolved);
        $this->assertSame('laravel_reserved_0.first_name', $resolved->column(AuthorResource::newModel()->setTable('laravel_reserved_0')));
    }

    /**
     * @param  list<string>  $relations
     * @param  class-string<Resource>  $resource
     * @return array{relations: list<string>, field: string, resource: class-string<Resource>, pivot: bool, column: ?string}
     */
    private static function path(array $relations, string $field, string $resource, bool $pivot, ?string $column): array
    {
        return ['relations' => $relations, 'field' => $field, 'resource' => $resource, 'pivot' => $pivot, 'column' => $column];
    }

    /** @param class-string<Resource> $resource */
    private function resource(string $resource): Resource
    {
        return (new RestContext(RestRequest::create('/api/search', 'POST'), $this->app))->resource($resource);
    }
}
