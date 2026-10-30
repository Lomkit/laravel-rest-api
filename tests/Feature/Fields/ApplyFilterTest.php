<?php

namespace Lomkit\Rest\Tests\Feature\Fields;

use Closure;
use Illuminate\Database\Eloquent\Builder;
use Lomkit\Rest\Fields\Boolean;
use Lomkit\Rest\Fields\Date;
use Lomkit\Rest\Fields\DateTime;
use Lomkit\Rest\Fields\Enum;
use Lomkit\Rest\Fields\Field;
use Lomkit\Rest\Fields\Filtering\FilterOperator;
use Lomkit\Rest\Fields\ID;
use Lomkit\Rest\Fields\Integer;
use Lomkit\Rest\Fields\Number;
use Lomkit\Rest\Fields\Text;
use Lomkit\Rest\Tests\Fixtures\Publishing\Post;
use Lomkit\Rest\Tests\Fixtures\Publishing\PostStatus;
use Lomkit\Rest\Tests\Fixtures\Publishing\Publishing;
use Lomkit\Rest\Tests\PackageTestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;

class ApplyFilterTest extends PackageTestCase
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

        $columns = ['id', 'title', 'summary', 'status', 'featured', 'views', 'rating', 'published_on', 'published_at'];

        foreach ([
            [1, 'Alpha launch', 'First look', PostStatus::Draft, false, 10, 2.5, '2026-01-05', null],
            [2, 'Beta Notes', null, PostStatus::Review, true, 250, null, '2026-02-10', '2026-02-10 09:30:00'],
            [3, 'Gamma release', 'Stable build', PostStatus::Published, true, 1200, 4.5, '2026-03-15', '2026-03-15 14:00:00'],
            [4, 'DELTA Recap', null, PostStatus::Published, false, 75, 3.0, '2026-04-20', '2026-04-21 08:00:00'],
            [5, 'epsilon draft', 'Work in progress', PostStatus::Draft, false, 0, null, '2026-05-25', null],
        ] as $row) {
            Post::factory()->create(array_combine($columns, $row));
        }
    }

    /**
     * @param  Closure(): Field  $field
     * @param  list<string>  $expected
     */
    #[Test]
    #[DataProvider('filters')]
    public function it_filters_the_query_with_each_supported_operator(Closure $field, FilterOperator|string $operator, mixed $value, array $expected): void
    {
        $query = Post::query();

        $this->bound($field)->applyFilter($query, $operator, $value);

        $this->assertTitles($expected, $query);
    }

    /** @return iterable<string, array{Closure(): Field, FilterOperator|string, mixed, list<string>}> */
    public static function filters(): iterable
    {
        $title = fn (): Field => Text::make('title');
        $summary = fn (): Field => Text::make('summary')->nullable();
        $rating = fn (): Field => Number::make('rating')->nullable();
        $views = fn (): Field => Integer::make('views');
        $publishedOn = fn (): Field => Date::make('published_on');
        $publishedAt = fn (): Field => DateTime::make('published_at')->nullable();
        $status = fn (): Field => Enum::make('status');
        $featured = fn (): Field => Boolean::make('featured');
        $id = fn (): Field => ID::make('id');

        yield 'text =' => [$title, '=', 'Beta Notes', ['Beta Notes']];
        yield 'text !=' => [$title, '!=', 'Beta Notes', ['Alpha launch', 'Gamma release', 'DELTA Recap', 'epsilon draft']];
        yield 'number =' => [$rating, '=', 4.5, ['Gamma release']];
        yield 'number !=' => [$rating, '!=', 4.5, ['Alpha launch', 'DELTA Recap']];
        yield 'boolean =' => [$featured, '=', true, ['Beta Notes', 'Gamma release']];
        yield 'boolean !=' => [$featured, '!=', true, ['Alpha launch', 'DELTA Recap', 'epsilon draft']];
        yield 'enum =' => [$status, '=', 'published', ['Gamma release', 'DELTA Recap']];
        yield 'enum !=' => [$status, '!=', 'draft', ['Beta Notes', 'Gamma release', 'DELTA Recap']];
        yield 'id =' => [$id, '=', 3, ['Gamma release']];
        yield 'id !=' => [$id, FilterOperator::NotEqual, 3, ['Alpha launch', 'Beta Notes', 'DELTA Recap', 'epsilon draft']];
        yield 'number >' => [$rating, '>', 3, ['Gamma release']];
        yield 'number >=' => [$rating, '>=', 3, ['Gamma release', 'DELTA Recap']];
        yield 'number <' => [$rating, '<', 3, ['Alpha launch']];
        yield 'number <=' => [$rating, '<=', 3, ['Alpha launch', 'DELTA Recap']];
        yield 'integer >' => [$views, '>', 250, ['Gamma release']];
        yield 'integer >=' => [$views, '>=', 250, ['Beta Notes', 'Gamma release']];
        yield 'integer <' => [$views, '<', 75, ['Alpha launch', 'epsilon draft']];
        yield 'integer <=' => [$views, '<=', 75, ['Alpha launch', 'DELTA Recap', 'epsilon draft']];
        yield 'date >' => [$publishedOn, '>', '2026-03-31', ['DELTA Recap', 'epsilon draft']];
        yield 'date >=' => [$publishedOn, '>=', '2026-03-01', ['Gamma release', 'DELTA Recap', 'epsilon draft']];
        yield 'date <' => [$publishedOn, '<', '2026-02-01', ['Alpha launch']];
        yield 'date <=' => [$publishedOn, '<=', '2026-02-28', ['Alpha launch', 'Beta Notes']];
        yield 'datetime >' => [$publishedAt, '>', '2026-03-15 14:00:00', ['DELTA Recap']];
        yield 'datetime >=' => [$publishedAt, '>=', '2026-03-15 14:00:00', ['Gamma release', 'DELTA Recap']];
        yield 'datetime <' => [$publishedAt, '<', '2026-03-15 14:00:00', ['Beta Notes']];
        yield 'datetime <=' => [$publishedAt, '<=', '2026-03-15 14:00:00', ['Beta Notes', 'Gamma release']];
        yield 'text like' => [$title, 'like', '%o%', ['Beta Notes', 'epsilon draft']];
        yield 'text not like' => [$title, 'not like', '%o%', ['Alpha launch', 'Gamma release', 'DELTA Recap']];
        yield 'text ilike' => [$title, 'ilike', '%RE%', ['Gamma release', 'DELTA Recap']];
        yield 'text not ilike' => [$title, 'NOT ILIKE', '%RE%', ['Alpha launch', 'Beta Notes', 'epsilon draft']];
        yield 'text in' => [$title, 'in', ['Alpha launch', 'epsilon draft'], ['Alpha launch', 'epsilon draft']];
        yield 'text not in' => [$title, 'not in', ['Alpha launch', 'epsilon draft'], ['Beta Notes', 'Gamma release', 'DELTA Recap']];
        yield 'integer in' => [$views, 'in', [0, 75], ['DELTA Recap', 'epsilon draft']];
        yield 'integer not in' => [$views, 'not in', [0, 75], ['Alpha launch', 'Beta Notes', 'Gamma release']];
        yield 'enum in' => [$status, 'in', ['review', 'published'], ['Beta Notes', 'Gamma release', 'DELTA Recap']];
        yield 'enum not in' => [$status, 'not in', ['review', 'published'], ['Alpha launch', 'epsilon draft']];
        yield 'number between' => [$rating, 'between', [2.5, 3], ['Alpha launch', 'DELTA Recap']];
        yield 'number not between' => [$rating, 'not between', [2.5, 3], ['Gamma release']];
        yield 'date between' => [$publishedOn, FilterOperator::Between, ['2026-02-01', '2026-03-31'], ['Beta Notes', 'Gamma release']];
        yield 'date not between' => [$publishedOn, 'not between', ['2026-02-01', '2026-03-31'], ['Alpha launch', 'DELTA Recap', 'epsilon draft']];
        yield 'text is null' => [$summary, 'is null', null, ['Beta Notes', 'DELTA Recap']];
        yield 'text is not null' => [$summary, 'is not null', null, ['Alpha launch', 'Gamma release', 'epsilon draft']];
        yield 'number is null' => [$rating, 'is null', null, ['Beta Notes', 'epsilon draft']];
        yield 'number is not null' => [$rating, 'is not null', null, ['Alpha launch', 'Gamma release', 'DELTA Recap']];
        yield 'datetime is null' => [$publishedAt, 'is null', null, ['Alpha launch', 'epsilon draft']];
        yield 'datetime is not null' => [$publishedAt, 'is not null', null, ['Beta Notes', 'Gamma release', 'DELTA Recap']];
    }

    /**
     * @param  Closure(): Field  $field
     * @param  list<string>  $expected
     */
    #[Test]
    #[DataProvider('storedBoundaries')]
    public function it_matches_a_boundary_equal_to_a_value_stored_through_the_model_cast(Closure $field, string $operator, mixed $value, array $expected): void
    {
        $query = Post::query();

        $this->bound($field)->applyFilter($query, $operator, $value);

        $this->assertTitles($expected, $query);
    }

    /** @return iterable<string, array{Closure(): Field, string, mixed, list<string>}> */
    public static function storedBoundaries(): iterable
    {
        yield 'date =' => [fn (): Field => Date::make('published_on'), '=', '2026-02-10', ['Beta Notes']];
        yield 'date <=' => [fn (): Field => Date::make('published_on'), '<=', '2026-02-10', ['Alpha launch', 'Beta Notes']];
        yield 'date between' => [fn (): Field => Date::make('published_on'), 'between', ['2026-02-10', '2026-03-15'], ['Beta Notes', 'Gamma release']];
        yield 'datetime >= with a T separator' => [fn (): Field => DateTime::make('published_at'), '>=', '2026-03-15T14:00:00', ['Gamma release', 'DELTA Recap']];
        yield 'date = with a time part' => [fn (): Field => Date::make('published_on'), '=', '2026-02-10 15:45', ['Beta Notes']];
        yield 'date between with time parts' => [fn (): Field => Date::make('published_on'), 'between', ['2026-02-10 23:00', '2026-03-15 01:00'], ['Beta Notes', 'Gamma release']];
        yield 'datetime keeps the time part' => [fn (): Field => DateTime::make('published_at'), '>', '2026-02-10 10:00', ['Gamma release', 'DELTA Recap']];
        yield 'date = written without separators' => [fn (): Field => Date::make('published_on'), '=', '20260210', ['Beta Notes']];
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
        $this->assertEqualsCanonicalizing($expected, $query->pluck('title')->all());
    }
}
