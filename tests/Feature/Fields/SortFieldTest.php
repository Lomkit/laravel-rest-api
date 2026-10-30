<?php

namespace Lomkit\Rest\Tests\Feature\Fields;

use Illuminate\Contracts\Database\Eloquent\Builder;
use Lomkit\Rest\Exceptions\InvalidFieldException;
use Lomkit\Rest\Fields\Enum;
use Lomkit\Rest\Fields\Integer;
use Lomkit\Rest\Fields\SortDirection;
use Lomkit\Rest\Fields\Text;
use Lomkit\Rest\Tests\Fixtures\Publishing\Post;
use Lomkit\Rest\Tests\Fixtures\Publishing\PostStatus;
use Lomkit\Rest\Tests\Fixtures\Publishing\Publishing;
use Lomkit\Rest\Tests\PackageTestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;

class SortFieldTest extends PackageTestCase
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

        Post::factory()->create(['title' => 'Annual report', 'status' => PostStatus::Draft, 'views' => 30, 'featured' => true]);
        Post::factory()->create(['title' => 'Spring launch', 'status' => PostStatus::Review, 'views' => 120, 'featured' => false]);
        Post::factory()->create(['title' => 'Team offsite', 'status' => PostStatus::Published, 'views' => 5, 'featured' => false]);
        Post::factory()->create(['title' => 'Quarterly report', 'status' => PostStatus::Published, 'views' => 75, 'featured' => true]);
    }

    /** @return array<string, array{string, list<string>}> */
    public static function directions(): array
    {
        return [
            'ascending' => ['asc', ['Team offsite', 'Annual report', 'Quarterly report', 'Spring launch']],
            'descending' => ['desc', ['Spring launch', 'Quarterly report', 'Annual report', 'Team offsite']],
        ];
    }

    /** @param list<string> $titles */
    #[Test]
    #[DataProvider('directions')]
    public function it_orders_by_the_qualified_column(string $direction, array $titles): void
    {
        $query = Post::query();

        Integer::make('views')->sort($query, $direction);

        $this->assertSame("select * from posts order by posts.views {$direction}", $this->sql($query));
        $this->assertSame($titles, $query->pluck('title')->all());
    }

    /** @return array<string, array{SortDirection|string, string}> */
    public static function spellings(): array
    {
        return [
            'lower case' => ['desc', 'desc'],
            'upper case' => ['ASC', 'asc'],
            'mixed case' => ['DeSc', 'desc'],
            'ascending case' => [SortDirection::Asc, 'asc'],
            'descending case' => [SortDirection::Desc, 'desc'],
        ];
    }

    #[Test]
    #[DataProvider('spellings')]
    public function it_accepts_a_direction_in_any_case_or_as_an_enum(SortDirection|string $direction, string $expected): void
    {
        $query = Post::query();

        Integer::make('views')->sort($query, $direction);

        $this->assertSame("select * from posts order by posts.views {$expected}", $this->sql($query));
    }

    /** @return array<string, array{string}> */
    public static function unknownDirections(): array
    {
        return [
            'word' => ['up'],
            'long form' => ['ascending'],
            'padded' => [' asc'],
            'empty' => [''],
        ];
    }

    #[Test]
    #[DataProvider('unknownDirections')]
    public function it_rejects_an_unknown_direction(string $direction): void
    {
        $this->expectException(InvalidFieldException::class);
        $this->expectExceptionMessage("Unknown sort direction [{$direction}]. Use asc or desc.");

        Integer::make('views')->sort(Post::query(), $direction);
    }

    #[Test]
    public function it_rejects_an_unknown_direction_before_calling_the_custom_sort(): void
    {
        $popularity = Integer::make('popularity')->virtual()->sortUsing(fn () => $this->fail('The custom sort ran.'));

        $this->expectException(InvalidFieldException::class);
        $this->expectExceptionMessage('Unknown sort direction [sideways]. Use asc or desc.');

        $popularity->sort(Post::query(), 'sideways');
    }

    /** @return array<string, array{string, list<string>}> */
    public static function popularity(): array
    {
        return [
            'ascending' => ['asc', ['Team offsite', 'Spring launch', 'Annual report', 'Quarterly report']],
            'descending' => ['desc', ['Quarterly report', 'Annual report', 'Spring launch', 'Team offsite']],
        ];
    }

    /** @param list<string> $titles */
    #[Test]
    #[DataProvider('popularity')]
    public function it_replaces_the_order_with_the_custom_sort(string $direction, array $titles): void
    {
        $query = Post::query();
        $popularity = Integer::make('popularity')->virtual()->sortUsing(function (Builder $query, string $direction): void {
            $query->orderBy('featured', $direction)->orderBy('views', $direction);
        });

        $popularity->sort($query, $direction);

        $this->assertSame("select * from posts order by featured {$direction}, views {$direction}", $this->sql($query));
        $this->assertSame($titles, $query->pluck('title')->all());
    }

    #[Test]
    #[DataProvider('spellings')]
    public function it_hands_the_direction_string_to_the_custom_sort(SortDirection|string $direction, string $expected): void
    {
        $received = null;
        $popularity = Integer::make('popularity')->virtual()->sortUsing(function (Builder $query, string $direction) use (&$received): void {
            $received = $direction;
        });

        $popularity->sort(Post::query(), $direction);

        $this->assertSame($expected, $received);
    }

    #[Test]
    public function it_orders_by_the_column_passed_instead_of_its_name(): void
    {
        $query = Post::query();

        Text::make('headline')->sort($query, 'asc', 'posts.title');

        $this->assertSame('select * from posts order by posts.title asc', $this->sql($query));
        $this->assertSame(['Annual report', 'Quarterly report', 'Spring launch', 'Team offsite'], $query->pluck('title')->all());
    }

    #[Test]
    public function it_appends_to_the_existing_order(): void
    {
        $query = Post::query();

        Enum::make('status', PostStatus::class)->sort($query, 'desc');
        Integer::make('views')->sort($query, 'asc');

        $this->assertSame('select * from posts order by posts.status desc, posts.views asc', $this->sql($query));
        $this->assertSame(['Spring launch', 'Team offsite', 'Quarterly report', 'Annual report'], $query->pluck('title')->all());
    }

    private function sql(Builder $query): string
    {
        return str_replace(['"', '`'], '', $query->toSql());
    }
}
