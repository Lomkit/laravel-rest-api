<?php

namespace Lomkit\Rest\Tests\Feature\Extensions\Meta;

use ArrayObject;
use Illuminate\Auth\GenericUser;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Lomkit\Rest\Exceptions\InvalidMetaExtensionException;
use Lomkit\Rest\Exceptions\MissingColumnException;
use Lomkit\Rest\Extensions\Applicability;
use Lomkit\Rest\Facades\Rest;
use Lomkit\Rest\Tests\Feature\Authorization\HelpdeskTestCase;
use Lomkit\Rest\Tests\Feature\Extensions\Stubs\ChangingSearchMeta;
use Lomkit\Rest\Tests\Feature\Extensions\Stubs\ColumnSearchMeta;
use Lomkit\Rest\Tests\Feature\Extensions\Stubs\MetaLog;
use Lomkit\Rest\Tests\Feature\Extensions\Stubs\MetaShape;
use Lomkit\Rest\Tests\Feature\Extensions\Stubs\MetaStatus;
use Lomkit\Rest\Tests\Feature\Extensions\Stubs\MetaTicketController;
use Lomkit\Rest\Tests\Feature\Extensions\Stubs\MetaTicketResource;
use Lomkit\Rest\Tests\Feature\Extensions\Stubs\QuotaSearchMeta;
use Lomkit\Rest\Tests\Feature\Extensions\Stubs\RegionSearchMeta;
use Lomkit\Rest\Tests\Feature\Extensions\Stubs\TeamSearchMeta;
use Lomkit\Rest\Tests\Feature\Extensions\Stubs\UnencodableMeta;
use Lomkit\Rest\Tests\Feature\Extensions\Stubs\ValueSearchMeta;
use Lomkit\Rest\Tests\Fixtures\Helpdesk\TicketPolicy;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use SplObjectStorage;

final class SearchMetaExtensionsTest extends HelpdeskTestCase
{
    private const array SEARCH = ['search' => [
        'filters' => [['field' => 'status', 'value' => 'open']],
        'sorts' => [['field' => 'id', 'direction' => 'asc']],
        'limit' => 10,
    ]];

    private const array ROWS = [
        ['id' => 1, 'team_id' => 1, 'title' => 'Printer jam', 'status' => 'open'],
        ['id' => 3, 'team_id' => 1, 'title' => 'Printer toner', 'status' => 'open'],
    ];

