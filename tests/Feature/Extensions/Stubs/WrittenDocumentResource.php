<?php

namespace Lomkit\Rest\Tests\Feature\Extensions\Stubs;

use Illuminate\Database\Eloquent\Collection;
use Lomkit\Rest\Actions\Action;
use Lomkit\Rest\Actions\ActionFields;
use Lomkit\Rest\Confirmation\Confirmation;
use Lomkit\Rest\Mutations\MutationNode;
use Lomkit\Rest\Mutations\PendingMutation;
use Lomkit\Rest\Tests\Feature\Events\Stubs\EventLog;
use Lomkit\Rest\Tests\Fixtures\Archive\DocumentResource;

final class WrittenDocumentResource extends DocumentResource
{
    public static bool $confirming = false;

    #[\Override]
    public function actions(): array
    {
        return [
            ArchiveDocumentsByOne::make(),
            AtomicArchiveDocuments::make()->atomic(),
            DirectArchiveDocuments::make()->withoutTransaction(),
            ArchiveEachDocument::make()->transactionPerRow(),
            QueuedArchiveDocumentsByOne::make(),
            RetriedQueuedArchiveDocuments::make(),
            BatchedArchiveDocumentsByOne::make(),
            FailingArchiveDocuments::make(),
            ConfirmedArchiveDocuments::make(),
        ];
    }

    #[\Override]
    public function mutatingAll(array $entries): void
    {
        EventLog::record('mutatingAll '.implode(',', array_map(fn (MutationNode $entry): string => $entry->operation->value, $entries)));
    }

    #[\Override]
    public function mutatedAll(array $operations): void
    {
        EventLog::record('mutatedAll '.count($operations));
    }

    #[\Override]
    public function operating(Action $action, ActionFields $fields): void
    {
        EventLog::record('operating '.$action->uriKey());
    }

    #[\Override]
    public function operated(Action $action, ActionFields $fields, int $impacted): void
    {
        EventLog::record('operated '.$action->uriKey().' '.$impacted);
    }

    #[\Override]
    public function destroyingAll(Collection $models): void
    {
        EventLog::record('destroyingAll '.implode(',', $models->modelKeys()));
    }

    #[\Override]
    public function destroyedAll(Collection $models): void
    {
        EventLog::record('destroyedAll '.implode(',', $models->modelKeys()));
    }

    #[\Override]
    public function restoringAll(Collection $models): void
    {
        EventLog::record('restoringAll '.implode(',', $models->modelKeys()));
    }

    #[\Override]
    public function restoredAll(Collection $models): void
    {
        EventLog::record('restoredAll '.implode(',', $models->modelKeys()));
    }

    #[\Override]
    public function forceDestroyingAll(Collection $models): void
    {
        EventLog::record('forceDestroyingAll '.implode(',', $models->modelKeys()));
    }

    #[\Override]
    public function forceDestroyedAll(Collection $models): void
    {
        EventLog::record('forceDestroyedAll '.implode(',', $models->modelKeys()));
    }

    #[\Override]
    public function mutateConfirmation(PendingMutation $mutation): ?Confirmation
    {
        return self::$confirming ? Confirmation::make('Change the documents?') : null;
    }

    #[\Override]
    public function destroyConfirmation(Collection $models): ?Confirmation
    {
        return self::$confirming ? Confirmation::make('Delete the documents?') : null;
    }
}
