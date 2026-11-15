<?php

namespace Lomkit\Rest\Tests\Feature\Extensions\Fields;

use Closure;
use Illuminate\Support\Facades\Route;
use Illuminate\Testing\TestResponse;
use Lomkit\Rest\Exceptions\InvalidFieldExtensionException;
use Lomkit\Rest\Extensions\Contexts\FieldsContext;
use Lomkit\Rest\Facades\Rest;
use Lomkit\Rest\Tests\Concerns\ResetsExtensionStubs;
use Lomkit\Rest\Tests\Feature\Extensions\Stubs\FieldVoucherController;
use Lomkit\Rest\Tests\Feature\Extensions\Stubs\MaskingFields;
use Lomkit\Rest\Tests\Feature\Extensions\Stubs\PriceEngineRestriction;
use Lomkit\Rest\Tests\Feature\Text\Engine\EngineSearchTestCase;
use Lomkit\Rest\Tests\Fixtures\Ledger\TeamVoucherResource;
use Lomkit\Rest\Tests\Fixtures\Ledger\VoucherResource;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;

#[Group('scout')]
class NarrowedEngineGrammarTest extends EngineSearchTestCase
{
    use ResetsExtensionStubs;

    private const string NUMBER = '{"name":"number","type":"integer","operators":["=","!=",">",">=","<","<=","in","not in"]}';

    private const string PRICE = '{"name":"price","type":"number","operators":["=","!=",">",">=","<","<=","in","not in"]}';

    private const string ACTIVE = '{"name":"active","type":"boolean","operators":["=","!="]}';

