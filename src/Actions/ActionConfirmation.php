<?php

namespace Lomkit\Rest\Actions;

use Lomkit\Rest\Exceptions\InvalidActionException;

final readonly class ActionConfirmation
{
    /** @param array<mixed> $data */
    private function __construct(
        public string $message,
        public array $data,
        public int $seconds,
    ) {
        if ($data !== [] && array_is_list($data)) {
            throw InvalidActionException::confirmationDataList();
        }

        if ($seconds < 1) {
            throw InvalidActionException::confirmationExpiry($seconds);
        }
    }

    public static function make(string $message): self
    {
        return new self($message, [], 300);
    }

    /** @param array<string, mixed> $data */
    public function data(array $data): self
    {
        return new self($this->message, $data, $this->seconds);
    }

    public function expiresIn(int $seconds): self
    {
        return new self($this->message, $this->data, $seconds);
    }
}
