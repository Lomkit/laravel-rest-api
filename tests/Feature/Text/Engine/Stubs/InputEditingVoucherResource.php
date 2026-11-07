<?php

namespace Lomkit\Rest\Tests\Feature\Text\Engine\Stubs;

use Lomkit\Rest\Actions\Action;
use Lomkit\Rest\Actions\ActionFields;
use Lomkit\Rest\Search\SearchContext;
use Lomkit\Rest\Tests\Fixtures\Ledger\VoucherResource;

class InputEditingVoucherResource extends VoucherResource
{
    private const array SEARCH = [
        'text' => ['value' => 'report'],
        'filters' => [['field' => 'number', 'value' => 5]],
        'sorts' => [['field' => 'price', 'direction' => 'asc']],
        'instructions' => [['name' => 'numbered-from', 'fields' => [['name' => 'min', 'value' => 3]]]],
        'limit' => 2,
        'page' => 2,
    ];

    #[\Override]
    public function searching(SearchContext $search): void
    {
        $this->request()->merge(['search' => self::SEARCH]);
    }

    #[\Override]
    public function operating(Action $action, ActionFields $fields): void
    {
        $this->request()->merge(['search' => self::SEARCH]);
    }
}
