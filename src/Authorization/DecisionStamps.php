<?php

namespace Lomkit\Rest\Authorization;

use Illuminate\Contracts\Cache\Repository;
use Illuminate\Support\Str;

final class DecisionStamps
{
    private const string KEY = 'rest.authorizations.stamp';

    private ?string $stamps = null;

    public function __construct(private readonly ?string $user) {}

    public static function renew(Repository $cache, ?string $user): void
    {
        $cache->forever(self::key($user), Str::random());
    }

    public function read(Repository $cache): string
    {
        if ($this->stamps !== null) {
            return $this->stamps;
        }

        $stamps = [];
        $missing = [];

        foreach ($cache->getMultiple($this->keys()) as $key => $stamp) {
            if (! is_string($stamp)) {
                $stamp = $missing[$key] = Str::random();
            }

            $stamps[] = $stamp;
        }

        if ($missing !== []) {
            $cache->setMultiple($missing);
        }

        return $this->stamps = implode('|', $stamps);
    }

    /** @return list<string> */
    private function keys(): array
    {
        return $this->user === null ? [self::key(null)] : [self::key(null), self::key($this->user)];
    }

    private static function key(?string $user): string
    {
        return $user === null ? self::KEY : self::KEY.'.'.hash('sha256', $user);
    }
}