    private GenericUser $lead;

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        MetaLog::reset();
        Applicability::flush();
        $this->travelTo('2026-11-15 10:00:00');
        DB::table('teams')->insert([
            ['id' => 1, 'name' => 'Billing', 'created_at' => '2026-01-01 00:00:00', 'updated_at' => '2026-01-01 00:00:00'],
            ['id' => 2, 'name' => 'Support', 'created_at' => '2026-01-01 00:00:00', 'updated_at' => '2026-01-01 00:00:00'],
        ]);
        DB::table('tickets')->insert([
            ['id' => 1, 'team_id' => 1, 'title' => 'Printer jam', 'status' => 'open', 'created_at' => '2026-01-01 00:00:00', 'updated_at' => '2026-01-01 00:00:00'],
            ['id' => 2, 'team_id' => 2, 'title' => 'Password reset', 'status' => 'closed', 'created_at' => '2026-01-01 00:00:00', 'updated_at' => '2026-01-01 00:00:00'],
            ['id' => 3, 'team_id' => 1, 'title' => 'Printer toner', 'status' => 'open', 'created_at' => '2026-01-01 00:00:00', 'updated_at' => '2026-01-01 00:00:00'],
            ['id' => 4, 'team_id' => 2, 'title' => 'Laptop setup', 'status' => 'closed', 'created_at' => '2026-01-01 00:00:00', 'updated_at' => '2026-01-01 00:00:00'],
        ]);
        Rest::resource('meta-tickets', MetaTicketController::class)->register();
        $this->lead = new GenericUser(['id' => TicketPolicy::LEAD]);
        $this->actingAs($this->lead);
        config(['rest.extensions' => ['quota' => QuotaSearchMeta::class, 'region' => RegionSearchMeta::class]]);
    }

    #[\Override]
    protected function tearDown(): void
    {
        MetaLog::reset();
        Applicability::flush();

        parent::tearDown();
    }

    #[Test]
    public function it_adds_the_meta_of_each_extension_under_its_name(): void
    {
        $response = $this->postJson('/meta-tickets/search', self::SEARCH);

        $response->assertOk()->assertExactJson([
            'current_page' => 1,
            'data' => self::ROWS,
            'from' => 1,
            'last_page' => 1,
            'meta' => [
                'count' => 2,
                'quota' => ['remaining' => 98, 'titles' => ['Printer jam', 'Printer toner'], 'user' => 1],
                'region' => [],
            ],
            'per_page' => 10,
            'to' => 2,
            'total' => 2,
        ]);
    }

    #[Test]
    public function it_renders_the_empty_meta_of_an_extension_as_an_object(): void
    {
        $content = (string) $this->postJson('/meta-tickets/search', self::SEARCH)->assertOk()->getContent();

        $this->assertStringContainsString('"meta":{"count":2,"quota":{"remaining":98,"titles":["Printer jam","Printer toner"],"user":1},"region":{}}', $content);
    }

    #[Test]
    public function it_computes_the_meta_on_the_received_page(): void
    {
        $this->postJson('/meta-tickets/search', self::SEARCH)->assertOk();

        $this->assertCount(1, MetaLog::$search);
        $context = MetaLog::$search[0];
        $this->assertInstanceOf(MetaTicketResource::class, $context->resource);
        $this->assertSame($this->lead, $context->user);
        $this->assertSame([1, 3], array_map(fn (Model $row): mixed => $row->getKey(), $context->rows));
        $this->assertSame([2, 1, 10], [$context->total, $context->page, $context->perPage]);
        $this->assertTrue($context->search->filtersOn('status'));
        $this->assertFalse($context->search->filtersOn('title'));
    }

    /** @return array<string, array{array<string, class-string>}> */
    public static function orders(): array
    {
        return [
            'quota first' => [['quota' => QuotaSearchMeta::class, 'region' => RegionSearchMeta::class]],
            'region first' => [['region' => RegionSearchMeta::class, 'quota' => QuotaSearchMeta::class]],
        ];
    }

    /** @param array<string, class-string> $extensions */
    #[Test]
    #[DataProvider('orders')]
    public function it_adds_the_meta_of_extensions_after_the_keys_of_the_resource_in_name_order(array $extensions): void
    {
        config(['rest.extensions' => $extensions]);

        $content = (string) $this->postJson('/meta-tickets/search', self::SEARCH)->assertOk()->getContent();

        $this->assertStringContainsString('"meta":{"count":2,"quota":{"remaining":98,"titles":["Printer jam","Printer toner"],"user":1},"region":{}}', $content);
    }

    #[Test]
    public function it_refuses_a_search_meta_key_of_the_resource_that_an_extension_takes(): void
    {
        MetaLog::$searchMeta = ['quota' => 1];
        $this->withoutExceptionHandling();

        $this->expectExceptionObject(InvalidMetaExtensionException::keyTaken('quota', QuotaSearchMeta::class, MetaTicketResource::class, 'searchMeta'));

        try {
            $this->postJson('/meta-tickets/search', self::SEARCH);
        } finally {
            $this->assertSame([], MetaLog::$search);
        }
    }

    /** @return array<string, array{string, string|null, string}> */
    public static function packageKeys(): array
    {
        $class = RegionSearchMeta::class;
        $resource = MetaTicketResource::class;

        return [
            'the default gates key' => ['gates', null, "The [gates] extension {$class} adds search meta to {$resource} under its name, which is the key of the gates in the meta of a search page (rest.gates.key). Register the extension under another name, or set rest.gates.key to another name."],
            'the instructions key' => ['instructions', null, "The [instructions] extension {$class} adds search meta to {$resource} under its name, which is the key of the instruction meta of a search page. Register the extension under another name."],
            'a configured gates key' => ['abilities', 'abilities', "The [abilities] extension {$class} adds search meta to {$resource} under its name, which is the key of the gates in the meta of a search page (rest.gates.key). Register the extension under another name, or set rest.gates.key to another name."],
        ];
    }

    #[Test]
    #[DataProvider('packageKeys')]
    public function it_refuses_an_extension_named_after_a_key_of_the_package(string $name, ?string $gatesKey, string $message): void
    {
        if ($gatesKey !== null) {
            config(['rest.gates.key' => $gatesKey]);
        }

        config(['rest.extensions' => [$name => RegionSearchMeta::class]]);
        $this->withoutExceptionHandling();

        $this->expectException(InvalidMetaExtensionException::class);
        $this->expectExceptionMessage($message);

        $this->postJson('/meta-tickets/search', self::SEARCH);
    }

    #[Test]
    public function it_accepts_an_extension_named_gates_when_the_gates_key_is_another(): void
    {
        config(['rest.gates.key' => 'abilities', 'rest.extensions' => ['gates' => QuotaSearchMeta::class]]);

        $response = $this->postJson('/meta-tickets/search', self::SEARCH);

        $response->assertOk()->assertJsonPath('meta', [
            'count' => 2,
            'gates' => ['remaining' => 98, 'titles' => ['Printer jam', 'Printer toner'], 'user' => 1],
        ]);
    }

    /** @return array<string, array{mixed, string, string}> */
    public static function unencodable(): array
    {
        return [
            'an object' => [new ArrayObject, 'ArrayObject', 'value.value'],
            'an infinite float' => [INF, 'float', 'value.value'],
            'a nested object' => [['nested' => new SplObjectStorage], 'SplObjectStorage', 'value.value.nested'],
            'a pure enum' => [['shape' => MetaShape::Round], MetaShape::class, 'value.value.shape'],
        ];
    }

    #[Test]
    #[DataProvider('unencodable')]
    public function it_refuses_a_value_that_json_cannot_encode(mixed $value, string $type, string $path): void
    {
        MetaLog::$value = $value;
        config(['rest.extensions' => ['value' => ValueSearchMeta::class]]);
        $this->withoutExceptionHandling();

        $this->expectExceptionObject(InvalidMetaExtensionException::notEncodable('value', ValueSearchMeta::class, MetaTicketResource::class, 'searchMeta', $path, $type));

        $this->postJson('/meta-tickets/search', self::SEARCH);
    }

    /** @return array<string, array{mixed, string}> */
    public static function notJson(): array
    {
        return UnencodableMeta::cases();
    }

    #[Test]
    #[DataProvider('notJson')]
    public function it_refuses_a_value_that_json_refuses_naming_the_extension(mixed $value, string $reason): void
    {
        MetaLog::$value = $value;
        config(['rest.extensions' => ['value' => ValueSearchMeta::class]]);
        $this->withoutExceptionHandling();

        $this->expectExceptionObject(InvalidMetaExtensionException::notJson('value', ValueSearchMeta::class, MetaTicketResource::class, 'searchMeta', $reason));

        $this->postJson('/meta-tickets/search', self::SEARCH);
    }

    #[Test]
    public function it_renders_json_serializable_values(): void
    {
        MetaLog::$value = ['at' => now(), 'tags' => (object) ['a' => 1], 'ratio' => 0.5, 'status' => MetaStatus::Open, 'none' => null];
        config(['rest.extensions' => ['value' => ValueSearchMeta::class]]);

        $content = (string) $this->postJson('/meta-tickets/search', self::SEARCH)->assertOk()->getContent();

        $this->assertStringContainsString('"meta":{"count":2,"value":{"value":{"at":"2026-11-15T10:00:00.000000Z","tags":{"a":1},"ratio":0.5,"status":"open","none":null}}}', $content);
    }

    #[Test]
    public function it_reads_only_the_columns_that_the_search_loaded(): void
    {
        config(['rest.extensions' => ['teams' => ColumnSearchMeta::class]]);

        $this->postJson('/meta-tickets/search', self::SEARCH)->assertOk()->assertJsonPath('meta.teams', ['teams' => [1, 1]]);
        $narrowed = $this->postJson('/meta-tickets/search', ['search' => ['selects' => [['field' => 'title']], 'sorts' => [['field' => 'id', 'direction' => 'asc']]]]);

        $narrowed->assertStatus(500);
        $this->assertInstanceOf(MissingColumnException::class, $narrowed->exception);
        $this->assertStringContainsString('[team_id]', $narrowed->exception->getMessage());
    }

    #[Test]
    public function it_hands_copies_of_the_rows_to_each_extension(): void
    {
        config(['rest.extensions' => ['changing' => ChangingSearchMeta::class, 'quota' => QuotaSearchMeta::class]]);

        $response = $this->postJson('/meta-tickets/search', self::SEARCH);

        $response->assertOk()
            ->assertJsonPath('data', self::ROWS)
            ->assertJsonPath('meta.quota.titles', ['Printer jam', 'Printer toner']);
    }

    #[Test]
    public function it_adds_the_meta_to_a_search_outside_http_for_its_user(): void
    {
        $other = new GenericUser(['id' => 7]);

        $rendered = Rest::search(MetaTicketResource::class)->as($other)->payload(self::SEARCH['search'])->render();

        $this->assertSame(7, MetaLog::$search[0]->user?->getAuthIdentifier());
        $this->assertSame('{"count":2,"quota":{"remaining":98,"titles":["Printer jam","Printer toner"],"user":7},"region":{}}', json_encode($rendered['meta']));
    }

    #[Test]
    public function it_adds_no_meta_to_an_include_or_a_lazy_search(): void
    {
        config(['rest.extensions' => ['teams' => TeamSearchMeta::class]]);
        $search = [...self::SEARCH['search'], 'includes' => [['relation' => 'team']]];

        $this->postJson('/meta-tickets/search', ['search' => $search])->assertOk()->assertJsonPath('meta', ['count' => 2]);
        $rows = Rest::search(MetaTicketResource::class)->as($this->lead)->payload(self::SEARCH['search'])->lazy()->all();

        $this->assertSame([1, 3], array_column($rows, 'id'));
        $this->assertSame([], MetaLog::$search);
    }
}
