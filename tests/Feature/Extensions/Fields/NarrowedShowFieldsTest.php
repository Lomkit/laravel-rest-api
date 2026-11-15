<?php

namespace Lomkit\Rest\Tests\Feature\Extensions\Fields;

use Closure;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Testing\TestResponse;
use Lomkit\Rest\Exceptions\InvalidFieldExtensionException;
use Lomkit\Rest\Extensions\Contexts\FieldsContext;
use Lomkit\Rest\Fields\FieldReduction;
use Lomkit\Rest\Tests\Concerns\ResetsExtensionStubs;
use Lomkit\Rest\Tests\Feature\Extensions\Stubs\MaskingFields;
use Lomkit\Rest\Tests\Fixtures\Accounts\Accounts;
use Lomkit\Rest\Tests\Fixtures\Accounts\User;
use Lomkit\Rest\Tests\Fixtures\Festival\Festival;
use Lomkit\Rest\Tests\Fixtures\Festival\Show;
use Lomkit\Rest\Tests\Fixtures\Festival\ShowResource;
use Lomkit\Rest\Tests\PackageTestCase;
use Orchestra\Testbench\Attributes\WithConfig;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;

#[WithConfig('rest.extensions', [MaskingFields::class])]
class NarrowedShowFieldsTest extends PackageTestCase
{
    use ResetsExtensionStubs;

    private int $opening = 0;

