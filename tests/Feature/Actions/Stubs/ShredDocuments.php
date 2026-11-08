<?php

namespace Lomkit\Rest\Tests\Feature\Actions\Stubs;

use Illuminate\Bus\PendingBatch;
use Illuminate\Support\Collection;
use Lomkit\Rest\Actions\Action;
use Lomkit\Rest\Actions\ActionFields;
use Lomkit\Rest\Confirmation\Confirmation;
use Lomkit\Rest\Fields\Text;
use Lomkit\Rest\Tests\Fixtures\Archive\Document;

class ShredDocuments extends Action
{
    /** @var list<int> */
    public static array $asked = [];

    /** @var list<list<int>> */
    public static array $runs = [];

    /** @var list<string> */
    public static array $batches = [];

    public static int $from = 1;

    public static int $seconds = 300;

    public static function reset(): void
    {
        self::$asked = [];
        self::$runs = [];
        self::$batches = [];
        self::$from = 1;
        self::$seconds = 300;
    }

    #[\Override]
    public function fields(): array
    {
        return [Text::make('note')->nullable()];
    }

    #[\Override]
    public function authorizeEach(): ?string
    {
        return 'shred';
    }

    #[\Override]
    public function confirmation(ActionFields $fields, int $rows): ?Confirmation
    {
        self::$asked[] = $rows;

        if ($rows < self::$from) {
            return null;
        }

        return Confirmation::make("Shred {$rows} documents?")
            ->data(['rows' => $rows])
            ->expiresIn(self::$seconds);
    }

    /** @param Collection<int, Document> $models */
    #[\Override]
    public function handle(ActionFields $fields, Collection $models): void
    {
        self::$runs[] = $models->modelKeys();
    }

    #[\Override]
    public function withBatch(ActionFields $fields, PendingBatch $batch): void
    {
        self::$batches[] = $this->uriKey();
    }
}
