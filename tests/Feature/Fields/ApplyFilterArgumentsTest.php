<?php

namespace Lomkit\Rest\Tests\Feature\Fields;

use Closure;
use Illuminate\Database\Eloquent\Builder;
use Lomkit\Rest\Exceptions\InvalidFieldException;
use Lomkit\Rest\Fields\Boolean;
use Lomkit\Rest\Fields\Date;
use Lomkit\Rest\Fields\Enum;
use Lomkit\Rest\Fields\Field;
use Lomkit\Rest\Fields\Integer;
use Lomkit\Rest\Fields\Json;
use Lomkit\Rest\Fields\Text;
use Lomkit\Rest\Tests\Fixtures\Publishing\Post;
use Lomkit\Rest\Tests\Fixtures\Publishing\PostStatus;
use Lomkit\Rest\Tests\Fixtures\Publishing\Publishing;
use Lomkit\Rest\Tests\PackageTestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;

class ApplyFilterArgumentsTest extends PackageTestCase
{
    #[\Override]
    protected function fixtures(): array
    {
        return [new Publishing];
    }

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        Post::factory()->create(['title' => 'Alpha launch', 'summary' => 'First look', 'status' => PostStatus::Draft, 'views' => 10, 'published_on' => '2026-01-05']);
        Post::factory()->create(['title' => 'Beta Notes', 'summary' => null, 'status' => PostStatus::Review, 'views' => 250, 'published_on' => '2026-02-10']);
        Post::factory()->create(['title' => 'Gamma release', 'summary' => 'Stable build', 'status' => PostStatus::Published, 'views' => 1200, 'published_on' => '2026-03-15']);
    }

    /**
     * @param  Closure(): Field  $field
     * @param  list<string>  $expected
     */
    #[Test]
    #[DataProvider('alternatives')]
    public function it_combines_the_condition_with_the_previous_where_using_or(Closure $field, string $operator, mixed $value, array $expected): void
    {
        $query = Post::query()->where('title', 'Alpha launch');

        $this->bound($field)->applyFilter($query, $operator, $value, 'or');

        $this->assertTitles($expected, $query);
    }

    /** @return iterable<string, array{Closure(): Field, string, mixed, list<string>}> */
    public static function alternatives(): iterable
    {
        yield 'comparison' => [fn (): Field => Integer::make('views'), '>', 1000, ['Alpha launch', 'Gamma release']];
        yield 'pattern' => [fn (): Field => Text::make('title'), 'like', 'Beta%', ['Alpha launch', 'Beta Notes']];
        yield 'case insensitive pattern' => [fn (): Field => Text::make('title'), 'ilike', 'GAMMA%', ['Alpha launch', 'Gamma release']];
        yield 'membership' => [fn (): Field => Enum::make('status'), 'in', ['review'], ['Alpha launch', 'Beta Notes']];
        yield 'range' => [fn (): Field => Date::make('published_on'), 'between', ['2026-03-01', '2026-03-31'], ['Alpha launch', 'Gamma release']];
        yield 'nullity' => [fn (): Field => Text::make('summary')->nullable(), 'is null', null, ['Alpha launch', 'Beta Notes']];
    }

    #[Test]
    public function it_combines_the_condition_with_the_previous_where_using_and_by_default(): void
    {
        $query = Post::query()->where('title', 'Alpha launch');

        Integer::make('views')->applyFilter($query, '>', 1000);

        $this->assertTitles([], $query);
    }

    #[Test]
    public function it_filters_the_given_column_instead_of_the_field_name(): void
    {
        $query = Post::query();

        Text::make('headline')->applyFilter($query, '=', 'Gamma release', 'and', 'title');

        $this->assertTitles(['Gamma release'], $query);
        $this->assertStringNotContainsString('headline', $query->toSql());
    }

    #[Test]
    public function it_qualifies_the_field_name_with_the_model_table_by_default(): void
    {
        $query = Post::query();

        Text::make('title')->applyFilter($query, 'ilike', 'gamma%');

        $this->assertStringContainsString($query->getQuery()->getGrammar()->wrap('posts.title'), $query->toSql());
        $this->assertTitles(['Gamma release'], $query);
    }

    #[Test]
    public function it_keeps_a_filter_unambiguous_when_the_table_is_joined_to_itself(): void
    {
        $query = Post::query()->select('posts.*')->join('posts as earlier', 'earlier.id', '<', 'posts.id');

        Text::make('title')->applyFilter($query, '=', 'Beta Notes');

        $this->assertTitles(['Beta Notes'], $query);
    }

    /** @param Closure(): Field $field */
    #[Test]
    #[DataProvider('unsupported')]
    public function it_rejects_an_unsupported_operator_before_touching_the_query(Closure $field, string $operator, string $message): void
    {
        $query = Post::query();

        try {
            $field()->applyFilter($query, $operator, 'Alpha launch');
            $this->fail("The operator [{$operator}] was applied.");
        } catch (InvalidFieldException $exception) {
            $this->assertSame($message, $exception->getMessage());
        }

        $this->assertSame([], $query->getQuery()->wheres);
    }

    /** @return iterable<string, array{Closure(): Field, string, string}> */
    public static function unsupported(): iterable
    {
        yield 'pattern on a boolean' => [fn (): Field => Boolean::make('featured'), 'like', 'The field [featured] does not support the operator [like].'];
        yield 'comparison on a text' => [fn (): Field => Text::make('title'), '>', 'The field [title] does not support the operator [>].'];
        yield 'range on an enum' => [fn (): Field => Enum::make('status'), 'between', 'The field [status] does not support the operator [between].'];
        yield 'nullity on a non nullable field' => [fn (): Field => Text::make('title'), 'IS NULL', 'The field [title] does not support the operator [is null].'];
        yield 'any operator on a json' => [fn (): Field => Json::make('metadata'), '=', 'The field [metadata] does not support the operator [=].'];
        yield 'an unknown operator' => [fn (): Field => Text::make('title'), 'contains', 'Unknown filter operator [contains].'];
    }

    /** @param Closure(): Field $factory */
    private function bound(Closure $factory): Field
    {
        $field = $factory();
        $field->bind(new Post);

        return $field;
    }

    /** @param list<string> $expected */
    private function assertTitles(array $expected, Builder $query): void
    {
        $this->assertEqualsCanonicalizing($expected, $query->get()->pluck('title')->all());
    }
}
