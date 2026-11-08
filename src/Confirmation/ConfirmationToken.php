<?php

namespace Lomkit\Rest\Confirmation;

final readonly class ConfirmationToken
{
    private function __construct(
        private string $key,
        private string $subject,
    ) {}

    /** @param list<mixed> $subject */
    public static function for(string $key, array $subject): self
    {
        return new self($key, json_encode($subject, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
    }

    public function issue(int $expires): string
    {
        return $expires.'.'.$this->sign($expires);
    }

    public function accepts(?string $token, int $now): bool
    {
        if ($token === null || preg_match('/^(\d{1,12})\.([0-9a-f]{64})$/', $token, $parts) !== 1) {
            return false;
        }

        $expires = (int) $parts[1];

        return $expires > $now && hash_equals($this->sign($expires), $parts[2]);
    }

    private function sign(int $expires): string
    {
        return hash_hmac('sha256', $expires."\n".$this->subject, $this->key);
    }
}
