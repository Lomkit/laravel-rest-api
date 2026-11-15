<?php

namespace Lomkit\Rest\Tests\Feature\Extensions\Meta;

use ArrayObject;
use Closure;
use Illuminate\Auth\GenericUser;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Lomkit\Rest\Exceptions\InvalidMetaExtensionException;
use Lomkit\Rest\Exceptions\MissingColumnException;
use Lomkit\Rest\Facades\Rest;
use Lomkit\Rest\Tests\Feature\Authorization\HelpdeskTestCase;
use Lomkit\Rest\Tests\Feature\Extensions\Stubs\ChangingSearchMeta;
use Lomkit\Rest\Tests\Feature\Extensions\Stubs\ColumnSearchMeta;
use Lomkit\Rest\Tests\Feature\Extensions\Stubs\JsonResourceMeta;
use Lomkit\Rest\Tests\Feature\Extensions\Stubs\MetaLog;
use Lomkit\Rest\Tests\Feature\Extensions\Stubs\MetaShape;
use Lomkit\Rest\Tests\Feature\Extensions\Stubs\MetaStatus;
use Lomkit\Rest\Tests\Feature\Extensions\Stubs\MetaTicketController;
use Lomkit\Rest\Tests\Feature\Extensions\Stubs\MetaTicketResource;
use Lomkit\Rest\Tests\Feature\Extensions\Stubs\QuotaSearchMeta;
use Lomkit\Rest\Tests\Feature\Extensions\Stubs\QuotaSearchMetaNamedGates;
use Lomkit\Rest\Tests\Feature\Extensions\Stubs\RegionSearchMeta;
use Lomkit\Rest\Tests\Feature\Extensions\Stubs\RegionSearchMetaNamedArea;
use Lomkit\Rest\Tests\Feature\Extensions\Stubs\TeamSearchMeta;
use Lomkit\Rest\Tests\Feature\Extensions\Stubs\UnencodableMeta;
use Lomkit\Rest\Tests\Feature\Extensions\Stubs\ValueSearchMeta;
use Lomkit\Rest\Tests\Fixtures\Extensions\PageCountMetaExtensionNamedAbilities;
use Lomkit\Rest\Tests\Fixtures\Extensions\PageCountMetaExtensionNamedInstructions;
use Lomkit\Rest\Tests\Fixtures\Helpdesk\Ticket;
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
        config(['rest.extensions' => [QuotaSearchMeta::class, RegionSearchMeta::class]]);
    }

    #[\Override]
    protected function tearDown(): void
    {
        MetaLog::reset();

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

    /** @return array<string, array{list<class-string>, string}> */
    public static function orders(): array
    {
        $quota = '"quota":{"remaining":98,"titles":["Printer jam","Printer toner"],"user":1}';

        return [
            'quota first' => [[QuotaSearchMeta::class, RegionSearchMeta::class], '"meta":{"count":2,'.$quota.',"region":{}}'],
            'region first' => [[RegionSearchMeta::class, QuotaSearchMeta::class], '"meta":{"count":2,'.$quota.',"region":{}}'],
            'area, a later class, first by name' => [[QuotaSearchMeta::class, RegionSearchMetaNamedArea::class], '"meta":{"count":2,"area":{},'.$quota.'}'],
            'area first in the config' => [[RegionSearchMetaNamedArea::class, QuotaSearchMeta::class], '"meta":{"count":2,"area":{},'.$quota.'}'],
        ];
    }

    /** @param list<class-string> $extensions */
    #[Test]
    #[DataProvider('orders')]
    public function it_adds_the_meta_of_extensions_after_the_keys_of_the_resource_in_name_order(array $extensions, string $meta): void
    {
        config(['rest.extensions' => $extensions]);

        $content = (string) $this->postJson('/meta-tickets/search', self::SEARCH)->assertOk()->getContent();

        $this->assertStringContainsString($meta, $content);
    }

    #[Test]
    public function it_refuses_a_search_meta_key_of_the_resource_that_an_extension_takes(): void
    {
        MetaLog::$searchMeta = ['quota' => 1];
        $this->withoutExceptionHandling();

        $this->expectExceptionObject(InvalidMetaExtensionException::keyTaken('quota', QuotaSearchMeta::class, MetaTicketResource::class, 'searchMeta'));
        $this->expectExceptionMessage('The resource ['.MetaTicketResource::class.'] returns the key [quota] from searchMeta(), which holds the meta that the [quota] extension '.QuotaSearchMeta::class.' adds. Rename the key in searchMeta() of the resource, or give the extension another name: return it from '.QuotaSearchMeta::class.'::name(), or list a subclass of '.QuotaSearchMeta::class.' that overrides name() in the [rest.extensions] config and '.QuotaSearchMeta::class.' in the [rest.disabled_extensions] config.');

        try {
            $this->postJson('/meta-tickets/search', self::SEARCH);
        } finally {
            $this->assertSame([], MetaLog::$search);
        }
    }

    /** @return array<string, array{class-string, string|null, string}> */
    public static function packageKeys(): array
    {
        $gates = QuotaSearchMetaNamedGates::class;
        $instructions = PageCountMetaExtensionNamedInstructions::class;
        $abilities = PageCountMetaExtensionNamedAbilities::class;
        $resource = MetaTicketResource::class;

        return [
            'the default gates key' => [$gates, null, "The [gates] extension {$gates} adds search meta to {$resource} under its name, which is the key of the gates in the meta of a search page (rest.gates.key). Set rest.gates.key to another name, or give the extension another name: return it from {$gates}::name(), or list a subclass of {$gates} that overrides name() in the [rest.extensions] config and {$gates} in the [rest.disabled_extensions] config."],
            'the instructions key' => [$instructions, null, "The [instructions] extension {$instructions} adds search meta to {$resource} under its name, which is the key of the instruction meta of a search page. Give the extension another name: return it from {$instructions}::name(), or list a subclass of {$instructions} that overrides name() in the [rest.extensions] config and {$instructions} in the [rest.disabled_extensions] config."],
            'a configured gates key' => [$abilities, 'abilities', "The [abilities] extension {$abilities} adds search meta to {$resource} under its name, which is the key of the gates in the meta of a search page (rest.gates.key). Set rest.gates.key to another name, or give the extension another name: return it from {$abilities}::name(), or list a subclass of {$abilities} that overrides name() in the [rest.extensions] config and {$abilities} in the [rest.disabled_extensions] config."],
        ];
    }

    /** @param class-string $class */
    #[Test]
    #[DataProvider('packageKeys')]
    public function it_refuses_an_extension_named_after_a_key_of_the_package(string $class, ?string $gatesKey, string $message): void
    {
        if ($gatesKey !== null) {
            config(['rest.gates.key' => $gatesKey]);
        }

        config(['rest.extensions' => [$class]]);
        $this->withoutExceptionHandling();

        $this->expectException(InvalidMetaExtensionException::class);
        $this->expectExceptionMessage($message);

        $this->postJson('/meta-tickets/search', self::SEARCH);
    }

    #[Test]
    public function it_accepts_an_extension_named_gates_when_the_gates_key_is_another(): void
    {
        config(['rest.gates.key' => 'abilities', 'rest.extensions' => [QuotaSearchMetaNamedGates::class]]);

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
        config(['rest.extensions' => [ValueSearchMeta::class]]);
        $this->withoutExceptionHandling();

        $this->expectExceptionObject(InvalidMetaExtensionException::notEncodable('value', ValueSearchMeta::class, MetaTicketResource::class, 'searchMeta', $path, $type));

        $this->postJson('/meta-tickets/search', self::SEARCH);
    }

    #[Test]
    public function it_names_the_registered_class_of_a_meta_extension_bound_to_a_subclass(): void
    {
        $bound = new class extends ValueSearchMeta {};
        $this->app->bind(ValueSearchMeta::class, static fn (): ValueSearchMeta => $bound);
        MetaLog::$value = new ArrayObject;
        config(['rest.extensions' => [ValueSearchMeta::class]]);
        $this->withoutExceptionHandling();
        $expected = InvalidMetaExtensionException::notEncodable('value', ValueSearchMeta::class, MetaTicketResource::class, 'searchMeta', 'value.value', ArrayObject::class)->getMessage();
        $exception = null;

        try {
            $this->postJson('/meta-tickets/search', self::SEARCH);
        } catch (InvalidMetaExtensionException $caught) {
            $exception = $caught;
        }

        $this->assertSame([$expected, true, false], [$exception?->getMessage(), $exception?->concerns(ValueSearchMeta::class), $exception?->concerns($bound::class)]);
    }

    #[Test]
    public function it_names_the_registered_class_of_a_meta_extension_named_after_a_package_key_and_bound_to_a_subclass(): void
    {
        $bound = new class extends PageCountMetaExtensionNamedInstructions {};
        $this->app->bind(PageCountMetaExtensionNamedInstructions::class, static fn (): PageCountMetaExtensionNamedInstructions => $bound);
        config(['rest.extensions' => [PageCountMetaExtensionNamedInstructions::class]]);
        $this->withoutExceptionHandling();
        $expected = InvalidMetaExtensionException::instructionsName('instructions', PageCountMetaExtensionNamedInstructions::class, MetaTicketResource::class)->getMessage();
        $exception = null;

        try {
            $this->postJson('/meta-tickets/search', self::SEARCH);
        } catch (InvalidMetaExtensionException $caught) {
            $exception = $caught;
        }

        $this->assertSame([$expected, true, false], [$exception?->getMessage(), $exception?->concerns(PageCountMetaExtensionNamedInstructions::class), $exception?->concerns($bound::class)]);
    }

    #[Test]
    public function it_refuses_a_model_in_the_meta(): void
    {
        MetaLog::$value = ['ticket' => new Ticket(['title' => 'Printer jam'])];
        config(['rest.extensions' => [ValueSearchMeta::class]]);
        $this->withoutExceptionHandling();

        $this->expectExceptionObject(InvalidMetaExtensionException::notEncodable('value', ValueSearchMeta::class, MetaTicketResource::class, 'searchMeta', 'value.value.ticket', Ticket::class));

        $this->postJson('/meta-tickets/search', self::SEARCH);
    }

    #[Test]
    public function it_refuses_an_eloquent_collection_in_the_meta(): void
    {
        MetaLog::$value = ['tickets' => new EloquentCollection([new Ticket(['title' => 'Printer jam'])])];
        config(['rest.extensions' => [ValueSearchMeta::class]]);
        $this->withoutExceptionHandling();

        $this->expectExceptionObject(InvalidMetaExtensionException::notEncodable('value', ValueSearchMeta::class, MetaTicketResource::class, 'searchMeta', 'value.value.tickets', EloquentCollection::class));

        $this->postJson('/meta-tickets/search', self::SEARCH);
    }

    #[Test]
    public function it_refuses_a_model_inside_a_plain_collection_in_the_meta(): void
    {
        MetaLog::$value = ['tickets' => collect([new Ticket(['title' => 'Printer jam'])])];
        config(['rest.extensions' => [ValueSearchMeta::class]]);
        $this->withoutExceptionHandling();

        $this->expectExceptionObject(InvalidMetaExtensionException::notEncodable('value', ValueSearchMeta::class, MetaTicketResource::class, 'searchMeta', 'value.value.tickets.0', Ticket::class));

        $this->postJson('/meta-tickets/search', self::SEARCH);
    }

    #[Test]
    public function it_refuses_the_models_of_an_eloquent_collection_turned_into_a_plain_one(): void
    {
        MetaLog::$value = ['tickets' => (new EloquentCollection([new Ticket(['title' => 'Printer jam'])]))->toBase()];
        config(['rest.extensions' => [ValueSearchMeta::class]]);
        $this->withoutExceptionHandling();

        $this->expectExceptionObject(InvalidMetaExtensionException::notEncodable('value', ValueSearchMeta::class, MetaTicketResource::class, 'searchMeta', 'value.value.tickets.0', Ticket::class));

        $this->postJson('/meta-tickets/search', self::SEARCH);
    }

    /** @return array<string, array{Closure(): mixed, string, class-string}> */
    public static function jsonResources(): array
    {
        return JsonResourceMeta::cases();
    }

    /** @param Closure(): mixed $value */
    #[Test]
    #[DataProvider('jsonResources')]
    public function it_refuses_a_json_resource_in_the_meta(Closure $value, string $path, string $type): void
    {
        MetaLog::$value = $value();
        config(['rest.extensions' => [ValueSearchMeta::class]]);
        $this->withoutExceptionHandling();

        $this->expectExceptionObject(InvalidMetaExtensionException::notEncodable('value', ValueSearchMeta::class, MetaTicketResource::class, 'searchMeta', "value.value.{$path}", $type));

        $this->postJson('/meta-tickets/search', self::SEARCH);
    }

    #[Test]
    public function it_renders_a_plain_collection_of_values(): void
    {
        MetaLog::$value = ['titles' => collect(['Printer jam', 'Printer toner'])];
        config(['rest.extensions' => [ValueSearchMeta::class]]);

        $this->postJson('/meta-tickets/search', self::SEARCH)->assertOk()->assertJsonPath('meta.value', ['value' => ['titles' => ['Printer jam', 'Printer toner']]]);
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
        config(['rest.extensions' => [ValueSearchMeta::class]]);
        $this->withoutExceptionHandling();

        $this->expectExceptionObject(InvalidMetaExtensionException::notJson('value', ValueSearchMeta::class, MetaTicketResource::class, 'searchMeta', $reason));

        $this->postJson('/meta-tickets/search', self::SEARCH);
    }

    #[Test]
    public function it_renders_json_serializable_values(): void
    {
        MetaLog::$value = ['at' => now(), 'tags' => (object) ['a' => 1], 'ratio' => 0.5, 'status' => MetaStatus::Open, 'none' => null];
        config(['rest.extensions' => [ValueSearchMeta::class]]);

        $content = (string) $this->postJson('/meta-tickets/search', self::SEARCH)->assertOk()->getContent();

        $this->assertStringContainsString('"meta":{"count":2,"value":{"value":{"at":"2026-11-15T10:00:00.000000Z","tags":{"a":1},"ratio":0.5,"status":"open","none":null}}}', $content);
    }

    #[Test]
    public function it_reads_only_the_columns_that_the_search_loaded(): void
    {
        config(['rest.extensions' => [ColumnSearchMeta::class]]);

        $this->postJson('/meta-tickets/search', self::SEARCH)->assertOk()->assertJsonPath('meta.teams', ['teams' => [1, 1]]);
        $narrowed = $this->postJson('/meta-tickets/search', ['search' => ['selects' => [['field' => 'title']], 'sorts' => [['field' => 'id', 'direction' => 'asc']]]]);

        $narrowed->assertStatus(500);
        $this->assertInstanceOf(MissingColumnException::class, $narrowed->exception);
        $this->assertStringContainsString('[team_id]', $narrowed->exception->getMessage());
    }

    #[Test]
    public function it_hands_copies_of_the_rows_to_each_extension(): void
    {
        config(['rest.extensions' => [ChangingSearchMeta::class, QuotaSearchMeta::class]]);

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
        config(['rest.extensions' => [TeamSearchMeta::class]]);
        $search = [...self::SEARCH['search'], 'includes' => [['relation' => 'team']]];

        $this->postJson('/meta-tickets/search', ['search' => $search])->assertOk()->assertJsonPath('meta', ['count' => 2]);
        $rows = Rest::search(MetaTicketResource::class)->as($this->lead)->payload(self::SEARCH['search'])->lazy()->all();

        $this->assertSame([1, 3], array_column($rows, 'id'));
        $this->assertSame([], MetaLog::$search);
    }
}
