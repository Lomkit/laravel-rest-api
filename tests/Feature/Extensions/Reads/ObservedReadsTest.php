<?php

namespace Lomkit\Rest\Tests\Feature\Extensions\Reads;

use Illuminate\Auth\GenericUser;
use Illuminate\Database\Eloquent\Model;
use Lomkit\Rest\Exceptions\MissingColumnException;
use Lomkit\Rest\Facades\Rest;
use Lomkit\Rest\Http\Routing\Endpoint;
use Lomkit\Rest\Tests\Feature\Authorization\HelpdeskTestCase;
use Lomkit\Rest\Tests\Feature\Events\Stubs\EventLog;
use Lomkit\Rest\Tests\Feature\Extensions\Stubs\ChangingReadObserver;
use Lomkit\Rest\Tests\Feature\Extensions\Stubs\FailingReadObserver;
use Lomkit\Rest\Tests\Feature\Extensions\Stubs\ObservedTicketController;
use Lomkit\Rest\Tests\Feature\Extensions\Stubs\ObservedTicketResource;
use Lomkit\Rest\Tests\Feature\Extensions\Stubs\OpenTicketCount;
use Lomkit\Rest\Tests\Feature\Extensions\Stubs\RecordingReadObserver;
use Lomkit\Rest\Tests\Feature\Extensions\Stubs\TeamReadingObserver;
use Lomkit\Rest\Tests\Feature\Extensions\Stubs\WriteLog;
use Lomkit\Rest\Tests\Fixtures\Extensions\ExtensionLog;
use Lomkit\Rest\Tests\Fixtures\Helpdesk\Team;
use Lomkit\Rest\Tests\Fixtures\Helpdesk\TicketPolicy;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use RuntimeException;

final class ObservedReadsTest extends HelpdeskTestCase
{
    private const array SEARCH = ['search' => [
        'filters' => [['field' => 'status', 'value' => 'open']],
        'includes' => [['relation' => 'team']],
        'sorts' => [['field' => 'id', 'direction' => 'asc']],
        'limit' => 10,
    ]];

