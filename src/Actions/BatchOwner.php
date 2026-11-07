<?php

namespace Lomkit\Rest\Actions;

use Illuminate\Bus\Batch;
use Illuminate\Bus\PendingBatch;
use Lomkit\Rest\Authorization\UserReference;

final readonly class BatchOwner
{
    private const string OPTION = 'lomkit_rest_owner';

    /** @param array{resource: class-string, action: string, guard: string, user: ?string, id: int|string|null} $owner */
    private function __construct(private array $owner) {}

    public static function of(Action $action, UserReference $user): self
    {
        return new self([
            'resource' => $action->resource::class,
            'action' => $action->uriKey(),
            'guard' => $user->guard,
            'user' => $user->class,
            'id' => $user->id,
        ]);
    }

    public function tag(PendingBatch $batch): void
    {
        $batch->withOption(self::OPTION, $this->owner);
    }

    public function owns(Batch $batch): bool
    {
        return $this->owner['user'] !== null && ($batch->options[self::OPTION] ?? null) === $this->owner;
    }
}
