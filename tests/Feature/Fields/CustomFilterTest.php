<?php

namespace Lomkit\Rest\Tests\Feature\Fields;

use Illuminate\Contracts\Database\Eloquent\Builder;
use Lomkit\Rest\Contracts\Filter;
use Lomkit\Rest\Exceptions\InvalidFieldException;
use Lomkit\Rest\Fields\Filtering\FilterOperator;
use Lomkit\Rest\Fields\Text;
use Lomkit\Rest\Tests\Fixtures\Publishing\Post;
use Lomkit\Rest\Tests\Fixtures\Publishing\PostStatus;
use Lomkit\Rest\Tests\Fixtures\Publishing\Publishing;
use Lomkit\Rest\Tests\PackageTestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;

class CustomFilterTest extends PackageTestCase
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

        Post::factory()->create(['title' => 'Annual report', 'summary' => 'Figures for the year', 'status' => PostStatus::Draft]);
        Post::factory()->create(['title' => 'Spring launch', 'summary' => 'A report on the launch', 'status' => PostStatus::Review]);
        Post::factory()->create(['title' => 'Team offsite', 'status' => PostStatus::Published, 'featured' => true]);
        Post::factory()->create(['title' => 'Quarterly report', 'status' => PostStatus::Published]);
    }

    /** @return array<string, array{Text}> */
    public static function headlines(): array
    {
        return [
            'closure' => [Text::make('headline')->virtual()->filterUsing(function (Builder $query, string $operator, mixed $value): void {
                $query->where('title', $operator, $value)->orWhere('summary', $operator, $value);
            })],
            'filter object' => [Text::make('headline')->virtual()->filterUsing(new class implements Filter
            {
                #[\Override]
                public function apply(Builder $query, string $operator, mixed $value): void
                {
                    $query->where('title', $operator, $value)->orWhere('summary', $operator, $value);
                }
            })],
        ];
    }

    #[Test]
    #[DataProvider('headlines')]
    public function it_groups_the_custom_filter_after_an_and(Text $headline): void
    {
        $query = Post::query()->where('status', 'published');

        $headline->filter($query, 'like', '%report%');

        $this->assertSame('select * from posts where status = ? and (title like ? or summary like ?)', $this->sql($query));
        $this->assertEqualsCanonicalizing(['Quarterly report'], $query->pluck('title')->all());
    }

    #[Test]
    #[DataProvider('headlines')]
    public function it_groups_the_custom_filter_after_an_or(Text $headline): void
    {
        $query = Post::query()->where('status', 'draft');

        $headline->filter($query, 'like', '%launch%', 'or');

        $this->assertSame('select * from posts where status = ? or (title like ? or summary like ?)', $this->sql($query));
        $this->assertEqualsCanonicalizing(['Annual report', 'Spring launch'], $query->pluck('title')->all());
    }

    #[Test]
    #[DataProvider('headlines')]
    public function it_stays_one_condition_inside_a_nested_group(Text $headline): void
    {
        $query = Post::query()->where('status', 'published')->where(function (Builder $query) use ($headline): void {
            $query->where('featured', true);
            $headline->filter($query, 'like', '%report%', 'or');
        });

        $this->assertSame('select * from posts where status = ? and (featured = ? or (title like ? or summary like ?))', $this->sql($query));
        $this->assertEqualsCanonicalizing(['Team offsite', 'Quarterly report'], $query->pluck('title')->all());
    }

    /** @return array<string, array{string, mixed, string, list<string>}> */
    public static function reusedFields(): array
    {
        return [
            'like' => ['like', '%report%', 'select * from posts where (posts.title like ? or posts.summary like ?)', ['Annual report', 'Spring launch', 'Quarterly report']],
            'in' => ['in', ['Team offsite', 'A report on the launch'], 'select * from posts where (posts.title in (?, ?) or posts.summary in (?, ?))', ['Spring launch', 'Team offsite']],
        ];
    }

    /** @param list<string> $titles */
    #[Test]
    #[DataProvider('reusedFields')]
    public function it_lets_the_custom_filter_reuse_other_fields(string $operator, mixed $value, string $sql, array $titles): void
    {
        $title = Text::make('title');
        $summary = Text::make('summary')->nullable();
        $headline = Text::make('headline')->virtual()->filterUsing(function (Builder $query, string $operator, mixed $value) use ($title, $summary): void {
            $title->applyFilter($query, $operator, $value);
            $summary->applyFilter($query, $operator, $value, 'or');
        });
        $query = Post::query();

        $headline->filter($query, $operator, $value);

        $this->assertSame($sql, $this->sql($query));
        $this->assertEqualsCanonicalizing($titles, $query->pluck('title')->all());
    }

    #[Test]
    public function it_falls_back_to_the_built_in_condition_without_a_custom_filter(): void
    {
        $query = Post::query()->where('status', 'published');

        Text::make('title')->filter($query, 'like', '%report%', 'or');

        $this->assertSame('select * from posts where status = ? or posts.title like ?', $this->sql($query));
        $this->assertEqualsCanonicalizing(['Annual report', 'Team offsite', 'Quarterly report'], $query->pluck('title')->all());
    }

    #[Test]
    public function it_passes_the_column_to_the_built_in_condition(): void
    {
        $query = Post::query();

        Text::make('headline')->filter($query, '=', 'Team offsite', 'and', 'posts.title');

        $this->assertSame('select * from posts where posts.title = ?', $this->sql($query));
        $this->assertSame(['Team offsite'], $query->pluck('title')->all());
    }

    /** @return array<string, array{FilterOperator|string, string, mixed}> */
    public static function receivedArguments(): array
    {
        return [
            'enum case' => [FilterOperator::In, 'in', ['Team offsite', 'Spring launch']],
            'upper case string' => ['NOT LIKE', 'not like', '%Report%'],
            'null value' => ['=', '=', null],
            'nested array' => ['=', '=', ['from' => '2026-01-01', 'to' => ['2026-12-31']]],
        ];
    }

    #[Test]
    #[DataProvider('receivedArguments')]
    public function it_hands_the_operator_string_and_the_raw_value_to_the_custom_filter(FilterOperator|string $operator, string $expected, mixed $value): void
    {
        $received = null;
        $headline = Text::make('headline')->virtual()->filterUsing(function (Builder $query, string $operator, mixed $value) use (&$received): void {
            $received = [$operator, $value];
        });

        $headline->filter(Post::query(), $operator, $value);

        $this->assertSame([$expected, $value], $received);
    }

    #[Test]
    public function it_rejects_an_unknown_operator_before_calling_the_custom_filter(): void
    {
        $headline = Text::make('headline')->virtual()->filterUsing(fn () => $this->fail('The custom filter ran.'));

        $this->expectException(InvalidFieldException::class);
        $this->expectExceptionMessage('Unknown filter operator [contains].');

        $headline->filter(Post::query(), 'contains', 'report');
    }

    #[Test]
    public function it_adds_no_condition_when_the_custom_filter_adds_none(): void
    {
        $query = Post::query()->where('status', 'published');

        Text::make('headline')->virtual()->filterUsing(function (): void {})->filter($query, 'like', '%report%', 'or');

        $this->assertSame('select * from posts where status = ?', $this->sql($query));
        $this->assertEqualsCanonicalizing(['Team offsite', 'Quarterly report'], $query->pluck('title')->all());
    }

    private function sql(Builder $query): string
    {
        return str_replace(['"', '`', '::text'], '', $query->toSql());
    }
}
