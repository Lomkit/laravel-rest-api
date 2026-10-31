<?php

namespace Lomkit\Rest\Tests\Fixtures\Ledger;

use Illuminate\Contracts\Queue\ShouldQueue;

class QueuedReviewVouchers extends ReviewVouchers implements ShouldQueue {}
