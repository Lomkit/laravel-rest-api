<?php

namespace Lomkit\Rest\Tests\Feature\Confirmation\Stubs;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Lomkit\Rest\Confirmation\Confirmation;
use Lomkit\Rest\Fields\Boolean;
use Lomkit\Rest\Fields\DateTime;
use Lomkit\Rest\Fields\ID;
use Lomkit\Rest\Fields\Text;
use Lomkit\Rest\Mutations\MutationNode;
use Lomkit\Rest\Mutations\PendingMutation;
use Lomkit\Rest\Tests\Fixtures\Archive\DocumentResource;

class ConfirmedWriteResource extends DocumentResource
{
    /** @var list<string> */
    public static array $confirms = [];

    /** @var list<string> */
    public static array $asked = [];

    /** @var list<string> */
    public static array $hooks = [];

    public static function reset(): void
    {
        self::$confirms = ['mutate', 'destroy', 'restore', 'forceDestroy'];
        self::$asked = [];
        self::$hooks = [];
    }

    #[\Override]
    public function fields(): array
    {
        return [
            ID::make('id'),
            Text::make('title'),
            Boolean::make('archived'),
            DateTime::make('updated_at')->readonly(),
            Text::make('note')
                ->virtual()
                ->fillUsing(function (Model $model, mixed $value): void {}),
        ];
    }

    #[\Override]
    public function versioned(): bool
    {
        return true;
    }

    #[\Override]
    public function actions(): array
    {
        return [];
    }

    #[\Override]
    public function mutateConfirmation(PendingMutation $mutation): ?Confirmation
    {
        $rows = array_map(fn (MutationNode $entry): ?Model => is_array($entry->key) || $entry->key === null ? null : $mutation->row($this, $entry->key), $mutation->entries);

        return $this->ask('mutate', new Collection(array_values(array_filter($rows))));
    }

    #[\Override]
    public function destroyConfirmation(Collection $models): ?Confirmation
    {
        return $this->ask('destroy', $models);
    }

    #[\Override]
    public function restoreConfirmation(Collection $models): ?Confirmation
    {
        return $this->ask('restore', $models);
    }

    #[\Override]
    public function forceDestroyConfirmation(Collection $models): ?Confirmation
    {
        return $this->ask('forceDestroy', $models);
    }

    #[\Override]
    public function mutatingAll(array $entries): void
    {
        self::$hooks[] = 'mutatingAll';
    }

    #[\Override]
    public function destroyingAll(Collection $models): void
    {
        self::$hooks[] = 'destroyingAll';
    }

    #[\Override]
    public function restoringAll(Collection $models): void
    {
        self::$hooks[] = 'restoringAll';
    }

    #[\Override]
    public function forceDestroyingAll(Collection $models): void
    {
        self::$hooks[] = 'forceDestroyingAll';
    }

    /** @param Collection<int, Model> $models */
    private function ask(string $write, Collection $models): ?Confirmation
    {
        self::$asked[] = $write;

        if (! in_array($write, self::$confirms, true)) {
            return null;
        }

        return Confirmation::make("{$write}: {$models->count()} documents?")->data(['titles' => $models->pluck('title')->all()]);
    }
}
