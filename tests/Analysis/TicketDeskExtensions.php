<?php

namespace Lomkit\Rest\Tests\Analysis;

use Illuminate\Support\ServiceProvider;
use Lomkit\Rest\Facades\Rest;
use Lomkit\Rest\Http\RestContext;

final class TicketDeskExtensions extends ServiceProvider
{
    #[\Override]
    public function register(): void
    {
        Rest::registerExtension('archive', TicketArchiveExtension::class);
        Rest::registerExtensions(['desk_archive' => TicketArchiveExtension::class]);
    }

    /** @return array<string, string> */
    public function labels(RestContext $context): array
    {
        $labels = [];

        foreach ($context->extensions()->applicable(TicketDeskResource::class, TicketDeskKind::class) as $name => $extension) {
            if ($extension->appliesTo(TicketDeskResource::class)) {
                $labels[$name] = $extension->label();
            }
        }

        return $labels;
    }
}
