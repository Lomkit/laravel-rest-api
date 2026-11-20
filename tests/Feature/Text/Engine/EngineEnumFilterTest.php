<?php

namespace Lomkit\Rest\Tests\Feature\Text\Engine;

use Illuminate\Support\Facades\Route;
use Lomkit\Rest\Facades\Rest;
use Lomkit\Rest\Tests\Feature\Text\Engine\Stubs\KindFieldVoucherController;
use Lomkit\Rest\Tests\Feature\Text\Engine\Stubs\KindVoucherController;
use Lomkit\Rest\Tests\Feature\Text\Engine\Stubs\RecordingCollectionEngine;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;

#[Group('scout')]
class EngineEnumFilterTest extends EngineSearchTestCase
{
    private const string INVALID = 'The selected %s is invalid.';

    private RecordingCollectionEngine $engine;

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        $this->engine = new RecordingCollectionEngine;
        $this->useEngine($this->engine);
        Route::prefix('api')->group(function (): void {
            Rest::resource('kind-vouchers', KindVoucherController::class)->register();
            Rest::resource('kind-field-vouchers', KindFieldVoucherController::class)->register();
        });
    }

    /** @param array<string, mixed> $search */
    #[Test]
    #[DataProvider('unknownValues')]
    public function it_refuses_a_value_outside_the_enum_before_searching(string $resource, array $search, string $key): void
    {
        $response = $this->search($resource, $search);

        $message = sprintf(self::INVALID, $key);
        $response->assertUnprocessable()->assertExactJson(['message' => $message, 'errors' => [$key => [$message]]]);
        $this->assertSame([], $this->engine->searches);
    }

    /** @return array<string, array{string, array<string, mixed>, string}> */
    public static function unknownValues(): array
    {
        $equal = ['filters' => [['field' => 'kind', 'value' => 'refund']]];
        $in = ['filters' => [['field' => 'kind', 'operator' => 'in', 'value' => ['credit', 'refund']]]];

        return [
            'a text search' => ['kind-vouchers', ['text' => ['value' => 'report'], ...$equal], 'search.filters.0.value'],
            'a text search with in' => ['kind-vouchers', ['text' => ['value' => 'report'], ...$in], 'search.filters.0.value.1'],
            'an engine list' => ['kind-vouchers', ['source' => 'engine', ...$equal], 'search.filters.0.value'],
            'an engine list with in' => ['kind-vouchers', ['source' => 'engine', ...$in], 'search.filters.0.value.1'],
            'a text search on a field of the resource' => ['kind-field-vouchers', ['text' => ['value' => 'report'], ...$equal], 'search.filters.0.value'],
            'an engine list on a field of the resource' => ['kind-field-vouchers', ['source' => 'engine', ...$equal], 'search.filters.0.value'],
            'an engine list with in on a field of the resource' => ['kind-field-vouchers', ['source' => 'engine', ...$in], 'search.filters.0.value.1'],
        ];
    }
}
