<?php

namespace Lomkit\Rest\Tests\Fixtures\Tenancy;

use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Queue\Events\JobFailed;
use Illuminate\Queue\Events\JobProcessed;
use Illuminate\Queue\Events\JobProcessing;
use Illuminate\Queue\Queue;

final class QueueTenancy
{
    private ?string $previous = null;

    private function __construct(private readonly bool $initializesTenant) {}

    public static function listen(Dispatcher $events, bool $initializesTenant): void
    {
        $tenancy = new self($initializesTenant);

        Queue::createPayloadUsing(fn (): array => ['tenant_id' => Tenancy::$tenant]);
        $events->listen(JobProcessing::class, $tenancy->processing(...));
        $events->listen([JobProcessed::class, JobFailed::class], $tenancy->revert(...));
    }

    private function processing(JobProcessing $event): void
    {
        $this->previous = Tenancy::$tenant;
        $tenant = $event->job->payload()['tenant_id'] ?? null;

        Tenancy::initialize($this->initializesTenant && is_string($tenant) ? $tenant : null);
    }

    private function revert(): void
    {
        Tenancy::initialize($this->previous);
    }
}
