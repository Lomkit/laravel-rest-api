<?php

namespace Lomkit\Rest\Console\QuickStart;

use Illuminate\Console\View\Components\Factory;
use Illuminate\Support\Str;

final readonly class ExposureReport
{
    public const string LABEL = 'checks (fresh process)';

    /** @param  list<string>  $problems */
    public function __construct(
        private int $routes = 0,
        private int $policies = 0,
        public array $problems = [],
        public ?string $search = null,
    ) {}

    public static function fromJson(string $json): ?self
    {
        $report = json_decode($json, true);

        if (! is_array($report)) {
            return null;
        }

        ['routes' => $routes, 'policies' => $policies, 'problems' => $problems, 'search' => $search] = $report + ['routes' => null, 'policies' => null, 'problems' => null, 'search' => null];

        if (! is_int($routes) || ! is_int($policies) || ! is_array($problems) || ($search !== null && ! is_string($search))) {
            return null;
        }

        return new self($routes, $policies, array_values(array_filter($problems, is_string(...))), $search);
    }

    public function withAuthenticatedRoute(): self
    {
        return new self($this->routes + 1, $this->policies, $this->problems, $this->search);
    }

    public function withPolicy(): self
    {
        return new self($this->routes, $this->policies + 1, $this->problems, $this->search);
    }

    public function withProblem(string $problem): self
    {
        return new self($this->routes, $this->policies, [...$this->problems, $problem], $this->search);
    }

    public function withSearch(string $uri): self
    {
        return new self($this->routes, $this->policies, $this->problems, $this->search ?? '/'.ltrim($uri, '/'));
    }

    public function passed(): bool
    {
        return $this->problems === [];
    }

    public function toJson(): string
    {
        return json_encode([
            'routes' => $this->routes,
            'policies' => $this->policies,
            'problems' => $this->problems,
            'search' => $this->search,
        ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);
    }

    public function render(Factory $components): void
    {
        if ($this->passed()) {
            $components->twoColumnDetail(self::LABEL, $this->summary());

            return;
        }

        $components->twoColumnDetail(self::LABEL, '<fg=red;options=bold>FAILED</>');
        $components->bulletList($this->problems);
    }

    private function summary(): string
    {
        return "{$this->routes} ".Str::plural('route', $this->routes).' authenticated, '
            ."{$this->policies} ".Str::plural('policy', $this->policies).' found';
    }
}
