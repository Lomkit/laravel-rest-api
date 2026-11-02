<?php

namespace Lomkit\Rest\Tests\Unit\Search;

use Lomkit\Rest\Fields\AggregateFunction;
use Lomkit\Rest\Fields\Field;
use Lomkit\Rest\Fields\ID;
use Lomkit\Rest\Fields\Integer;
use Lomkit\Rest\Search\AggregateKey;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class AggregateKeyTest extends TestCase
{
    #[Test]
    #[DataProvider('aggregates')]
    public function it_derives_the_key_of_an_aggregate(AggregateFunction $function, ?Field $field, ?string $alias, string $key): void
    {
        $this->assertSame($key, AggregateKey::of('posts', $function, $field, $alias)->value);
    }

    /** @return array<string, array{AggregateFunction, ?Field, ?string, string}> */
    public static function aggregates(): array
    {
        return [
            'a field' => [AggregateFunction::Sum, Integer::make('views'), null, 'posts_sum_views'],
            'an alias' => [AggregateFunction::Max, ID::make('id'), 'last_post', 'last_post'],
            'an alias wins' => [AggregateFunction::Sum, Integer::make('views'), 'total_views', 'total_views'],
            'a function without a field' => [AggregateFunction::Exists, null, null, 'posts_exists'],
        ];
    }
}
