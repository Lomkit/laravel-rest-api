<?php

namespace Lomkit\Rest\Tests\Feature\Actions\Stubs;

use Illuminate\Contracts\Database\Eloquent\Builder;
use Lomkit\Rest\Actions\ActionTarget;
use Lomkit\Rest\Tests\Fixtures\Workspace\TeamNoteResource;

class TargetNoteResource extends TeamNoteResource
{
    public static ?ActionTarget $declared = null;

    public static ActionTarget $queued = ActionTarget::Search;

    #[\Override]
    public function actions(): array
    {
        $record = RecordNotes::make();

        return [
            self::$declared === null ? $record : $record->target(self::$declared),
            RecordNamedNotes::make(),
            RecordNoNote::make(),
            QueuedRecordNotes::make()->target(self::$queued),
        ];
    }

    #[\Override]
    public function perimeterQuery(Builder $query): Builder
    {
        return $query->where('team', 'red')->orWhere('title', 'Public');
    }
}
