<?php

namespace Lomkit\Rest\Tests\Feature\Actions\Stubs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Lomkit\Rest\Actions\Action;
use Lomkit\Rest\Actions\ActionFields;
use Lomkit\Rest\Fields\DateTime;
use Lomkit\Rest\Fields\Enum;
use Lomkit\Rest\Fields\Text;
use Lomkit\Rest\Tests\Fixtures\Archive\Document;

class ExportDocuments extends Action implements ShouldQueue
{
    #[\Override]
    public function fields(): array
    {
        return [
            DateTime::make('due_at'),
            Enum::make('priority', DocumentPriority::class)->default(DocumentPriority::Normal),
            Text::make('note')->nullable(),
        ];
    }

    /** @param Collection<int, Document> $models */
    #[\Override]
    public function handle(ActionFields $fields, Collection $models): void
    {
        QueuedRuns::$runs[] = [
            'user' => $this->request()->user()?->getAuthIdentifier(),
            'guard' => Auth::getDefaultDriver(),
            'fields' => $fields->toArray(),
            'keys' => $models->modelKeys(),
            'shred' => $models->isEmpty() ? null : $this->resource
                ->authorizedTo('shred', $models->first())
                ->allowed(),
            'batch' => $this->batch()?->name,
        ];
    }
}
