<?php

namespace Lomkit\Rest\Tests\Feature\Text\Engine\Stubs;

use Lomkit\Rest\Attributes\WithoutPerimeter;
use Lomkit\Rest\Fields\ID;
use Lomkit\Rest\Fields\Integer;
use Lomkit\Rest\Fields\Text;
use Lomkit\Rest\Http\Resource;
use Lomkit\Rest\Tests\Fixtures\Ledger\Voucher;
use Lomkit\Rest\Text\ScoutEngine;
use Lomkit\Rest\Text\TextSearch;

#[WithoutPerimeter]
class OpenListedVoucherResource extends Resource
{
    public static string $model = Voucher::class;

    #[\Override]
    public function fields(): array
    {
        return [ID::make('id'), Text::make('code'), Integer::make('number')];
    }

    #[\Override]
    public function text(): ?TextSearch
    {
        return ScoutEngine::make()->filterable(['number', 'code'])->sortable(['code'])->withoutPerimeter()->servesLists();
    }
}
