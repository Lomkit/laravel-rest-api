<?php

namespace Lomkit\Rest\Tests\Fixtures\Atlas;

class RemoteTrip extends Trip
{
    protected $connection = 'remote';

    protected $table = 'trips';
}
