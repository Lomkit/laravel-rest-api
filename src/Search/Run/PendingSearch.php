<?php

namespace Lomkit\Rest\Search\Run;

use Illuminate\Auth\AuthManager;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Container\Container;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\LazyCollection;
use Lomkit\Rest\Authorization\ActingUser;
use Lomkit\Rest\Exceptions\SearchRunException;
use Lomkit\Rest\Http\Resource;

final readonly class PendingSearch
{
    /**
     * @param  class-string<Resource>  $resource
     * @param  array<string, mixed>|null  $search
     */
    public function __construct(
        private Container $container,
        private string $resource,
        private ?ActingUser $user = null,
        private ?array $search = null,
    ) {}

    public function as(?Authenticatable $user, ?string $guard = null): self
    {
        $guard ??= $this->container
            ->make(AuthManager::class)
            ->getDefaultDriver();

        return new self($this->container, $this->resource, ActingUser::of($guard, $user), $this->search);
    }

    /** @param array<string, mixed> $search */
    public function payload(array $search): self
    {
        return new self($this->container, $this->resource, $this->user, $search);
    }

    /** @return LengthAwarePaginator<int, Model> */
    public function page(?int $page = null): LengthAwarePaginator
    {
        return $this->run($page)
            ->page()
            ->rows;
    }

    /** @return array<array-key, mixed> */
    public function render(?int $page = null): array
    {
        return $this->run($page)
            ->page()
            ->response()
            ->body();
    }

    /** @return LazyCollection<int, array<string, mixed>> */
    public function lazy(int $chunk = 500, ?int $max = null): LazyCollection
    {
        if ($chunk < 1) {
            throw SearchRunException::invalidChunk($chunk);
        }

        if ($max !== null && $max < 0) {
            throw SearchRunException::invalidMax($max);
        }

        return $this->run(null)->lazy($chunk, $max);
    }

    private function run(?int $page): SearchRun
    {
        if ($this->user === null) {
            throw SearchRunException::missingUser($this->resource);
        }

        return PayloadSearchRequest::of($this->container, $this->resource, $this->body($page), $this->user)->run();
    }

    /** @return array<string, mixed> */
    private function body(?int $page): array
    {
        if ($page === null) {
            return $this->search === null ? [] : ['search' => $this->search];
        }

        return ['search' => [...$this->search ?? [], 'page' => $page]];
    }
}
