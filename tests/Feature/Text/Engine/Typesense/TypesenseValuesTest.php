<?php

namespace Lomkit\Rest\Tests\Feature\Text\Engine\Typesense;

use Illuminate\Auth\GenericUser;
use Illuminate\Support\Facades\Route;
use Lomkit\Rest\Facades\Rest;
use Lomkit\Rest\Tests\Feature\Text\Engine\Stubs\TitledVoucherController;
use Lomkit\Rest\Tests\Feature\Text\Engine\Stubs\TitledVouchers;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;

#[Group('scout')]
#[Group('typesense')]
class TypesenseValuesTest extends TypesenseTestCase
{
    private const array BY_PRICE = [['field' => 'price', 'direction' => 'asc']];

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        TitledVouchers::seed();
        $this->actingAs(new GenericUser(['id' => 1, 'teams' => ['red']]));
    }

    #[\Override]
    protected function defineRoutes($router): void
    {
        parent::defineRoutes($router);

        Route::prefix('api')->group(fn () => Rest::resource('titled-vouchers', TitledVoucherController::class));
    }

    /**
     * @param  array<string, mixed>  $filter
     * @param  list<int>  $ids
     */
    #[Test]
    #[DataProvider('values')]
    public function it_matches_values_with_quotes_backslashes_commas_and_brackets_as_they_are(array $filter, array $ids): void
    {
        $response = $this->search('titled-vouchers', ['text' => ['value' => 'report'], 'filters' => [$filter], 'sorts' => self::BY_PRICE]);

        $response->assertOk()
            ->assertJsonPath('data.*.id', $ids)
            ->assertJsonPath('total', count($ids));
    }

    /** @return array<string, array{array<string, mixed>, list<int>}> */
    public static function values(): array
    {
        return [
            'a double quote' => [['field' => 'title', 'value' => TitledVouchers::QUOTED], [7]],
            'a backslash' => [['field' => 'title', 'value' => TitledVouchers::BACKSLASH], [8]],
            'a trailing backslash' => [['field' => 'title', 'value' => TitledVouchers::TRAILING], [9]],
            'a comma' => [['field' => 'title', 'value' => TitledVouchers::COMMA], [11]],
            'brackets' => [['field' => 'title', 'value' => TitledVouchers::BRACKETS], [12]],
            'a list' => [['field' => 'title', 'operator' => 'in', 'value' => [TitledVouchers::QUOTED, TitledVouchers::TRAILING, TitledVouchers::BRACKETS]], [7, 9, 12]],
            'an excluded list' => [['field' => 'title', 'operator' => 'not in', 'value' => [TitledVouchers::QUOTED, TitledVouchers::BACKSLASH, TitledVouchers::BRACKETS]], [1, 9, 11, 3, 5]],
        ];
    }

    #[Test]
    public function it_sorts_on_three_attributes(): void
    {
        $response = $this->search('titled-vouchers', ['text' => ['value' => 'report'], 'sorts' => [
            ['field' => 'number', 'direction' => 'desc'],
            ['field' => 'price'],
            ['field' => 'code'],
        ]]);

        $response->assertOk()
            ->assertJsonPath('data.*.id', [12, 11, 9, 8, 7, 5, 1, 3])
            ->assertJsonPath('total', 8);
    }

    #[Test]
    public function it_refuses_a_fourth_sort(): void
    {
        $response = $this->search('titled-vouchers', ['text' => ['value' => 'report'], 'sorts' => [
            ['field' => 'number', 'direction' => 'desc'],
            ['field' => 'price'],
            ['field' => 'code'],
            ['field' => 'number'],
        ]]);

        $response->assertUnprocessable()->assertExactJson([
            'message' => 'The search.sorts field must not have more than 3 items.',
            'errors' => ['search.sorts' => ['The search.sorts field must not have more than 3 items.']],
        ]);
    }
}
