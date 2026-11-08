<?php

namespace Lomkit\Rest\Tests\Feature\Transactions\Stubs;

use Closure;
use Lomkit\Rest\Fields\DateTime;
use Lomkit\Rest\Fields\ID;
use Lomkit\Rest\Fields\Text;

class VersionedDocumentResource extends RetriedDocumentResource
{
    public static ?Closure $whileWriting = null;

    #[\Override]
    public function fields(): array
    {
        return [ID::make('id'), Text::make('title'), DateTime::make('updated_at')->readonly()];
    }

    #[\Override]
    public function versioned(): bool
    {
        return true;
    }

    #[\Override]
    public function mutatingAll(array $entries): void
    {
        if (self::$whileWriting !== null) {
            (self::$whileWriting)();
        }
    }
}
