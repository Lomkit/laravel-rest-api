<?php

namespace Lomkit\Rest\Authorization;

use Closure;

final class DecisionBatch
{
    /** @var array<string, Decision|null> */
    private array $read = [];

    /** @var array<string, Check> */
    private array $checks = [];

    /** @var array<string, Decision> */
    private array $decisions = [];

    private int $depth = 0;

    public function __construct(
        private readonly DecisionStore $store,
        private readonly DecisionMemo $memo,
        private readonly DecisionPreparation $preparation = new DecisionPreparation,
    ) {}

    /**
     * @template TResult
     *
     * @param  array<string, Check>  $checks
     * @param  Closure(): TResult  $decide
     * @return TResult
     */
    public function around(array $checks, Closure $decide): mixed
    {
        $storable = $this->read($checks);
        $this->depth++;

        try {
            $this->preparation->prepare($checks, $this->decided($storable));

            return $decide();
        } finally {
            $this->depth--;
            $this->flush();
        }
    }

    /**
     * @param  array<string, Check>  $checks
     * @param  Closure(Check): Decision  $evaluate
     * @return array<string, Decision>
     */
    public function resolve(array $checks, Closure $evaluate): array
    {
        $storable = $this->read($checks);
        $decisions = [];

        try {
            $this->preparation->prepare($checks, $this->decided($storable));

            foreach ($checks as $key => $check) {
                $stored = isset($storable[$key]) ? $this->read[$key] ?? null : null;
                $decisions[$key] = $stored ?? $evaluate($check);

                if ($stored === null && isset($storable[$key])) {
                    $this->checks[$key] = $check;
                    $this->decisions[$key] = $decisions[$key];
                }
            }
        } finally {
            $this->flush();
        }

        return $decisions;
    }

    /**
     * @param  array<string, Check>  $checks
     * @return array<string, Check>
     */
    private function read(array $checks): array
    {
        $storable = array_filter($checks, fn (Check $check): bool => ! $this->memo->involvesForgotten($check));
        $unread = array_diff_key($storable, $this->read);

        if ($unread === []) {
            return $storable;
        }

        foreach ($this->store->many($unread) + array_fill_keys(array_keys($unread), null) as $key => $decision) {
            $this->read[$key] = $decision;
        }

        return $storable;
    }

    /**
     * @param  array<string, Check>  $storable
     * @return Closure(string): bool
     */
    private function decided(array $storable): Closure
    {
        return fn (string $key): bool => isset($storable[$key], $this->read[$key]);
    }

    private function flush(): void
    {
        if ($this->depth > 0) {
            return;
        }

        $checks = $this->checks;
        $decisions = $this->decisions;
        $this->read = $this->checks = $this->decisions = [];
        $this->preparation->reset();

        if ($checks !== []) {
            $this->store->putMany($checks, $decisions);
        }
    }
}
