<?php

namespace Lomkit\Rest\Tests\Feature\Text\Engine;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;

#[Group('scout')]
class EngineValueLengthTest extends EngineSearchTestCase
{
    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        config(['scout.driver' => 'collection']);
    }

    /** @param array<string, mixed> $filter */
    #[Test]
    #[DataProvider('filters')]
    public function it_refuses_an_engine_filter_value_over_the_limit(array $filter, string $key): void
    {
        $message = "The {$key} field must not be greater than 1000 characters.";

        $response = $this->search('vouchers', ['text' => ['value' => 'report'], 'filters' => [$filter]]);

        $response->assertUnprocessable()->assertExactJson(['message' => $message, 'errors' => [$key => [$message]]]);
    }

    /** @return array<string, array{array<string, mixed>, string}> */
    public static function filters(): array
    {
        $long = str_repeat('V', 1001);

        return [
            'a value' => [['field' => 'code', 'value' => $long], 'search.filters.0.value'],
            'an item of a list' => [['field' => 'code', 'operator' => 'in', 'value' => ['V-a', $long]], 'search.filters.0.value.1'],
        ];
    }

    #[Test]
    public function it_accepts_an_engine_filter_value_at_the_limit(): void
    {
        $response = $this->search('vouchers', ['text' => ['value' => 'report'], 'filters' => [['field' => 'code', 'value' => str_repeat('V', 1000)]]]);

        $response->assertOk()->assertJsonPath('data', []);
    }
}
