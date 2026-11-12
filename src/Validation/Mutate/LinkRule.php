<?php

namespace Lomkit\Rest\Validation\Mutate;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Lomkit\Rest\Fields\MutationOperation;
use Lomkit\Rest\Mutations\LinkReplay;
use Lomkit\Rest\Relations\Relation;
use Lomkit\Rest\Validation\Cursor;

final readonly class LinkRule implements ValidationRule
{
    public function __construct(
        private LinkReplay $replay,
        private string $path,
    ) {}

    /** @return list<string> */
    public static function paths(Cursor $entry, MutationOperation $operation, ?Relation $relation): array
    {
        return match (true) {
            $relation === null => [],
            $operation === MutationOperation::Update, $operation === MutationOperation::Detach, $operation === MutationOperation::Delete => [$entry->at('key')->path],
            $relation->syncsChildren() && $operation === MutationOperation::Sync => [$entry->at('key')->path],
            $relation->hasMultipleEntries() && $operation === MutationOperation::Attach => [$entry->at('key')->path],
            $relation->hasMultipleEntries() => [],
            $operation === MutationOperation::Create, $operation === MutationOperation::Attach => [$entry->path],
            default => [],
        };
    }

    #[\Override]
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $violation = $this->replay->violation($this->path);

        if ($violation !== null) {
            $fail($violation->message)->translate($violation->replace);
        }
    }
}
