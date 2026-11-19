<?php

namespace Lomkit\Rest\Tests\Feature\Text\Engine\Stubs;

use GuzzleHttp\Psr7\Response;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

final class RecordingHttpClient implements ClientInterface
{
    /** @var list<array{string, string, string, string}> */
    public array $requests = [];

    /** @param array<mixed> $answer */
    public function __construct(private readonly array $answer) {}

    #[\Override]
    public function sendRequest(RequestInterface $request): ResponseInterface
    {
        $this->requests[] = [$request->getMethod(), $request->getUri()->getPath(), $request->getUri()->getQuery(), (string) $request->getBody()];

        return new Response(200, ['Content-Type' => 'application/json'], (string) json_encode($this->answer));
    }
}
