<?php

namespace Lomkit\Rest\Console\QuickStart;

use Illuminate\Contracts\Auth\Access\Gate;

final readonly class PolicyLookup
{
    /** @param  list<string>  $candidates */
    private function __construct(public ?object $policy, private array $candidates) {}

    public static function for(Gate $gate, string $model): self
    {
        $candidates = [];
        $record = function (string $class) use (&$candidates): void {
            $candidates[] = $class;
        };

        spl_autoload_register($record, prepend: true);

        try {
            $policy = $gate->getPolicyFor($model);
        } finally {
            spl_autoload_unregister($record);
        }

        return new self(is_object($policy) ? $policy : null, $candidates);
    }

    public function wouldDiscover(string $policy): bool
    {
        return in_array($policy, $this->candidates, true);
    }
}
