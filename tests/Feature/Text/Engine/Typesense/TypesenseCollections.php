<?php

namespace Lomkit\Rest\Tests\Feature\Text\Engine\Typesense;

use Illuminate\Http\Client\Factory;

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
}