    #[\Override]
    protected function fixtures(): array
    {
        return [new Accounts, new Festival];
    }

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2026-03-01 09:00:00');
        DB::table('stages')->insert([['code' => 'main', 'name' => 'Main stage']]);
        $this->opening = DB::table('shows')->insertGetId(['stage_code' => 'main', 'title' => 'Opening night', 'price' => 20, 'seats' => 300, 'level' => 1, 'starts_on' => '2026-07-01', 'starts_at' => '2026-02-20 20:00:00', 'status' => 'on_sale', 'updated_at' => '2026-02-01 08:00:00']);
        DB::table('shows')->insert(['stage_code' => 'main', 'title' => 'Closing night', 'price' => 35, 'seats' => 100, 'level' => 2, 'starts_on' => '2026-07-03', 'starts_at' => '2026-02-21 20:00:00', 'status' => 'planned', 'updated_at' => '2026-02-01 08:00:00']);
        DB::table('notes')->insert(['id' => 1, 'notable_type' => Show::class, 'notable_id' => (string) $this->opening, 'body' => 'Sold out soon']);
        $this->actingAs(new User(['id' => 1, 'name' => 'Ada']));
    }

    #[Test]
    public function it_refuses_to_hide_the_row_version(): void
    {
        $this->narrow(static fn (FieldsContext $context): mixed => $context->hide('updated_at'));

        $this->expectExceptionObject(new InvalidFieldExtensionException('The [mask] extension '.MaskingFields::class.' hides [updated_at], the row version of '.ShowResource::class.': versioned() reads it on each update and returns it in a 409. Call readOnly(), unfilterable() or unsortable() on it instead, or turn versioned() off.'));

        $this->withoutExceptionHandling()->postJson('/api/shows/search', ['search' => []]);
    }

    #[Test]
    public function it_lets_the_row_version_be_unfilterable_and_unsortable(): void
    {
        $this->narrow(static function (FieldsContext $context): void {
            $context->unfilterable('updated_at');
            $context->unsortable('updated_at');
        });

        $update = $this->postJson('/api/shows/mutate', ['mutate' => [
            ['operation' => 'update', 'key' => $this->opening, 'version' => '2026-02-01T08:00:00.000000Z', 'attributes' => ['title' => 'First night']],
        ], 'response' => ['selects' => [['field' => 'updated_at']]]]);
        $filter = $this->postJson('/api/shows/search', ['search' => ['filters' => [['field' => 'updated_at', 'value' => '2026-02-01T08:00:00.000000Z']]]]);

        $update->assertOk()->assertJsonPath('records', [['updated_at' => '2026-03-01T09:00:00.000000Z']]);
        $filter->assertUnprocessable()->assertJsonPath('errors', ['search.filters.0.field' => ['The selected search.filters.0.field is invalid.']]);
    }

    #[Test]
    public function it_refuses_to_hide_a_searchable_field(): void
    {
        $this->narrow(static fn (FieldsContext $context): mixed => $context->hide('title'));

        $this->expectExceptionObject(new InvalidFieldExtensionException('The [mask] extension '.MaskingFields::class.' hides [title], a searchable() field of '.ShowResource::class.': its text search would still match the value. Remove searchable() from the field or keep it visible.'));

        $this->withoutExceptionHandling()->postJson('/api/shows/search', ['search' => []]);
    }

    #[Test]
    public function it_lets_a_searchable_field_be_read_only(): void
    {
        $this->narrow(static fn (FieldsContext $context): mixed => $context->readOnly('title'));

        $response = $this->postJson('/api/shows/search', ['search' => ['text' => ['value' => 'Closing'], 'selects' => [['field' => 'title']]]]);
        $write = $this->postJson('/api/shows/mutate', ['mutate' => [
            ['operation' => 'update', 'key' => $this->opening, 'attributes' => ['title' => 'Gala night']],
        ]]);

        $response->assertOk()->assertJsonPath('data', [['title' => 'Closing night']]);
        $write->assertUnprocessable()->assertJsonPath('errors', ['mutate.0.attributes' => ['The title key of the mutate.0.attributes field is not writable on update.']]);
        $this->assertSame('Opening night', Show::query()->findOrFail($this->opening)->getAttribute('title'));
    }

    #[Test]
    public function it_creates_a_row_without_a_hidden_nullable_field(): void
    {
        $this->hidePrice();
        $show = ['title' => 'Matinee', 'starts_on' => '2026-07-05', 'status' => 'planned'];

        $created = $this->postJson('/api/shows/mutate', ['mutate' => [['operation' => 'create', 'attributes' => $show]]]);

        $matinee = Show::query()->where('title', 'Matinee')->sole();
        $created->assertOk()->assertExactJson(['operations' => [['operation' => 'create', 'key' => $matinee->getKey()]]]);
        $this->assertSame([null, '2026-07-05'], [$matinee->getAttribute('price'), $matinee->getAttribute('starts_on')?->format('Y-m-d')]);
        $this->assertLikeUnknown(fn (string $field): TestResponse => $this->postJson('/api/shows/mutate', ['mutate' => [
            ['operation' => 'create', 'attributes' => [...$show, $field => 12]],
        ]]));
    }

    #[Test]
    public function it_keeps_app_metrics_on_declared_fields(): void
    {
        $this->narrow(static fn (FieldsContext $context): mixed => $context->hide('seats'));

        $metric = $this->postJson('/api/shows/metrics/seats-by-level', ['range' => 30]);

        $metric->assertOk();
        $this->assertSame(['data' => ['type' => 'partition', 'parts' => [
            ['key' => 1, 'label' => 'Open', 'value' => 300.0, 'previous' => null],
            ['key' => 2, 'label' => 'Main', 'value' => 100.0, 'previous' => null],
        ], 'other' => null]], self::withFloatValues($metric->json()));
        $this->assertLikeUnknown(
            fn (string $field): TestResponse => $this->postJson('/api/shows/metrics/seats-by-level', ['range' => 30, 'search' => ['filters' => [['field' => $field, 'value' => 300]]]]),
            'seats',
        );
        $this->assertLikeUnknown(
            fn (string $field): TestResponse => $this->postJson('/api/shows/metrics/seats-by-level', ['range' => 30, 'search' => ['filters' => [['nested' => [['field' => $field, 'value' => 300]]]]]]),
            'seats',
        );
    }

    #[Test]
    public function it_refuses_a_hidden_field_in_the_search_of_an_action(): void
    {
        $this->hidePrice();

        $this->assertLikeUnknown(fn (string $field): TestResponse => $this->postJson('/api/shows/actions/reschedule-shows', [
            'search' => ['filters' => [['field' => $field, 'value' => 20]]],
        ]));
    }

    #[Test]
    public function it_hides_the_field_in_a_morph_to_branch(): void
    {
        $this->hidePrice();

        $search = $this->postJson('/api/notes/search', ['search' => ['includes' => [['relation' => 'notable']]]]);

        $search->assertOk()->assertExactJson([
            'current_page' => 1,
            'data' => [[
                'id' => 1,
                'notable_type' => Show::class,
                'body' => 'Sold out soon',
                'notable' => ['@type' => Show::class, 'id' => $this->opening, 'title' => 'Opening night', 'summary' => null, 'seats' => 300, 'featured' => false, 'starts_on' => '2026-07-01', 'starts_at' => '2026-02-20T20:00:00.000000Z', 'status' => 'on_sale', 'level' => 1, 'settings' => null, 'updated_at' => '2026-02-01T08:00:00.000000Z'],
            ]],
            'from' => 1,
            'last_page' => 1,
            'per_page' => 50,
            'to' => 1,
            'total' => 1,
            'meta' => [],
        ]);
        $this->assertLikeUnknown(fn (string $field): TestResponse => $this->postJson('/api/notes/search', ['search' => ['filters' => [
            ['relation' => 'notable', 'types' => [Show::class => [['field' => $field, 'value' => 20]]]],
        ]]]));
    }

    #[Test]
    #[DataProvider('rowWrites')]
    public function it_narrows_the_rows_of_a_row_write_before_writing_them_and_writes_nothing_when_it_fails(string $method, string $uri, bool $trashed, string $updatedAt): void
    {
        $this->trashOpeningIf($trashed);
        $this->narrow(static fn (FieldsContext $context): mixed => $context->hide('colour'));
        $before = $this->openingState();

        try {
            $this->withoutExceptionHandling()->json($method, $uri, ['resources' => [$this->opening]]);
            $this->fail('The row write passed although its field narrowing failed.');
        } catch (InvalidFieldExtensionException $exception) {
            $this->assertSame(InvalidFieldExtensionException::unknownField('mask', MaskingFields::class, ShowResource::class, FieldReduction::Hide, 'colour')->getMessage(), $exception->getMessage());
            $this->assertSame($before, $this->openingState());
        }

        $states = [];
        $this->narrow(function (FieldsContext $context) use (&$states): void {
            $states[] = $this->openingState();
            $context->hide('price');
        });

        $response = $this->json($method, $uri, ['resources' => [$this->opening]]);

        $response->assertOk()->assertExactJson(['data' => [
            ['id' => $this->opening, 'title' => 'Opening night', 'summary' => null, 'seats' => 300, 'featured' => false, 'starts_on' => '2026-07-01', 'starts_at' => '2026-02-20T20:00:00.000000Z', 'status' => 'on_sale', 'level' => 1, 'settings' => null, 'updated_at' => $updatedAt],
        ], 'meta' => []]);
        $this->assertSame([$before], $states);
    }

    /** @return array<string, array{string, string, bool, string}> */
    public static function rowWrites(): array
    {
        return [
            'a destroy' => ['DELETE', '/api/shows', false, '2026-03-01T09:00:00.000000Z'],
            'a restore' => ['POST', '/api/shows/restore', true, '2026-03-01T09:00:00.000000Z'],
            'a force destroy' => ['DELETE', '/api/shows/force', true, '2026-02-01T08:00:00.000000Z'],
        ];
    }

    /**
     * @param  array<string, mixed>  $body
     * @return array<string, mixed>
     */
    private static function withFloatValues(array $body): array
    {
        $parts = $body['data']['parts'] ?? [];
        $body['data']['parts'] = array_map(static fn (array $part): array => [...$part, 'value' => (float) $part['value']], is_array($parts) ? $parts : []);

        return $body;
    }

    private function trashOpeningIf(bool $trashed): void
    {
        if ($trashed) {
            DB::table('shows')->where('id', $this->opening)->update(['deleted_at' => '2026-02-25 10:00:00']);
        }
    }

    /** @return array{int, bool, bool} */
    private function openingState(): array
    {
        $show = Show::withTrashed()->find($this->opening);

        return [DB::transactionLevel(), $show !== null, $show?->trashed() ?? false];
    }

    /** @param Closure(FieldsContext): mixed $narrow */
    private function narrow(Closure $narrow): void
    {
        MaskingFields::$narrow = static function (FieldsContext $context) use ($narrow): void {
            if ($context->resource instanceof ShowResource) {
                $narrow($context);
            }
        };
    }

    private function hidePrice(): void
    {
        $this->narrow(static fn (FieldsContext $context): mixed => $context->hide('price'));
    }

    /** @param Closure(string): TestResponse $request */
    private function assertLikeUnknown(Closure $request, string $hidden = 'price'): void
    {
        $narrowed = $request($hidden);
        $unknown = $request('colour');

        $narrowed->assertUnprocessable();
        $this->assertSame(str_replace('colour', $hidden, (string) $unknown->getContent()), $narrowed->getContent());
    }
}
