<?php

namespace Lomkit\Rest\Tests\Feature\Actions\Stubs;

use Lomkit\Rest\Actions\Action;
use Lomkit\Rest\Actions\ActionFields;
use Lomkit\Rest\Actions\ActionTarget;

class ConfirmedDocumentResource extends StaffDocumentResource
{
    public static ActionTarget $target = ActionTarget::Search;

    public static ?int $max = 1000;

    /** @var list<string> */
    public static array $hooks = [];

    public static function reset(): void
    {
        self::$target = ActionTarget::Search;
        self::$max = 1000;
        self::$hooks = [];
        ShredDocuments::reset();
    }

    #[\Override]
    public function actions(): array
    {
        return [
            ShredDocuments::make()
                ->target(self::$target)
                ->maxResources(self::$max),
            QueuedShredDocuments::make(),
            BatchedShredDocuments::make(),
            ImportDocuments::make(),
            TouchDocuments::make(),
        ];
    }

    #[\Override]
    public function operating(Action $action, ActionFields $fields): void
    {
        self::$hooks[] = 'operating';
    }
}
