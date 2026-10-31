<?php

namespace Lomkit\Rest\Tests\Feature\Search;

use Illuminate\Support\Facades\DB;
use Lomkit\Rest\Http\Requests\RestRequest;
use Lomkit\Rest\Http\RestContext;
use Lomkit\Rest\Tests\Fixtures\Publishing\Post;
use Lomkit\Rest\Tests\Fixtures\Publishing\PostResource;
use Lomkit\Rest\Tests\Fixtures\Publishing\PostStatus;
use Lomkit\Rest\Tests\Fixtures\Publishing\Publishing;
use Lomkit\Rest\Tests\PackageTestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;

class FilterOperatorsTest extends PackageTestCase
{
    private const string ANNUAL = 'Annual report';

    private const string SPRING = 'Spring launch';

    private const string OFFSITE = 'Team offsite';

    private const string QUARTERLY = 'Quarterly report';

    /** @var array<string, Post> */
    private array $posts = [];

    #[\Override]
    protected function fixtures(): array
    {
        return [new Publishing];
    }

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        $this->createPost(self::ANNUAL, 'Figures for the year', PostStatus::Draft, false, 10, 4.5, '2026-01-10', '2026-01-10 08:30:00');
        $this->createPost(self::SPRING, null, PostStatus::Review, true, 25, null, '2026-03-01', null);
        $this->createPost(self::OFFSITE, 'A day out of the office', PostStatus::Published, false, 40, 3.0, '2026-03-15', '2026-03-15 18:00:00');
        $this->createPost(self::QUARTERLY, 'Figures for the quarter', PostStatus::Published, true, 25, 2.5, '2026-04-01', '2026-04-01 12:00:00');
    }

    /** @return array<string, array{string, string, mixed, list<string>}> */
    public static function matrix(): array
    {
        return [
            'id =' => ['id', '=', self::SPRING, [self::SPRING]],
            'id !=' => ['id', '!=', self::SPRING, [self::ANNUAL, self::OFFSITE, self::QUARTERLY]],
            'id in' => ['id', 'in', [self::ANNUAL, self::OFFSITE], [self::ANNUAL, self::OFFSITE]],
            'id not in' => ['id', 'not in', [self::ANNUAL, self::OFFSITE], [self::SPRING, self::QUARTERLY]],
            'text =' => ['title', '=', self::OFFSITE, [self::OFFSITE]],
            'text !=' => ['title', '!=', self::OFFSITE, [self::ANNUAL, self::SPRING, self::QUARTERLY]],
            'text like a part' => ['title', 'like', '%report%', [self::ANNUAL, self::QUARTERLY]],
            'text like a start' => ['title', 'like', 'Quarterly%', [self::QUARTERLY]],
            'text not like a part' => ['title', 'not like', '%report%', [self::SPRING, self::OFFSITE]],
            'text not like a start' => ['title', 'not like', 'Spring%', [self::ANNUAL, self::OFFSITE, self::QUARTERLY]],
            'text ilike' => ['title', 'ilike', '%REPORT', [self::ANNUAL, self::QUARTERLY]],
            'text not ilike' => ['title', 'not ilike', 'annual%', [self::SPRING, self::OFFSITE, self::QUARTERLY]],
            'text in' => ['title', 'in', [self::ANNUAL, self::OFFSITE], [self::ANNUAL, self::OFFSITE]],
            'text not in' => ['title', 'not in', [self::ANNUAL, self::OFFSITE], [self::SPRING, self::QUARTERLY]],
            'nullable text =' => ['summary', '=', 'A day out of the office', [self::OFFSITE]],
            'nullable text !=' => ['summary', '!=', 'Figures for the year', [self::OFFSITE, self::QUARTERLY]],
            'nullable text like' => ['summary', 'like', 'Figures%', [self::ANNUAL, self::QUARTERLY]],
            'nullable text not like' => ['summary', 'not like', 'Figures%', [self::OFFSITE]],
            'nullable text ilike' => ['summary', 'ilike', '%OFFICE', [self::OFFSITE]],
            'nullable text not ilike' => ['summary', 'not ilike', '%OFFICE', [self::ANNUAL, self::QUARTERLY]],
            'nullable text in' => ['summary', 'in', ['Figures for the year', 'Figures for the quarter'], [self::ANNUAL, self::QUARTERLY]],
            'nullable text not in' => ['summary', 'not in', ['Figures for the year'], [self::OFFSITE, self::QUARTERLY]],
            'nullable text is null' => ['summary', 'is null', null, [self::SPRING]],
            'nullable text is not null' => ['summary', 'is not null', null, [self::ANNUAL, self::OFFSITE, self::QUARTERLY]],
            'enum =' => ['status', '=', 'published', [self::OFFSITE, self::QUARTERLY]],
            'enum !=' => ['status', '!=', 'published', [self::ANNUAL, self::SPRING]],
            'enum in' => ['status', 'in', ['draft', 'review'], [self::ANNUAL, self::SPRING]],
            'enum not in' => ['status', 'not in', ['draft'], [self::SPRING, self::OFFSITE, self::QUARTERLY]],
            'boolean =' => ['featured', '=', true, [self::SPRING, self::QUARTERLY]],
            'boolean !=' => ['featured', '!=', true, [self::ANNUAL, self::OFFSITE]],
            'integer =' => ['views', '=', 25, [self::SPRING, self::QUARTERLY]],
            'integer !=' => ['views', '!=', 25, [self::ANNUAL, self::OFFSITE]],
            'integer >' => ['views', '>', 25, [self::OFFSITE]],
            'integer >=' => ['views', '>=', 25, [self::SPRING, self::OFFSITE, self::QUARTERLY]],
            'integer <' => ['views', '<', 25, [self::ANNUAL]],
            'integer <=' => ['views', '<=', 25, [self::ANNUAL, self::SPRING, self::QUARTERLY]],
            'integer between' => ['views', 'between', [10, 25], [self::ANNUAL, self::SPRING, self::QUARTERLY]],
            'integer not between' => ['views', 'not between', [10, 25], [self::OFFSITE]],
            'integer in' => ['views', 'in', [10, 40], [self::ANNUAL, self::OFFSITE]],
            'integer not in' => ['views', 'not in', [10, 40], [self::SPRING, self::QUARTERLY]],
            'nullable number =' => ['rating', '=', 4.5, [self::ANNUAL]],
            'nullable number !=' => ['rating', '!=', 4.5, [self::OFFSITE, self::QUARTERLY]],
            'nullable number >' => ['rating', '>', 3, [self::ANNUAL]],
            'nullable number >=' => ['rating', '>=', 3, [self::ANNUAL, self::OFFSITE]],
            'nullable number <' => ['rating', '<', 3, [self::QUARTERLY]],
            'nullable number <=' => ['rating', '<=', 3, [self::OFFSITE, self::QUARTERLY]],
            'nullable number between' => ['rating', 'between', [3, 4.5], [self::ANNUAL, self::OFFSITE]],
            'nullable number not between' => ['rating', 'not between', [3, 4.5], [self::QUARTERLY]],
            'nullable number in' => ['rating', 'in', [2.5, 3], [self::OFFSITE, self::QUARTERLY]],
            'nullable number not in' => ['rating', 'not in', [2.5], [self::ANNUAL, self::OFFSITE]],
            'nullable number is null' => ['rating', 'is null', null, [self::SPRING]],
            'nullable number is not null' => ['rating', 'is not null', null, [self::ANNUAL, self::OFFSITE, self::QUARTERLY]],
            'date =' => ['published_on', '=', '2026-03-01', [self::SPRING]],
            'date !=' => ['published_on', '!=', '2026-03-01', [self::ANNUAL, self::OFFSITE, self::QUARTERLY]],
            'date >' => ['published_on', '>', '2026-03-01', [self::OFFSITE, self::QUARTERLY]],
            'date >=' => ['published_on', '>=', '2026-03-01', [self::SPRING, self::OFFSITE, self::QUARTERLY]],
            'date <' => ['published_on', '<', '2026-03-01', [self::ANNUAL]],
            'date <=' => ['published_on', '<=', '2026-03-01', [self::ANNUAL, self::SPRING]],
            'date between' => ['published_on', 'between', ['2026-03-01', '2026-03-15'], [self::SPRING, self::OFFSITE]],
            'date not between' => ['published_on', 'not between', ['2026-03-01', '2026-03-15'], [self::ANNUAL, self::QUARTERLY]],
            'nullable datetime =' => ['published_at', '=', '2026-03-15 18:00:00', [self::OFFSITE]],
            'nullable datetime !=' => ['published_at', '!=', '2026-03-15 18:00:00', [self::ANNUAL, self::QUARTERLY]],
            'nullable datetime >' => ['published_at', '>', '2026-03-15 18:00:00', [self::QUARTERLY]],
            'nullable datetime >=' => ['published_at', '>=', '2026-03-15 18:00:00', [self::OFFSITE, self::QUARTERLY]],
            'nullable datetime <' => ['published_at', '<', '2026-03-15 18:00:00', [self::ANNUAL]],
            'nullable datetime <=' => ['published_at', '<=', '2026-03-15 18:00:00', [self::ANNUAL, self::OFFSITE]],
            'nullable datetime between' => ['published_at', 'between', ['2026-01-10 08:30:00', '2026-03-15 18:00:00'], [self::ANNUAL, self::OFFSITE]],
            'nullable datetime not between' => ['published_at', 'not between', ['2026-01-10 08:30:00', '2026-03-15 18:00:00'], [self::QUARTERLY]],
            'nullable datetime is null' => ['published_at', 'is null', null, [self::SPRING]],
            'nullable datetime is not null' => ['published_at', 'is not null', null, [self::ANNUAL, self::OFFSITE, self::QUARTERLY]],
        ];
    }

    /** @param list<string> $titles */
    #[Test]
    #[DataProvider('matrix')]
    public function it_filters_each_type_with_each_operator_it_supports(string $field, string $operator, mixed $value, array $titles): void
    {
        $filter = ['field' => $field, 'operator' => $operator];

        if ($value !== null) {
            $filter['value'] = $field === 'id' ? $this->ids($value) : $value;
        }

        $response = $this->postJson('/api/posts/search', ['search' => ['filters' => [$filter], 'sorts' => [['field' => 'id']]]]);

        $response->assertOk()->assertJsonPath('data.*.id', $this->ids($titles));
    }

    #[Test]
    public function it_covers_every_operator_of_every_filterable_field_of_the_posts(): void
    {
        $resource = (new RestContext(RestRequest::create('/api/posts/search', 'POST'), $this->app))->resource(PostResource::class);
        $declared = [];

        foreach ($resource->getFields($resource->context()->request)->filterable() as $field) {
            foreach ($field->filterOperators() as $operator) {
                $declared[] = $field->name.' '.$operator->value;
            }
        }

        $covered = array_map(fn (array $case): string => $case[0].' '.$case[1], array_values(self::matrix()));

        $this->assertEqualsCanonicalizing($declared, array_values(array_unique($covered)));
    }

    #[Test]
    public function it_matches_like_with_the_case_rules_of_the_database(): void
    {
        $like = $this->postJson('/api/posts/search', ['search' => ['filters' => [['field' => 'title', 'operator' => 'like', 'value' => 'annual%']]]]);
        $ilike = $this->postJson('/api/posts/search', ['search' => ['filters' => [['field' => 'title', 'operator' => 'ilike', 'value' => 'annual%']]]]);

        $like->assertOk()->assertJsonPath('data.*.id', DB::connection()->getDriverName() === 'pgsql' ? [] : $this->ids([self::ANNUAL]));
        $ilike->assertOk()->assertJsonPath('data.*.id', $this->ids([self::ANNUAL]));
    }

    private function createPost(string $title, ?string $summary, PostStatus $status, bool $featured, int $views, ?float $rating, string $publishedOn, ?string $publishedAt): void
    {
        $this->posts[$title] = Post::factory()->create([
            'title' => $title,
            'summary' => $summary,
            'status' => $status,
            'featured' => $featured,
            'views' => $views,
            'rating' => $rating,
            'published_on' => $publishedOn,
            'published_at' => $publishedAt,
        ]);
    }

    /** @return int|list<int> */
    private function ids(mixed $titles): int|array
    {
        if (is_string($titles)) {
            return $this->posts[$titles]->id;
        }

        return array_map(fn (mixed $title): int => $this->posts[(string) $title]->id, is_array($titles) ? array_values($titles) : []);
    }
}
