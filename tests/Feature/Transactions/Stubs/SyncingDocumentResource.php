<?php

namespace Lomkit\Rest\Tests\Feature\Transactions\Stubs;

use Closure;
use Lomkit\Rest\Relations\ChildRemoval;
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
                ->syncable(ChildRemoval::Delete),
            HasMany::make('detachedChildren', self::class)->syncable(ChildRemoval::Detach),
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
