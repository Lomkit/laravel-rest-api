<?php

namespace Lomkit\Rest\Tests\Feature\Extensions\Stubs;

use Illuminate\Database\Eloquent\Model;
use Lomkit\Rest\Extensions\Contexts\WriteObservationContext;
use Lomkit\Rest\Extensions\Extension;
use Lomkit\Rest\Extensions\Kinds\ObservesWrites;
use Lomkit\Rest\Tests\Feature\Events\Stubs\EventLog;

final class RecordingWriteObserver extends Extension implements ObservesWrites
{
    #[\Override]
    public function observeWrite(WriteObservationContext $context): void
    {
        WriteLog::$writes[] = $context;
        EventLog::record("observe {$context->endpoint->value} ".self::subject($context).($context->impacted === null ? '' : " ({$context->impacted})"));

        if (WriteLog::$observing !== null) {
            (WriteLog::$observing)($context);
        }
    }

    private static function subject(WriteObservationContext $context): string
    {
        if ($context->operations !== null) {
            return implode(',', array_map(fn (array $operation): string => $operation['operation'].':'.$operation['key'], $context->operations));
        }

        if ($context->rows === null) {
            return '*';
        }

        return implode(',', array_map(fn (Model $row): string => (string) $row->getKey(), $context->rows));
    }
}
