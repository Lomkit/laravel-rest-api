<?php

namespace Lomkit\Rest\Tests\Feature\Actions\Stubs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Lomkit\Rest\Tests\Fixtures\Ledger\RetitleVouchers;

class QueuedRetitleVouchers extends RetitleVouchers implements ShouldQueue {}
