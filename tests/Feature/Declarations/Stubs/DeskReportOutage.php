<?php

namespace Lomkit\Rest\Tests\Feature\Declarations\Stubs;

use Lomkit\Rest\Tests\Fixtures\Helpdesk\ReportOutage;

class DeskReportOutage extends ReportOutage
{
    public ?string $name = 'Report Outage';

    public array $withoutMiddleware = ['auth', 'verified'];

    public function __construct()
    {
        DeclarationLog::$built[] = static::class;
    }
}
