<?php

namespace Lomkit\Rest\Tests;

use Orchestra\Testbench\TestCase as BaseTestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;

class TestCase extends BaseTestCase
{
    use RefreshDatabase;


    protected function setUp(): void
    {
        parent::setUp();
    }
}