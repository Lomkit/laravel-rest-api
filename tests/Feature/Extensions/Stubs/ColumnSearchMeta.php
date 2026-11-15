<?php

namespace Lomkit\Rest\Tests\Feature\Extensions\Stubs;

use Illuminate\Database\Eloquent\Model;
use Lomkit\Rest\Extensions\Contexts\SearchMetaContext;
use Lomkit\Rest\Extensions\Extension;
use Lomkit\Rest\Extensions\Kinds\AddsSearchMeta;

final class ColumnSearchMeta extends Extension implements AddsSearchMeta
{
    #[\Override]
    public static function name(): string
    {
        return 'teams';
    }

    #[\Override]
    public function searchMeta(SearchMetaContext $context): array
    {
        return ['teams' => array_map(fn (Model $row): mixed => $row->getAttribute('team_id'), $context->rows)];
    }
}
