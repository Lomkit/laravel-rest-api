<?php

namespace Lomkit\Rest\Tests\Feature\Text\Engine\Stubs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Lomkit\Rest\Tests\Fixtures\Ledger\DeactivateVouchers;

class QueuedDeactivateVouchers extends DeactivateVouchers implements ShouldQueue {}
