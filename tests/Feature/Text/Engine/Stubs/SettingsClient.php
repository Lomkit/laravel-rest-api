<?php

namespace Lomkit\Rest\Tests\Feature\Text\Engine\Stubs;

use Closure;

class SettingsClient
{
    /** @var list<string> */
    public array $indexes = [];

    /** @param Closure(): mixed $settings */
    public function __construct(private readonly Closure $settings) {}

    public function index(string $uid): self
    {
        $this->indexes[] = $uid;

        return $this;
    }

    public function initIndex(string $name): self
    {
        return $this->index($name);
    }

    public function getPagination(): mixed
    {
        return ($this->settings)();
    }

    public function getSettings(?string $name = null): mixed
    {
        if ($name !== null) {
            $this->indexes[] = $name;
        }

        return ($this->settings)();
    }
}
