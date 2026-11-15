<?php

namespace Lomkit\Rest\Tests\Feature\Extensions\Fields;

use Closure;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Testing\TestResponse;
use Lomkit\Rest\Exceptions\InvalidFieldExtensionException;
use Lomkit\Rest\Extensions\Applicability;
use Lomkit\Rest\Extensions\Contexts\FieldsContext;
use Lomkit\Rest\Tests\Feature\Extensions\Stubs\MaskingFields;
use Lomkit\Rest\Tests\Fixtures\Accounts\Accounts;
use Lomkit\Rest\Tests\Fixtures\Accounts\User;
use Lomkit\Rest\Tests\Fixtures\Festival\Festival;
use Lomkit\Rest\Tests\Fixtures\Festival\Show;
use Lomkit\Rest\Tests\Fixtures\Festival\ShowResource;
use Lomkit\Rest\Tests\PackageTestCase;
use Orchestra\Testbench\Attributes\WithConfig;
use PHPUnit\Framework\Attributes\Test;

#[WithConfig('rest.extensions', ['mask' => MaskingFields::class])]
class NarrowedShowFieldsTest extends PackageTestCase
{
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
        Applicability::flush();
        MaskingFields::reset();
        DB::table('stages')->insert([['code' => 'main', 'name' => 'Main stage']]);
        DB::table('shows')->insert([
            ['id' => 1, 'stage_code' => 'main', 'title' => 'Opening night', 'price' => 20, 'seats' => 300, 'level' => 1, 'starts_on' => '2026-07-01', 'starts_at' => '2026-02-20 20:00:00', 'status' => 'on_sale', 'updated_at' => '2026-02-01 08:00:00'],
            ['id' => 2, 'stage_code' => 'main', 'title' => 'Closing night', 'price' => 35, 'seats' => 100, 'level' => 2, 'starts_on' => '2026-07-03', 'starts_at' => '2026-02-21 20:00:00', 'status' => 'planned', 'updated_at' => '2026-02-01 08:00:00'],
        ]);
        DB::table('notes')->insert([['id' => 1, 'notable_type' => Show::class, 'notable_id' => '1', 'body' => 'Sold out soon']]);
        $this->actingAs(new User(['id' => 1, 'name' => 'Ada']));
    }

    #[\Override]
    protected function tearDown(): void
    {
        MaskingFields::reset();
        Applicability::flush();

        parent::tearDown();
    }

    #[Test]
    public function it_refuses_to_hide_the_row_version(): void
    {
        $this->narrow(static fn (FieldsContext $context): mixed => $context->hide('updated_at'));

        $this->expectExceptionObject(new InvalidFieldExtensionException('The [mask] extension hides [updated_at], the row version of '.ShowResource::class.': versioned() reads it on each update and returns it in a 409. Call readOnly(), unfilterable() or unsortable() on it instead, or turn versioned() off.'));

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
            ['operation' => 'update', 'key' => 1, 'version' => '2026-02-01T08:00:00.000000Z', 'attributes' => ['title' => 'First night']],
        ], 'response' => ['selects' => [['field' => 'updated_at']]]]);
        $filter = $this->postJson('/api/shows/search', ['search' => ['filters' => [['field' => 'updated_at', 'value' => '2026-02-01T08:00:00.000000Z']]]]);

        $update->assertOk()->assertJsonPath('records', [['updated_at' => '2026-03-01T09:00:00.000000Z']]);
        $filter->assertUnprocessable()->assertJsonPath('errors', ['search.filters.0.field' => ['The selected search.filters.0.field is invalid.']]);
    }

    #[Test]
    public function it_refuses_to_hide_a_searchable_field(): void
    {
        $this->narrow(static fn (FieldsContext $context): mixed => $context->hide('title'));

        $this->expectExceptionObject(new InvalidFieldExtensionException('The [mask] extension hides [title], a searchable() field of '.ShowResource::class.': its text search would still match the value. Remove searchable() from the field or keep it visible.'));

        $this->withoutExceptionHandling()->postJson('/api/shows/search', ['search' => []]);
    }

    #[Test]
    public function it_lets_a_searchable_field_be_read_only(): void
    {
        $this->narrow(static fn (FieldsContext $context): mixed => $context->readOnly('title'));

        $response = $this->postJson('/api/shows/search', ['search' => ['text' => ['value' => 'Closing'], 'selects' => [['field' => 'title']]]]);

        $response->assertOk()->assertJsonPath('data', [['title' => 'Closing night']]);
    }

    #[Test]
    public function it_creates_a_row_without_a_hidden_nullable_field(): void
    {
        $this->hidePrice();
        $show = ['title' => 'Matinee', 'starts_on' => '2026-07-05', 'status' => 'planned'];

        $created = $this->postJson('/api/shows/mutate', ['mutate' => [['operation' => 'create', 'attributes' => $show]]]);

        $created->assertOk()->assertJsonPath('operations', [['operation' => 'create', 'key' => 3]]);
        $this->assertNull(Show::query()->findOrFail(3)->getAttribute('price'));
        $this->assertLikeUnknown(fn (string $field): TestResponse => $this->postJson('/api/shows/mutate', ['mutate' => [
            ['operation' => 'create', 'attributes' => [...$show, $field => 12]],
        ]]));
    }

    #[Test]
    public function it_keeps_app_metrics_on_declared_fields(): void
    {
        $this->narrow(static fn (FieldsContext $context): mixed => $context->hide('seats'));

        $metric = $this->postJson('/api/shows/metrics/seats-by-level', ['range' => 30]);

        $metric->assertOk()->assertJsonPath('data.parts.*.key', [1, 2]);
        $this->assertSame([300.0, 100.0], array_map(floatval(...), $metric->json('data.parts.*.value')));
        $this->assertLikeUnknown(
            fn (string $field): TestResponse => $this->postJson('/api/shows/metrics/seats-by-level', ['range' => 30, 'search' => ['filters' => [['field' => $field, 'value' => 300]]]]),
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

        $search->assertOk();
        $this->assertSame(['@type', 'id', 'title', 'summary', 'seats', 'featured', 'starts_on', 'starts_at', 'status', 'level', 'settings', 'updated_at'], array_keys($search->json('data.0.notable')));
        $this->assertLikeUnknown(fn (string $field): TestResponse => $this->postJson('/api/notes/search', ['search' => ['filters' => [
            ['relation' => 'notable', 'types' => [Show::class => [['field' => $field, 'value' => 20]]]],
        ]]]));
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
