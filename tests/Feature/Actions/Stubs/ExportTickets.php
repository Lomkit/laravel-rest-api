<?php

namespace Lomkit\Rest\Tests\Feature\Actions\Stubs;

use Illuminate\Bus\PendingBatch;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Lomkit\Rest\Actions\Action;
use Lomkit\Rest\Actions\ActionFields;
use Lomkit\Rest\Actions\ActionResponse;
use Lomkit\Rest\Contracts\BatchableAction;
use Lomkit\Rest\Fields\Json;
use Lomkit\Rest\Tests\Fixtures\Helpdesk\Ticket;

class ExportTickets extends Action implements BatchableAction, ShouldQueue
{
    public const array COLUMNS = ['id', 'title', 'status'];

    public int $chunkCount = 500;

    public ?int $maxResources = 50000;

    private string $export = '';

    #[\Override]
    public function fields(): array
    {
        return [Json::make('columns')->rules(['required', 'list', Rule::in(self::COLUMNS)])];
    }

    #[\Override]
    public function withBatch(ActionFields $fields, PendingBatch $batch): void
    {
        $export = $this->export = (string) Str::uuid();
        $columns = $fields->columns;

        $batch->withOption('export', $export)->then(static function () use ($export, $columns): void {
            $disk = Storage::disk('exports');
            $csv = fopen('php://temp', 'w+');
            fputcsv($csv, $columns, escape: '');
            $parts = $disk->files($export);
            sort($parts, SORT_NATURAL);

            foreach ($parts as $part) {
                stream_copy_to_stream($disk->readStream($part), $csv);
            }

            rewind($csv);
            $disk->writeStream("{$export}.csv", $csv);
            $disk->deleteDirectory($export);
        });
    }

    /** @param Collection<int, Ticket> $models */
    #[\Override]
    public function handle(ActionFields $fields, Collection $models): void
    {
        $csv = fopen('php://temp', 'w+');

        foreach ($models as $ticket) {
            fputcsv($csv, $ticket->only($fields->columns), escape: '');
        }

        rewind($csv);
        Storage::disk('exports')->writeStream($this->batch()?->options['export'].'/'.$models->first()?->getKey().'.csv', $csv);
    }

    #[\Override]
    public function respond(ActionFields $fields, int $impacted): ActionResponse
    {
        if ($impacted === 0) {
            return ActionResponse::message('No rows to export.');
        }

        return ActionResponse::download(Storage::disk('exports')->temporaryUrl("{$this->export}.csv", now()->addDay()), 'tickets.csv');
    }
}
