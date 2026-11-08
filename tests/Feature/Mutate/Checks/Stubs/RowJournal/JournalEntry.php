<?php

namespace Lomkit\Rest\Tests\Feature\Mutate\Checks\Stubs\RowJournal;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Lomkit\Rest\Mutations\MutationNode;
use Lomkit\Rest\Tests\Feature\Mutate\Characterization\Stubs\TracingPolicy;
use RuntimeException;

final class JournalEntry
{
    public static ?string $failure = null;

    public static function write(MutationNode $entry, Model $model): void
    {
        $row = TracingPolicy::name($model);

        DB::table('journal_entries')->insert([
            'subject' => $row,
            'note' => trim($entry->operation->value.' '.implode(',', array_keys($entry->relations ?? []))),
        ]);

        if (self::$failure === $row) {
            throw new RuntimeException("The journal entry of {$row} failed.");
        }
    }

    /** @return list<string> */
    public static function rows(): array
    {
        return DB::table('journal_entries')
            ->orderBy('id')
            ->get()
            ->map(fn (object $entry): string => "{$entry->subject} {$entry->note}")
            ->values()
            ->all();
    }
}
