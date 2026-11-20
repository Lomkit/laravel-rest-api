<?php

namespace Lomkit\Rest\Tests\Feature\Text\Engine\Typesense;

use Illuminate\Http\Client\Factory;
use Illuminate\Http\Client\PendingRequest;

final readonly class TypesenseCollections
{
    public function __construct(
        private Factory $http,
        private string $url,
        private string $key,
    ) {}

    public function drop(string $name): void
    {
        $response = $this->http
            ->baseUrl($this->url)
            ->withHeaders(['X-TYPESENSE-API-KEY' => $this->key])
            ->acceptJson()
            ->timeout(10)
            ->delete("collections/{$name}");

        if ($response->status() !== 404) {
            $response->throw();
        }
    }

    public function exists(string $name): bool
    {
        $response = $this->request()->get("collections/{$name}");

        if ($response->status() !== 404) {
            $response->throw();
        }

        return $response->successful();
    }

    /** @param list<array<string, mixed>> $items */
    public function curate(string $collection, string $set, array $items): void
    {
        $this->request()->put("curation_sets/{$set}", ['items' => $items])->throw();
        $this->request()->patch("collections/{$collection}", ['curation_sets' => [$set]])->throw();
    }

    /** @param array<string, mixed> $value */
    public function preset(string $name, array $value): void
    {
        $this->request()->put("presets/{$name}", ['value' => $value])->throw();
    }

    public function forget(string $path): void
    {
        $this->request()->delete($path);
    }

    private function request(): PendingRequest
    {
        return $this->http->baseUrl($this->url)->withHeaders(['X-TYPESENSE-API-KEY' => $this->key])->acceptJson()->asJson()->timeout(10);
    }
}