    private const string CODE = '{"name":"code","type":"text","operators":["=","!=","in","not in"]}';

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        config(['scout.driver' => 'collection', 'rest.extensions' => [MaskingFields::class]]);
    }

    #[Test]
    #[DataProvider('grammarReductions')]
    public function it_drops_a_narrowed_field_from_the_engine_grammar(string $reduction, string $grammar): void
    {
        $this->narrow(static fn (FieldsContext $context): mixed => $context->{$reduction}('price'));

        $this->assertGrammar($grammar, $this->getJson('/api/vouchers?with=text'));
    }

    /** @return array<string, array{string, string}> */
    public static function grammarReductions(): array
    {
        return [
            'hidden from the filters and the sorts' => ['hide', '['.self::NUMBER.','.self::ACTIVE.','.self::CODE.'],"sorts":["number"]'],
            'unfilterable from the filters only' => ['unfilterable', '['.self::NUMBER.','.self::ACTIVE.','.self::CODE.'],"sorts":["price","number"]'],
            'unsortable from the sorts only' => ['unsortable', '['.self::NUMBER.','.self::PRICE.','.self::ACTIVE.','.self::CODE.'],"sorts":["number"]'],
        ];
    }

    #[Test]
    #[DataProvider('drivers')]
    public function it_answers_an_engine_filter_and_sort_on_a_hidden_field_like_an_unknown_name(string $driver): void
    {
        config(['scout.driver' => $driver]);
        $this->narrow(static fn (FieldsContext $context): mixed => $context->hide('price'));

        $this->assertLikeUnknown(fn (string $field): TestResponse => $this->search('vouchers', ['text' => ['value' => 'report'], 'filters' => [['field' => $field, 'operator' => '>', 'value' => 1]]]));
        $this->assertLikeUnknown(fn (string $field): TestResponse => $this->search('vouchers', ['text' => ['value' => 'report'], 'sorts' => [['field' => $field]]]));
    }

    #[Test]
    public function it_matches_an_engine_text_search_on_a_hidden_indexed_value(): void
    {
        $this->narrow(static fn (FieldsContext $context): mixed => $context->hide('title'));

        $response = $this->search('vouchers', ['text' => ['value' => 'inactive']]);

        $response->assertOk()->assertJsonPath('data', [
            ['id' => 5, 'code' => 'V-e', 'number' => 5, 'team' => 'red', 'price' => 40, 'active' => false],
        ]);
        $this->assertStringNotContainsString('inactive', (string) $response->getContent());
    }

    #[Test]
    public function it_narrows_an_engine_attribute_declared_as_a_field_instance(): void
    {
        Route::prefix('api')->group(fn () => Rest::resource('field-vouchers', FieldVoucherController::class)->register());
        $this->narrow(static fn (FieldsContext $context): mixed => $context->hide('price'));

        $response = $this->getJson('/api/field-vouchers?with=text');

        $this->assertGrammar('['.self::NUMBER.','.self::ACTIVE.','.self::CODE.'],"sorts":["number"]', $response);
        $this->assertLikeUnknown(fn (string $field): TestResponse => $this->search('field-vouchers', ['text' => ['value' => 'report'], 'filters' => [['field' => $field, 'value' => 1]]]));
    }

    #[Test]
    public function it_refuses_to_hide_an_engine_perimeter_attribute(): void
    {
        $this->narrow(static fn (FieldsContext $context): mixed => $context->hide('team'));

        $this->expectExceptionObject(new InvalidFieldExtensionException('The [mask] extension '.MaskingFields::class.' hides [team], an attribute that ScoutEngine::perimeter() of '.TeamVoucherResource::class.' reads for every user. Keep it visible: clients cannot filter or sort on a perimeter attribute anyway.'));

        $this->withoutExceptionHandling()->search('team-vouchers', ['text' => ['value' => 'report']]);
    }

    #[Test]
    public function it_lets_an_engine_perimeter_attribute_be_read_only(): void
    {
        $this->narrow(static fn (FieldsContext $context): mixed => $context->readOnly('team'));

        $response = $this->search('team-vouchers', ['text' => ['value' => 'report']]);
        $details = $this->getJson('/api/team-vouchers?with=fields.capabilities');

        $response->assertOk()->assertJsonPath('data', []);
        $details->assertOk()->assertJsonPath('data.fields.3', [
            'name' => 'team',
            'label' => null,
            'type' => 'text',
            'nullable' => false,
            'select' => true,
            'sort' => true,
            'create' => false,
            'update' => false,
        ]);
    }

    #[Test]
    public function it_keeps_app_and_extension_engine_conditions_on_a_hidden_attribute(): void
    {
        $search = ['text' => ['value' => 'report'], 'instructions' => [['name' => 'priced-up-to', 'fields' => [['name' => 'max', 'value' => 10]]]]];
        config(['rest.extensions' => [PriceEngineRestriction::class]]);
        $declared = $this->search('vouchers', $search);
        config(['rest.extensions' => [MaskingFields::class, PriceEngineRestriction::class]]);
        $this->narrow(static fn (FieldsContext $context): mixed => $context->hide('price'));

        $narrowed = $this->search('vouchers', $search);

        $declared->assertOk()->assertJsonPath('data', [
            ['id' => 1, 'code' => 'V-c', 'number' => 3, 'team' => 'red', 'title' => 'Report alpha', 'price' => 1.5, 'active' => true],
            ['id' => 3, 'code' => 'V-a', 'number' => 1, 'team' => 'red', 'title' => 'Report beta', 'price' => 7.25, 'active' => true],
        ]);
        $narrowed->assertOk()->assertJsonPath('data', [
            ['id' => 1, 'code' => 'V-c', 'number' => 3, 'team' => 'red', 'title' => 'Report alpha', 'active' => true],
            ['id' => 3, 'code' => 'V-a', 'number' => 1, 'team' => 'red', 'title' => 'Report beta', 'active' => true],
        ]);
    }

    /** @param Closure(FieldsContext): mixed $narrow */
    private function narrow(Closure $narrow): void
    {
        MaskingFields::$narrow = static function (FieldsContext $context) use ($narrow): void {
            if ($context->resource instanceof VoucherResource) {
                $narrow($context);
            }
        };
    }

    private function assertGrammar(string $grammar, TestResponse $response): void
    {
        $response->assertOk();

        $this->assertSame(
            '{"filters":'.$grammar.',"max_sorts":null,"max_page_size":null,"window":null}',
            json_encode(json_decode((string) $response->getContent(), flags: JSON_THROW_ON_ERROR)->data->text->engine),
        );
    }

    /** @param Closure(string): TestResponse $request */
    private function assertLikeUnknown(Closure $request): void
    {
        $narrowed = $request('price');
        $unknown = $request('colour');

        $narrowed->assertUnprocessable();
        $this->assertSame(str_replace('colour', 'price', (string) $unknown->getContent()), $narrowed->getContent());
    }
}
