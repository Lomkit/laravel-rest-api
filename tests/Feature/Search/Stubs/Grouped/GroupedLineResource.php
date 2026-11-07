<?php

namespace Lomkit\Rest\Tests\Feature\Search\Stubs\Grouped;

use Lomkit\Rest\Fields\ID;
use Lomkit\Rest\Fields\Integer;
use Lomkit\Rest\Fields\Text;
use Lomkit\Rest\Http\Resource;
use Lomkit\Rest\Relations\BelongsTo;

class GroupedLineResource extends Resource
{
    public static string $model = GroupedLine::class;

    #[\Override]
    public function fields(): array
    {
        return [ID::make('id'), Integer::make('voucher_id'), Text::make('label')];
    }

    #[\Override]
    public function relations(): array
    {
        return [BelongsTo::make('voucher', GroupedVoucherResource::class)];
    }
}
