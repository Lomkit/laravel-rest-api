<?php

namespace Lomkit\Rest\Tests\Feature\Scaffolding\Stubs;

use Lomkit\Rest\Tests\Fixtures\Ledger\VoucherResource;
use Lomkit\Rest\Text\TextSearch;
use RuntimeException;

final class RouteBoundVoucherResource extends VoucherResource
{
    public static int $textCalls = 0;

    #[\Override]
    public function relations(): array
    {
        return [];
    }

    #[\Override]
    public function actions(): array
    {
        return [];
    }

    #[\Override]
    public function instructions(): array
    {
        return [];
    }

    #[\Override]
    public function text(): ?TextSearch
    {
        self::$textCalls++;

        throw new RuntimeException('text() needs the request route');
    }
}
