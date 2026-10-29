<?php

namespace Lomkit\Rest\Authorization;

use Closure;
use Illuminate\Database\Eloquent\Model;

final class DecisionMemo
{
    /** @var array<string, Decision> */
    private array $decisions = [];

    /** @var array<string, Check> */
    private array $checks = [];

    /** @var array<string, list<string>> */
    private array $rows = [];

    /** @var array<string, array<string, true>> */
    private array $keysByRow = [];

    /** @var array<string, true> */
    private array $forgotten = [];

    public function has(string $key): bool
    {
        return isset($this->checks[$key]);
    }

    /** @param Closure(): Decision $resolver */
    public function remember(string $key, Check $check, Closure $resolver): Decision
    {
        if (isset($this->decisions[$key])) {
            return $this->decisions[$key];
        }

        $this->decisions[$key] = $resolver();
        $this->checks[$key] = $check;
        $this->rows[$key] = $check->rows();

        foreach ($this->rows[$key] as $row) {
            $this->keysByRow[$row][$key] = true;
        }

        return $this->decisions[$key];
    }

    public function forget(Model $model): void
    {
        foreach (Check::rowsOf($model) as $row) {
            $this->forgotten[$row] = true;

            foreach (array_keys($this->keysByRow[$row] ?? []) as $key) {
                $this->drop($key);
            }

            unset($this->keysByRow[$row]);
        }
    }

    public function involvesForgotten(Check $check): bool
    {
        foreach ($check->rows() as $row) {
            if (isset($this->forgotten[$row])) {
                return true;
            }
        }

        return false;
    }

    private function drop(string $key): void
    {
        foreach ($this->rows[$key] as $row) {
            unset($this->keysByRow[$row][$key]);
        }

        unset($this->checks[$key], $this->rows[$key], $this->decisions[$key]);
    }
}
