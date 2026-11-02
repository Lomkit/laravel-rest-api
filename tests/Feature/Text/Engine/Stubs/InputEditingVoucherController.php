<?php

namespace Lomkit\Rest\Tests\Feature\Text\Engine\Stubs;

use Lomkit\Rest\Http\Controllers\Controller;
use Lomkit\Rest\Http\Requests\OperateRequest;
use Lomkit\Rest\Http\Requests\SearchRequest;
use Lomkit\Rest\Tests\Fixtures\Ledger\VoucherResource;

class InputEditingVoucherController extends Controller
{
    public static string $resource = VoucherResource::class;

    private const array SEARCH = [
        'text' => ['value' => 'report'],
        'filters' => [['field' => 'number', 'value' => 5]],
        'sorts' => [['field' => 'price', 'direction' => 'asc']],
        'instructions' => [['name' => 'numbered-from', 'fields' => [['name' => 'min', 'value' => 3]]]],
        'limit' => 2,
        'page' => 2,
    ];

    #[\Override]
    protected function beforeSearch(SearchRequest $request): void
    {
        $request->merge(['search' => self::SEARCH]);
    }

    #[\Override]
    protected function beforeOperate(OperateRequest $request): void
    {
        $request->merge(['search' => self::SEARCH]);
    }
}
