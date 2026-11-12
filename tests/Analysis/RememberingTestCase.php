<?php

namespace Lomkit\Rest\Tests\Analysis;

use Lomkit\Rest\Testing\ForgetsRoutes;
use PHPUnit\Framework\TestCase;

abstract class RememberingTestCase extends TestCase
{
    use ForgetsRoutes;
}
