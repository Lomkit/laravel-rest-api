<?php

namespace Lomkit\Rest\Fields;

enum MutationOperation: string
{
    case Create = 'create';
    case Update = 'update';
    case Attach = 'attach';
    case Detach = 'detach';
    case Sync = 'sync';
    case Toggle = 'toggle';

    public static function tryFromEntry(mixed $entry): ?self
    {
        $operation = is_array($entry) ? ($entry['operation'] ?? null) : null;

        return is_string($operation) ? self::tryFrom($operation) : null;
    }

    public function writeOperation(): ?WriteOperation
    {
        return match ($this) {
            self::Create => WriteOperation::Create,
            self::Update, self::Sync, self::Toggle => WriteOperation::Update,
            self::Attach, self::Detach => null,
        };
    }

    public function pivotWriteOperation(): ?WriteOperation
    {
        return match ($this) {
            self::Create, self::Attach => WriteOperation::Create,
            self::Update, self::Sync, self::Toggle => WriteOperation::Update,
            self::Detach => null,
        };
    }

    public function requiresKey(): bool
    {
        return $this !== self::Create;
    }
}
