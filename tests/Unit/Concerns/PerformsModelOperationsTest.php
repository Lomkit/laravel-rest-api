<?php

namespace Lomkit\Rest\Tests\Unit\Concerns;

use Lomkit\Rest\Exceptions\InvalidResourceException;
use Lomkit\Rest\Tests\Fixtures\Helpdesk\Ticket;
use Lomkit\Rest\Tests\Fixtures\Helpdesk\TicketResource;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class PerformsModelOperationsTest extends TestCase
{
    #[Test]
    public function it_refuses_to_restore_a_model_without_a_restore_method(): void
    {
        $this->expectExceptionObject(new InvalidResourceException('The resource [Lomkit\Rest\Tests\Fixtures\Helpdesk\TicketResource] restores a row of the model [Lomkit\Rest\Tests\Fixtures\Helpdesk\Ticket], which has no restore() method. Use Illuminate\Database\Eloquent\SoftDeletes in the model.'));

        (new TicketResource)->performRestore(new Ticket);
    }
}
