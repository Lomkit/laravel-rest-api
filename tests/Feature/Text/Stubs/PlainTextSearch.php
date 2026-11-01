<?php

namespace Lomkit\Rest\Tests\Feature\Text\Stubs;

use Lomkit\Rest\Text\TextCapabilities;
use Lomkit\Rest\Text\TextMode;
use Lomkit\Rest\Text\TextSearch;
use Lomkit\Rest\Text\TextTarget;

class PlainTextSearch implements TextSearch
{
    #[\Override]
    public function capabilities(TextTarget $target): TextCapabilities
    {
        return new TextCapabilities([], [TextMode::Words], false, false);
    }
}