    private GenericUser $lead;

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        ExtensionLog::reset();
        EventLog::reset();
        WriteLog::reset();
        $this->travelTo('2026-11-08 10:00:00');
        $this->seedTickets();
        Rest::resource('observed-tickets', ObservedTicketController::class)->register();
        $this->lead = new GenericUser(['id' => TicketPolicy::LEAD]);
        $this->actingAs($this->lead);
        config(['rest.extensions' => ['reads' => RecordingReadObserver::class]]);
    }

    #[\Override]
    protected function tearDown(): void
    {
        ExtensionLog::reset();
        EventLog::reset();
        WriteLog::reset();

        parent::tearDown();
    }

    #[Test]
    public function it_notifies_a_search_after_its_page_is_computed(): void
    {
        $this->postJson('/observed-tickets/search', self::SEARCH)->assertOk();

        $this->assertSame(['searching', 'searched 1,3 of 2', 'read search 1 Printer jam,3 Printer toner of 2'], EventLog::$lines);
        $context = WriteLog::$reads[0];
        $this->assertCount(1, WriteLog::$reads);
        $this->assertInstanceOf(ObservedTicketResource::class, $context->resource);
        $this->assertSame(Endpoint::Search, $context->endpoint);
        $this->assertSame($this->lead, $context->user);
        $this->assertSame([2, 1, 10], [$context->total, $context->page, $context->perPage]);
        $this->assertSame([[1, 'Billing'], [3, 'Billing']], array_map(fn (Model $row): array => [$row->getKey(), $row->getRelation('team')?->getAttribute('name')], $context->rows));
        $this->assertNull($context->metric);
        $this->assertNull($context->result);
        $this->assertNull($context->details);
    }

    /** @return array<string, array{string}> */
    public static function programmaticSearches(): array
    {
        return [
            'page' => ['page'],
            'render' => ['render'],
        ];
    }

    #[Test]
    #[DataProvider('programmaticSearches')]
    public function it_notifies_rest_search_page_and_render(string $method): void
    {
        $other = new GenericUser(['id' => 7]);

        Rest::search(ObservedTicketResource::class)->as($other)->payload(self::SEARCH['search'])->{$method}();

        $this->assertSame(['searching', 'searched 1,3 of 2', 'read search 1 Printer jam,3 Printer toner of 2'], EventLog::$lines);
        $this->assertSame($other, WriteLog::$reads[0]->user);
    }

    #[Test]
    public function it_does_not_notify_a_lazy_search(): void
    {
        $rows = Rest::search(ObservedTicketResource::class)->as($this->lead)->payload(self::SEARCH['search'])->lazy()->all();

        $this->assertSame([1, 3], array_column($rows, 'id'));
        $this->assertSame([], WriteLog::$reads);
    }

    #[Test]
    public function it_notifies_a_metric_computed_and_read_from_the_cache(): void
    {
        $first = $this->postJson('/observed-tickets/metrics/open-ticket-count')->assertOk()->getContent();
        $second = $this->postJson('/observed-tickets/metrics/open-ticket-count')->assertOk()->getContent();

        $this->assertSame(['{"data":{"type":"value","value":2,"previous":null}}', '{"data":{"type":"value","value":2,"previous":null}}'], [$first, $second]);
        $this->assertSame(['searching', 'calculate', 'read metric {"type":"value","value":2,"previous":null}', 'searching', 'read metric {"type":"value","value":2,"previous":null}'], EventLog::$lines);
        $this->assertCount(2, WriteLog::$reads);

        foreach (WriteLog::$reads as $context) {
            $this->assertSame(Endpoint::Metric, $context->endpoint);
            $this->assertInstanceOf(OpenTicketCount::class, $context->metric);
            $this->assertSame(['type' => 'value', 'value' => 2, 'previous' => null], $context->result);
            $this->assertSame([], $context->rows);
        }
    }

    #[Test]
    public function it_notifies_details_after_described(): void
    {
        $response = $this->getJson('/observed-tickets')->assertOk();

        $keys = implode(',', array_keys($response->json('data')));
        $this->assertSame(['describing', "described {$keys}", "read details {$keys}"], EventLog::$lines);
        $this->assertSame($response->json('data'), WriteLog::$reads[0]->details);
        $this->assertSame(['plan' => 'pro'], WriteLog::$reads[0]->details['meta'] ?? null);
        $this->assertSame(Endpoint::Details, WriteLog::$reads[0]->endpoint);
    }

    /** @return array<string, array{string, string, array<string, mixed>, bool}> */
    public static function reads(): array
    {
        return [
            'search' => ['POST', '/observed-tickets/search', self::SEARCH, false],
            'details' => ['GET', '/observed-tickets', [], true],
            'metric' => ['POST', '/observed-tickets/metrics/open-ticket-count', [], true],
        ];
    }

    /** @param array<string, mixed> $body */
    #[Test]
    #[DataProvider('reads')]
    public function it_keeps_every_response_when_an_observer_changes_its_copies(string $method, string $uri, array $body, bool $readonly): void
    {
        config(['rest.extensions' => []]);
        $plain = $this->json($method, $uri, $body)->assertOk()->getContent();
        $this->app['cache']->flush();
        config(['rest.extensions' => ['reads' => ChangingReadObserver::class]]);

        $changed = $this->json($method, $uri, $body)->assertOk()->getContent();

        $this->assertSame($plain, $changed);
        $this->assertSame($readonly ? ['read readonly'] : [], array_values(array_filter(EventLog::$lines, fn (string $line): bool => str_starts_with($line, 'read'))));
        $this->assertSame(['Billing', 'Support'], Team::query()->orderBy('id')->pluck('name')->all());
    }

    #[Test]
    public function it_keeps_the_rows_of_rest_search_page(): void
    {
        config(['rest.extensions' => ['reads' => ChangingReadObserver::class]]);

        $page = Rest::search(ObservedTicketResource::class)->as($this->lead)->payload(self::SEARCH['search'])->page();

        $this->assertSame([[1, 'Printer jam', 'Billing'], [3, 'Printer toner', 'Billing']], array_map(
            fn (Model $row): array => [$row->getKey(), $row->getAttribute('title'), $row->getRelation('team')?->getAttribute('name')],
            $page->items(),
        ));
    }

    /** @param array<string, mixed> $body */
    #[Test]
    #[DataProvider('reads')]
    public function it_lets_a_read_observer_exception_reach_the_client(string $method, string $uri, array $body, bool $readonly): void
    {
        config(['rest.extensions' => ['reads' => FailingReadObserver::class]]);
        $this->withoutExceptionHandling();

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('The read log is offline.');

        $this->json($method, $uri, $body);
    }

    /** @return array<string, array{array<string, class-string>}> */
    public static function readOrders(): array
    {
        return [
            'changing first' => [['first' => ChangingReadObserver::class, 'second' => RecordingReadObserver::class]],
            'recording first' => [['first' => RecordingReadObserver::class, 'second' => ChangingReadObserver::class]],
        ];
    }

    /** @param array<string, class-string> $extensions */
    #[Test]
    #[DataProvider('readOrders')]
    public function it_hands_each_extension_its_own_copies(array $extensions): void
    {
        config(['rest.extensions' => $extensions]);

        $this->postJson('/observed-tickets/search', self::SEARCH)->assertOk();

        $this->assertSame(['read search 1 Printer jam,3 Printer toner of 2'], array_values(array_filter(EventLog::$lines, fn (string $line): bool => str_starts_with($line, 'read'))));
        $this->assertSame([['Billing'], ['Billing']], array_map(fn (Model $row): array => [$row->getRelation('team')?->getAttribute('name')], WriteLog::$reads[0]->rows));
    }

    /** @return array<string, array{array<string, class-string>}> */
    public static function configuredOrders(): array
    {
        return [
            'in name order' => [['first' => ChangingReadObserver::class, 'second' => RecordingReadObserver::class]],
            'against name order' => [['second' => RecordingReadObserver::class, 'first' => ChangingReadObserver::class]],
        ];
    }

    /** @param array<string, class-string> $extensions */
    #[Test]
    #[DataProvider('configuredOrders')]
    public function it_notifies_the_read_observers_in_name_order(array $extensions): void
    {
        config(['rest.extensions' => $extensions]);

        $response = $this->getJson('/observed-tickets')->assertOk();

        $keys = implode(',', array_keys($response->json('data')));
        $this->assertSame(['read readonly', "read details {$keys}"], array_values(array_filter(EventLog::$lines, fn (string $line): bool => str_starts_with($line, 'read'))));
    }

    #[Test]
    public function it_refuses_a_read_observer_that_reads_a_column_that_the_page_did_not_load(): void
    {
        config(['rest.extensions' => ['reads' => TeamReadingObserver::class]]);

        $this->postJson('/observed-tickets/search', self::SEARCH)->assertOk();
        $narrowed = $this->postJson('/observed-tickets/search', ['search' => ['selects' => [['field' => 'title']], 'sorts' => [['field' => 'id', 'direction' => 'asc']]]]);

        $narrowed->assertStatus(500);
        $this->assertInstanceOf(MissingColumnException::class, $narrowed->exception);
        $this->assertSame(['read team 1', 'read team 1'], array_values(array_filter(EventLog::$lines, fn (string $line): bool => str_starts_with($line, 'read'))));
    }
}
