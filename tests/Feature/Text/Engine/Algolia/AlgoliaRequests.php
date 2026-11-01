<?php

namespace Lomkit\Rest\Tests\Feature\Text\Engine\Algolia;

use Algolia\AlgoliaSearch\Http\HttpClientInterface;
use Algolia\AlgoliaSearch\Http\Psr7\Response;
use Psr\Http\Message\RequestInterface;

final class AlgoliaRequests implements HttpClientInterface
{
    /** @var list<array{string, string, mixed}> */
    private array $requests = [];

    /** @var array<string, array{int, array<string, mixed>}> */
    private array $responses = [];

    /** @param array<string, mixed> $body */
    public function respond(string $method, string $path, int $status, array $body): void
    {
        $this->responses["{$method} {$path}"] = [$status, $body];
    }

    #[\Override]
    public function sendRequest(RequestInterface $request, $timeout, $connectTimeout): Response
    {
        $method = $request->getMethod();
        $path = $request->getUri()->getPath();
        $this->requests[] = [$method, $path, json_decode((string) $request->getBody(), true)];
        [$status, $body] = $this->responses["{$method} {$path}"] ?? [404, ['message' => 'Index does not exist', 'status' => 404]];

        return new Response($status, ['Content-Type' => 'application/json; charset=UTF-8'], (string) json_encode($body));
    }

    /** @return list<array{string, string, mixed}> */
    public function sent(string $method, string $path): array
    {
        return array_values(array_filter($this->requests, fn (array $request): bool => $request[0] === $method && $request[1] === $path));
    }
}
