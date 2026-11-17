<?php

namespace Lomkit\Rest\Tests\Feature\Actions\Stubs;

use Illuminate\Contracts\Queue\ShouldQueue;

class QueuedLockVouchers extends LockVouchers implements ShouldQueue {}
