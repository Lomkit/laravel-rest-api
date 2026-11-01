<?php

namespace Lomkit\Rest\Console\QuickStart;

final readonly class FirstRequest
{
    private const string SEARCH = 'curl -X POST %s/%s%s -H "Accept: application/json" -H "Content-Type: application/json" -d \'{"search":{"limit":10}}\'';

    private const string BEARER = ' -H "Authorization: Bearer <token>"';

    public function __construct(private string $userModel, private string $url, private ?string $search, private ApiGuard $guard) {}

    /** @return list<string> */
    public function commands(): array
    {
        $package = $this->guard->kind->package();
        $first = $package?->tokenCommand($this->userModel) ?? "Authenticate with the {$this->guard->name} guard to call the API.";

        if ($this->search === null) {
            return [$first];
        }

        return [$first, sprintf(self::SEARCH, rtrim($this->url, '/'), ltrim($this->search, '/'), $package === null ? '' : self::BEARER)];
    }
}
