<?php

namespace Lomkit\Rest\Console\QuickStart;

final readonly class FirstRequest
{
    private const string TOKEN = 'php artisan tinker --execute="echo %s::first()->createToken(\'cli\')->plainTextToken;"';

    private const string SEARCH = 'curl -X POST %s/%s -H "Authorization: Bearer <token>" -H "Accept: application/json" -H "Content-Type: application/json" -d \'{"search":{"limit":10}}\'';

    public function __construct(private string $userModel, private string $url, private ?string $search) {}

    /** @return list<string> */
    public function commands(): array
    {
        $token = sprintf(self::TOKEN, $this->userModel);

        if ($this->search === null) {
            return [$token];
        }

        return [$token, sprintf(self::SEARCH, rtrim($this->url, '/'), ltrim($this->search, '/'))];
    }
}
