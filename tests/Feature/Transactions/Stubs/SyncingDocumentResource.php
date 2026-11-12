<?php

namespace Lomkit\Rest\Tests\Feature\Transactions\Stubs;

use Closure;
use Lomkit\Rest\Relations\HasMany;

class SyncingDocumentResource extends RetriedDocumentResource
{
    public static string $model = SyncingDocument::class;

    public static ?Closure $whileWriting = null;

    #[\Override]
    public function relations(): array
    {
        return [
            HasMany::make('children', self::class)
                ->deletable()
                ->syncable(),
            HasMany::make('detachedChildren', self::class)->syncable(),
        ];
    }

    #[\Override]
    public function mutatingAll(array $entries): void
    {
        if (self::$whileWriting !== null) {
            (self::$whileWriting)();
        }
    }
}
