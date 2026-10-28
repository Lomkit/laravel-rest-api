<?php

namespace Lomkit\Rest\Tests\Fixtures;

interface Fixture
{
    public function migrations(): string;

    public function routes(): void;
}
