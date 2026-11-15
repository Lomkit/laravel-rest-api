<?php

namespace Lomkit\Rest\Tests\Feature\Extensions\Stubs;

use Illuminate\Database\Eloquent\Model;
use Lomkit\Rest\Extensions\Contexts\WriteGuardContext;
use Lomkit\Rest\Extensions\Extension;
use Lomkit\Rest\Extensions\Kinds\GuardsWrites;
use Lomkit\Rest\Mutations\MutationNode;
use Lomkit\Rest\Tests\Feature\Events\Stubs\EventLog;

final class RecordingGuard extends Extension implements GuardsWrites
{
    #[\Override]
    public function guardWrite(WriteGuardContext $context): void
    {
        WriteLog::$guards[] = $context;
        EventLog::record("guard {$context->endpoint->value} ".self::subject($context));

        if (count(WriteLog::$guards) === WriteLog::$refuseAt) {
            $context->refuse(WriteLog::$refusal, WriteLog::$status, WriteLog::$headers);
        }
    }

    private static function subject(WriteGuardContext $context): string
    {
        if ($context->entries !== null) {
            return implode(',', array_map(fn (MutationNode $entry): string => $entry->operation->value, $context->entries));
        }

        return $context->rows === [] || $context->rows === null ? '-' : implode(',', array_map(fn (Model $row): string => (string) $row->getKey(), $context->rows));
    }
}
