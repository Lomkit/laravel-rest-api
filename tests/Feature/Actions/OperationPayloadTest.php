<?php

namespace Lomkit\Rest\Tests\Feature\Actions;

use Carbon\CarbonImmutable;
use LogicException;
use Lomkit\Rest\Actions\OperationNode;
use Lomkit\Rest\Facades\Rest;
use Lomkit\Rest\Fields\Filtering\FilterOperator;
use Lomkit\Rest\Http\Requests\OperateRequest;
use Lomkit\Rest\Search\FilterCondition;
use Lomkit\Rest\Search\SearchNode;
use Lomkit\Rest\Tests\Feature\Actions\Stubs\EarlyReadingOperateRequest;
use Lomkit\Rest\Tests\Feature\Actions\Stubs\FailureIgnoringOperateRequest;
use Lomkit\Rest\Tests\Feature\Actions\Stubs\PayloadReadingOperateRequest;
use Lomkit\Rest\Tests\Feature\Validation\Stubs\PayloadPostController;
use Lomkit\Rest\Tests\Fixtures\Publishing\ChangeStatus;
use Lomkit\Rest\Tests\Fixtures\Publishing\Post;
use Lomkit\Rest\Tests\Fixtures\Publishing\PostStatus;
use Lomkit\Rest\Tests\Fixtures\Publishing\Publishing;
use Lomkit\Rest\Tests\Fixtures\Workspace\Note;
use Lomkit\Rest\Tests\Fixtures\Workspace\Workspace;
use Lomkit\Rest\Tests\PackageTestCase;
use Lomkit\Rest\Validation\KeyList;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;

class OperationPayloadTest extends PackageTestCase
{
    #[\Override]
    protected function fixtures(): array
    {
        return [new Publishing, new Workspace];
    }

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        PayloadReadingOperateRequest::$read = null;
        PayloadReadingOperateRequest::$validated = null;
        $this->app->bind(OperateRequest::class, PayloadReadingOperateRequest::class);
    }

    #[Test]
    public function it_reads_a_classic_operation_as_a_typed_node(): void
    {
        Post::factory()->create(['id' => 1, 'title' => 'Annual report', 'status' => PostStatus::Draft]);

        $response = $this->postJson('/api/posts/actions/change-status', [
            'search' => ['filters' => [['field' => 'title', 'value' => 'Annual report']]],
            'fields' => [['name' => 'status', 'value' => 'published'], ['name' => 'at', 'value' => '2026-03-01 10:00:00']],
        ]);

        $response->assertOk()->assertExactJson(['data' => ['impacted' => 1]]);
        $read = $this->read();
        $fields = $read->fields->toArray();
        $this->assertInstanceOf(ChangeStatus::class, $read->action);
        $this->assertSame(['status', 'at'], array_keys($fields));
        $this->assertSame(PostStatus::Published, $fields['status']);
        $this->assertInstanceOf(CarbonImmutable::class, $fields['at']);
        $this->assertSame('2026-03-01 10:00:00', $fields['at']->format('Y-m-d H:i:s'));
        $this->assertInstanceOf(SearchNode::class, $read->target);
        $this->assertCount(1, $read->target->filters->filters);
        $filter = $read->target->filters->filters[0];
        $this->assertInstanceOf(FilterCondition::class, $filter);
        $this->assertSame(['title', FilterOperator::Equal, 'Annual report'], [$filter->path, $filter->operator, $filter->value]);
    }

    #[Test]
    public function it_reads_a_missing_search_as_an_empty_search(): void
    {
        $this->postJson('/api/posts/actions/publish-posts', [])->assertOk();

        $this->assertEquals(SearchNode::empty(), $this->read()->target);
        $this->assertSame(['notify' => true], $this->read()->fields->toArray());
        $this->assertSame([], PayloadReadingOperateRequest::$validated);
    }

    #[Test]
    public function it_reads_the_keys_of_a_targeted_operation(): void
    {
        Note::factory()->create(['id' => 1, 'team' => 'red']);
        Note::factory()->create(['id' => 2, 'team' => 'red']);

        $response = $this->postJson('/api/team-notes/actions/pin-named-notes', ['resources' => [2, '1']]);

        $response->assertOk()->assertExactJson(['data' => ['impacted' => 2]]);
        $read = $this->read();
        $this->assertInstanceOf(KeyList::class, $read->target);
        $this->assertSame([2, 1], $read->target->keys);
        $this->assertSame($read->target, $read->keys());
    }

    #[Test]
    public function it_reads_a_standalone_operation_without_a_target(): void
    {
        Rest::resource('payload-posts', PayloadPostController::class)->register();

        $response = $this->postJson('/payload-posts/actions/attach-payload', ['fields' => [['name' => 'reason', 'value' => 'spam']]]);

        $response->assertOk()->assertExactJson(['data' => ['impacted' => 0]]);
        $read = $this->read();
        $this->assertNull($read->target);
        $this->assertSame(['reason' => 'spam'], $read->fields->toArray());
        $this->expectExceptionObject(new LogicException('The request has no resource keys.'));

        $read->keys();
    }

    #[Test]
    public function it_refuses_to_read_the_operation_before_validation(): void
    {
        $this->expectExceptionObject(new LogicException('The operation was read before the request passed validation.'));

        (new OperateRequest)->payload();
    }

    #[Test]
    public function it_refuses_to_read_the_operation_while_the_request_validates(): void
    {
        $this->app->bind(OperateRequest::class, EarlyReadingOperateRequest::class);
        $this->withoutExceptionHandling();

        $this->expectExceptionObject(new LogicException('The operation was read before the request passed validation.'));

        $this->postJson('/api/posts/actions/publish-posts', []);
    }

    /** @param array<string, mixed> $body */
    #[Test]
    #[DataProvider('failedOperations')]
    public function it_refuses_to_read_an_operation_that_failed_validation(string $uri, array $body): void
    {
        $this->app->bind(OperateRequest::class, FailureIgnoringOperateRequest::class);
        $this->withoutExceptionHandling();

        $this->expectExceptionObject(new LogicException('The operation was read before the request passed validation.'));

        $this->postJson($uri, $body);
    }

    /** @return array<string, array{string, array<string, mixed>}> */
    public static function failedOperations(): array
    {
        return [
            'a partial parse' => ['/api/posts/actions/publish-posts', ['fields' => [['name' => 'pinned', 'value' => true]]]],
            'a recorded violation' => ['/api/posts/actions/publish-posts', ['fields' => [['name' => 'notify', 'value' => true, 'default' => false]]]],
            'fields that are not an array' => ['/api/posts/actions/publish-posts', ['fields' => null]],
            'keys that did not parse' => ['/api/team-notes/actions/pin-named-notes', ['resources' => ['one']]],
        ];
    }

    private function read(): OperationNode
    {
        $this->assertInstanceOf(OperationNode::class, PayloadReadingOperateRequest::$read);

        return PayloadReadingOperateRequest::$read;
    }
}
