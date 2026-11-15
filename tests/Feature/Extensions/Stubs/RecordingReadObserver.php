<?php

namespace Lomkit\Rest\Tests\Feature\Extensions\Stubs;

use Illuminate\Database\Eloquent\Model;
use Lomkit\Rest\Extensions\Contexts\ReadObservationContext;
use Lomkit\Rest\Extensions\Extension;
use Lomkit\Rest\Extensions\Kinds\ObservesReads;
use Lomkit\Rest\Http\Routing\Endpoint;
use Lomkit\Rest\Tests\Feature\Events\Stubs\EventLog;

class RecordingReadObserver extends Extension implements ObservesReads
{
    #[\Override]
    public static function name(): string
    {
        return 'reads';
    }

    #[\Override]
    public function observeRead(ReadObservationContext $context): void
    {
        WriteLog::$reads[] = $context;
        EventLog::record("read {$context->endpoint->value} ".self::subject($context));
    }

    private static function subject(ReadObservationContext $context): string
    {
        if ($context->endpoint === Endpoint::Search) {
            return implode(',', array_map(fn (Model $row): string => $row->getKey().' '.$row->getAttribute('title'), $context->rows ?? []))." of {$context->total}";
        }

        if ($context->endpoint === Endpoint::Metric) {
            return (string) json_encode($context->result);
        }

        return implode(',', array_keys($context->details ?? []));
    }
}
