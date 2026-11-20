<?php

namespace Lomkit\Rest\Tests\Feature\Text;

use Illuminate\Auth\GenericUser;
use Illuminate\Bus\PendingBatch;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Queue;
use Lomkit\Rest\Facades\Rest;
use Lomkit\Rest\Tests\Feature\Text\Stubs\ContainsPostResource;
use Lomkit\Rest\Tests\Feature\Text\Stubs\FullTextPostResource;
use Lomkit\Rest\Tests\Feature\Text\Stubs\MeasuredFullTextPostController;
use Lomkit\Rest\Tests\Fixtures\Publishing\Post;
use Lomkit\Rest\Tests\Fixtures\Publishing\PostStatus;
use Lomkit\Rest\Tests\Fixtures\ReceivedFields;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;

class TermlessTextTest extends FullTextTestCase
{
    private const array EMPTY_PAGE = [
        'current_page' => 1,
        'data' => [],
        'from' => null,
        'last_page' => 1,
        'per_page' => 50,
        'to' => null,
        'total' => 0,
        'meta' => [],
    ];

    private ReceivedFields $received;

    /** @var list<int> */
    private array $ids = [];

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        $this->received = $this->app->instance(ReceivedFields::class, new ReceivedFields);

        foreach (['Desk lamp', 'Glass vase', 'Floor lamp'] as $title) {
            $this->ids[] = $this->createPost($title, 'Brass shade')->id;
        }
    }

    /** @param array<string, string> $text */
    #[Test]
    #[DataProvider('termlessTexts')]
    public function it_matches_nothing_for_a_text_without_terms(string $resource, array $text): void
    {
        $response = $this->search($resource, ['text' => $text]);

        $response->assertOk()->assertExactJson(self::EMPTY_PAGE);
    }

    /** @return iterable<string, array{string, array<string, string>}> */
    public static function termlessTexts(): iterable
    {
        foreach (['Contains' => 'contains-posts', 'FullText' => 'full-text-posts'] as $strategy => $resource) {
            foreach (['*', '%', '_', '-'] as $value) {
                yield "{$value} in words on {$strategy}" => [$resource, ['value' => $value]];
                yield "{$value} in a phrase on {$strategy}" => [$resource, ['value' => $value, 'mode' => 'phrase']];
            }

            yield "punctuation and spaces on {$strategy}" => [$resource, ['value' => ' !!! ... # ']];
        }
    }

    /** @param array<string, string> $text */
    #[Test]
    #[DataProvider('fullTextModes')]
    public function it_matches_nothing_for_a_text_without_terms_in_every_full_text_mode(array $text): void
    {
        $this->requiresFullText();

        $response = $this->search('full-text-posts', ['text' => $text]);

        $response->assertOk()->assertExactJson(self::EMPTY_PAGE);
    }

    /** @return array<string, array{array<string, string>}> */
    public static function fullTextModes(): array
    {
        return [
            'prefix' => [['value' => '*', 'mode' => 'prefix']],
            'websearch' => [['value' => '-', 'mode' => 'websearch']],
            'websearch quotes' => [['value' => '"%"', 'mode' => 'websearch']],
        ];
    }

    /** @param array<string, mixed> $text */
    #[Test]
    #[DataProvider('blankTexts')]
    public function it_returns_the_normal_list_for_a_blank_text(string $resource, array $text): void
    {
        $absent = $this->search($resource, []);
        $blank = $this->search($resource, ['text' => $text]);

        $absent->assertOk()->assertJsonPath('data.*.id', array_reverse($this->ids));
        $this->assertSame($absent->json(), $blank->assertOk()->json());
    }

    /** @return iterable<string, array{string, array<string, mixed>}> */
    public static function blankTexts(): iterable
    {
        foreach (['Contains' => 'contains-posts', 'FullText' => 'full-text-posts'] as $strategy => $resource) {
            yield "an empty value on {$strategy}" => [$resource, ['value' => '']];
            yield "spaces on {$strategy}" => [$resource, ['value' => "  \t "]];
            yield "spaces in a phrase on {$strategy}" => [$resource, ['value' => '   ', 'mode' => 'phrase']];
            yield "a null value on {$strategy}" => [$resource, ['value' => null]];
            yield "no value on {$strategy}" => [$resource, []];
        }
    }

    #[Test]
    public function it_pages_renders_and_streams_nothing_for_a_programmatic_search_without_terms(): void
    {
        $search = Rest::search(ContainsPostResource::class)->as(new GenericUser(['id' => 1]))->payload(['text' => ['value' => '*']]);

        $page = $search->page();

        $this->assertSame([[], 0], [$page->items(), $page->total()]);
        $this->assertSame([], $search->render()['data']);
        $this->assertSame([], $search->lazy()->all());
    }

    #[Test]
    public function it_streams_the_whole_list_for_a_programmatic_search_with_a_blank_text(): void
    {
        $search = Rest::search(FullTextPostResource::class)->as(new GenericUser(['id' => 1]))->payload(['text' => ['value' => ' '], 'selects' => [['field' => 'id']]]);

        $this->assertSame(array_reverse($this->ids), array_map(fn (Model $post): mixed => $post->getKey(), $search->page()->items()));
        $this->assertSame(array_reverse($this->ids), array_column($search->lazy()->all(), 'id'));
    }

    #[Test]
    #[DataProvider('actions')]
    public function it_runs_an_action_on_no_row_for_a_text_without_terms(string $resource, string $action): void
    {
        $response = $this->postJson("/{$resource}/actions/{$action}", ['search' => ['text' => ['value' => '%']]]);

        $response->assertOk()->assertExactJson(['data' => ['impacted' => 0, 'result' => null]]);
        $this->assertSame([], $this->received->calls());
        $this->assertSame([PostStatus::Draft, PostStatus::Draft, PostStatus::Draft], Post::query()->orderBy('id')->pluck('status')->all());
    }

    /** @return array<string, array{string, string}> */
    public static function actions(): array
    {
        return [
            'an action on Contains' => ['contains-posts', 'publish-posts'],
            'an action on FullText' => ['full-text-posts', 'publish-posts'],
            'a queued action' => ['contains-posts', 'queued-publish-posts'],
        ];
    }

    #[Test]
    public function it_queues_and_batches_nothing_for_a_text_without_terms(): void
    {
        Queue::fake();
        Bus::fake();

        $queued = $this->postJson('/contains-posts/actions/queued-publish-posts', ['search' => ['text' => ['value' => '*']]]);
        $batched = $this->postJson('/contains-posts/actions/batched-publish-posts', ['search' => ['text' => ['value' => '*']]]);

        $queued->assertOk()->assertExactJson(['data' => ['impacted' => 0, 'result' => null]]);
        $batched->assertOk()->assertExactJson(['data' => ['batch' => $batched->json('data.batch'), 'impacted' => 0, 'result' => null]]);
        Queue::assertNothingPushed();
        Bus::assertBatched(fn (PendingBatch $batch): bool => $batch->jobs->isEmpty());
        Bus::assertBatchCount(1);
    }

    #[Test]
    public function it_counts_no_row_in_a_metric_for_a_text_without_terms(): void
    {
        Rest::resource('measured-full-text-posts', MeasuredFullTextPostController::class)->register();

        $this->postJson('/measured-full-text-posts/metrics/row-count', ['search' => ['text' => ['value' => '_']]])
            ->assertOk()
            ->assertExactJson(['data' => ['type' => 'count', 'value' => 0]]);
    }

    /** @param array<string, mixed> $search */
    #[Test]
    #[DataProvider('validatedSearches')]
    public function it_validates_a_text_without_terms_as_a_search_without_text(array $search): void
    {
        $absent = $this->search('full-text-posts', $search);
        $termless = $this->search('full-text-posts', ['text' => ['value' => '*'], ...$search]);

        $absent->assertUnprocessable();
        $this->assertSame($absent->json(), $termless->assertUnprocessable()->json());
    }

    /** @return array<string, array{array<string, mixed>}> */
    public static function validatedSearches(): array
    {
        return [
            'the relevance sort' => [['sorts' => [['field' => '@relevance']]]],
            'a source' => [['source' => 'engine']],
        ];
    }
}
