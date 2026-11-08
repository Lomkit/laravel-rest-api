<?php

namespace Lomkit\Rest\Tests\Feature\Transactions\Stubs;

use Closure;
use Lomkit\Rest\Confirmation\Confirmation;
use Lomkit\Rest\Fields\DateTime;
use Lomkit\Rest\Fields\ID;
use Lomkit\Rest\Fields\Text;
use Lomkit\Rest\Mutations\PendingMutation;

class VersionedDocumentResource extends RetriedDocumentResource
{
    public static ?Closure $whileWriting = null;

    public static bool $confirms = false;

    public static int $asked = 0;

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
    public function mutateConfirmation(PendingMutation $mutation): ?Confirmation
    {
        self::$asked++;

        return self::$confirms ? Confirmation::make('Rename the report?') : null;
    }

    #[\Override]
    public function mutatingAll(array $entries): void
    {
        if (self::$whileWriting !== null) {
            (self::$whileWriting)();
        }
    }
}
