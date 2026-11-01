<?php

namespace Lomkit\Rest\Tests\Feature\Text\Engine\Meilisearch;

use Illuminate\Http\Client\Factory;
use Illuminate\Http\Client\Response;
use RuntimeException;

final readonly class MeilisearchIndexes
{
    private const int TIMEOUT_MS = 20_000;

    public function __construct(
        private Factory $http,
        private string $host,
        private ?string $key,
    ) {}

    /** @param array<string, mixed> $settings */
    public function reset(string $uid, string $primaryKey, array $settings): void
    {
        $this->send('DELETE', "indexes/{$uid}");
        $this->send('POST', 'indexes', ['uid' => $uid, 'primaryKey' => $primaryKey]);
        $this->configure($uid, $settings);
    }

    /** @param array<string, mixed> $settings */
    public function configure(string $uid, array $settings): void
    {
        $this->send('PATCH', "indexes/{$uid}/settings", $settings);
        $this->settle($uid);
    }

    public function drop(string $uid): void
    {
        $this->send('DELETE', "indexes/{$uid}");
        $this->settle($uid);
    }

    /** @param list<string> $actions */
    public function key(string $uid, array $actions): string
    {
        $key = $this->send('POST', 'keys', ['actions' => $actions, 'indexes' => [$uid], 'expiresAt' => null])->json('key');

        return is_string($key) ? $key : throw new RuntimeException('Meilisearch created no key.');
    }

    public function forget(string $key): void
    {
        $this->send('DELETE', "keys/{$key}");
    }

    public function settle(string $uid): void
    {
        $deadline = hrtime(true) + self::TIMEOUT_MS * 1_000_000;

        while ($this->tasks($uid, 'enqueued,processing') > 0) {
            if (hrtime(true) > $deadline) {
                throw new RuntimeException("The Meilisearch tasks of [{$uid}] did not finish.");
            }

            usleep(20_000);
        }

        $failed = $this->send('GET', 'tasks', ['indexUids' => $uid, 'statuses' => 'failed', 'types' => 'documentAdditionOrUpdate,documentDeletion,settingsUpdate,indexCreation'])->json('results');

        if (is_array($failed) && $failed !== []) {
            throw new RuntimeException('A Meilisearch task failed: '.json_encode($failed));
        }
    }

    private function tasks(string $uid, string $statuses): int
    {
        $total = $this->send('GET', 'tasks', ['indexUids' => $uid, 'statuses' => $statuses, 'limit' => 1])->json('total');

        return is_int($total) ? $total : 0;
    }

    /** @param array<string, mixed> $data */
    private function send(string $method, string $uri, array $data = []): Response
    {
        $request = $this->http
            ->baseUrl(rtrim($this->host, '/').'/')
            ->acceptJson()
            ->asJson()
            ->timeout(10);

        $options = $method === 'GET' ? ['query' => $data] : ($data === [] ? [] : ['json' => $data]);

        return ($this->key === null ? $request : $request->withToken($this->key))
            ->send($method, $uri, $options)
            ->throw();
    }
}
