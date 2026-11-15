<?php

namespace Lomkit\Rest\Tests\Analysis;

use Illuminate\Contracts\Config\Repository;
use Lomkit\Rest\Extensions\Extension;

final class TicketArchiveExtension extends Extension implements TicketDeskKind
{
    public function __construct(private readonly Repository $config) {}

    #[\Override]
    public static function name(): string
    {
        return 'archive';
    }

    #[\Override]
    public function appliesTo(string $resource): bool
    {
        return $resource === TicketDeskResource::class;
    }

    #[\Override]
    public function label(): string
    {
        return $this->config->string('app.name');
    }
}
