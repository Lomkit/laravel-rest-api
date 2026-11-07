<?php

namespace Lomkit\Rest\Tests\Feature\Text\Engine;

use Illuminate\Auth\GenericUser;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\Gate;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;

#[Group('scout')]
class EnginePerimeterPathsTest extends EngineSearchTestCase
{
    /** @var list<string> */
    private array $checks = [];

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        Gate::before(function (?Authenticatable $user, string $ability, array $arguments): ?bool {
            $this->checks[] = $ability.' '.class_basename(is_string($arguments[0] ?? null) ? $arguments[0] : '');

            return null;
        });
    }

    /**
     * @param  array<string, mixed>  $search
     * @param  list<string>  $checks
     */
    #[Test]
    #[DataProvider('paths')]
    public function it_reads_the_rows_of_the_index_and_applies_the_perimeter_of_the_levels_below(string $driver, string $resource, array $search, string $path, mixed $expected, int $total, array $checks): void
    {
        config(['scout.driver' => $driver]);
        $this->actingAs(new GenericUser(['id' => 1, 'teams' => ['blue']]));

        $response = $this->search($resource, ['text' => ['value' => 'report'], ...$search]);

        $response->assertOk()
            ->assertJsonPath($path, $expected)
            ->assertJsonPath('total', $total);
        $this->assertSame($checks, $this->checks);
    }

    /** @return iterable<string, array{string, string, array<string, mixed>, string, mixed, int, list<string>}> */
    public static function paths(): iterable
    {
        $paths = [
            'the rows of an index without perimeter' => ['vouchers', [], 'data.*.id', [5, 4, 1, 3], 4, ['viewAny Voucher']],
            'the rows of an index with a perimeter' => ['team-vouchers', [], 'data.*.id', [4], 1, ['viewAny Voucher']],
            'an include of the rows' => ['vouchers', ['includes' => [['relation' => 'lines', 'includes' => [['relation' => 'voucher']]]]], 'data.*.lines.*.voucher.id', [5, null, 1, 1, 3], 4, ['viewAny Voucher', 'viewAny VoucherLine']],
            'an aggregate of the rows' => ['vouchers', ['aggregates' => [['relation' => 'lines', 'type' => 'count']]], 'data.*.lines_count', [1, 1, 2, 1], 4, ['viewAny Voucher', 'viewAny VoucherLine']],
        ];

        foreach (self::drivers() as $engine => [$driver]) {
            foreach ($paths as $name => $row) {
                yield "{$name} on {$engine}" => [$driver, ...$row];
            }
        }
    }
}
